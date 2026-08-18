<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\User;
use App\Repository\ClientRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Connection;

/**
 * Purge les comptes clientes inactifs depuis 3 ans.
 * Les rendez-vous sont rattachés à l'utilisateur d'id 1 s'il existe, sinon supprimés.
 */
final class PurgeInactiveClients
{
    public const FALLBACK_USER_ID = 1;
    public const RETENTION_PERIOD = '-3 years';

    public function __construct(
        private readonly ClientRepository $clientRepository,
        private readonly UserRepository $userRepository,
        private readonly Connection $connection,
    ) {
    }

    public function execute(\DateTimeInterface $now, bool $dryRun = false): PurgeInactiveClientsResult
    {
        $threshold = \DateTimeImmutable::createFromInterface($now)->modify(self::RETENTION_PERIOD);
        $clients = $this->clientRepository->findWithLastAppointmentBefore($threshold);
        $fallbackUser = $this->userRepository->find(self::FALLBACK_USER_ID);
        $fallbackExists = $fallbackUser instanceof User;

        $candidates = 0;
        $purged = 0;

        foreach ($clients as $client) {
            if (!$this->isEligible($client)) {
                continue;
            }

            ++$candidates;

            if ($dryRun) {
                continue;
            }

            $this->purgeClient($client->getId(), $fallbackExists);
            ++$purged;
        }

        return new PurgeInactiveClientsResult(
            candidates: $candidates,
            purged: $purged,
            fallbackUserExists: $fallbackExists,
            dryRun: $dryRun,
        );
    }

    /**
     * Suppression manuelle d'une cliente (back-office).
     * Les rendez-vous sont rattachés à l'utilisateur d'id 1 s'il existe, sinon supprimés.
     *
     * @return bool true si les rendez-vous ont été rattachés à l'id 1
     */
    public function deleteClient(Client $client): bool
    {
        if (!$this->isEligible($client)) {
            throw new \DomainException('Ce compte ne peut pas être supprimé (compte de repli ou administrateur).');
        }

        $fallbackExists = $this->userRepository->find(self::FALLBACK_USER_ID) instanceof User;
        $this->purgeClient($client->getId(), $fallbackExists);

        return $fallbackExists;
    }

    private function isEligible(Client $client): bool
    {
        if ($client->getId() === self::FALLBACK_USER_ID) {
            return false;
        }

        return !in_array('ROLE_ADMIN', $client->getRoles(), true);
    }

    private function purgeClient(?int $clientId, bool $fallbackExists): void
    {
        if ($clientId === null || $clientId === self::FALLBACK_USER_ID) {
            return;
        }

        $this->connection->beginTransaction();

        try {
            $this->reassignOrDeleteAppointments($clientId, $fallbackExists);
            $this->deleteDependentRows($clientId);
            $this->connection->executeStatement(
                'DELETE FROM ' . $this->connection->quoteIdentifier('user') . ' WHERE id = ?',
                [$clientId]
            );
            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }
    }

    private function reassignOrDeleteAppointments(int $clientId, bool $fallbackExists): void
    {
        if ($fallbackExists) {
            $this->connection->executeStatement(
                'UPDATE appointment SET client_id = ? WHERE client_id = ?',
                [self::FALLBACK_USER_ID, $clientId]
            );

            return;
        }

        $this->connection->executeStatement(
            'DELETE FROM appointment WHERE client_id = ?',
            [$clientId]
        );
    }

    private function deleteDependentRows(int $clientId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM picture WHERE client_information_id IN (SELECT id FROM client_information WHERE client_id = ?)',
            [$clientId]
        );
        $this->connection->executeStatement(
            'DELETE FROM client_information WHERE client_id = ?',
            [$clientId]
        );
        $this->connection->executeStatement(
            'DELETE FROM review WHERE client_id = ?',
            [$clientId]
        );
        $this->connection->executeStatement(
            'DELETE FROM enveloppe WHERE user_mois_id IN (SELECT id FROM user_mois WHERE current_user_id = ?)',
            [$clientId]
        );
        $this->connection->executeStatement(
            'DELETE FROM user_depense_fixe WHERE user_mois_id IN (SELECT id FROM user_mois WHERE current_user_id = ?)',
            [$clientId]
        );
        $this->connection->executeStatement(
            'DELETE FROM user_mois WHERE current_user_id = ?',
            [$clientId]
        );
    }
}
