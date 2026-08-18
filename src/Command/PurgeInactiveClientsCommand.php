<?php

namespace App\Command;

use App\Service\PurgeInactiveClients;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:purge-inactive-clients',
    description: 'Supprime les comptes clientes sans rendez-vous depuis 3 ans.',
)]
class PurgeInactiveClientsCommand extends Command
{
    public function __construct(
        private readonly PurgeInactiveClients $purgeInactiveClients,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(
                'Les comptes dont le dernier rendez-vous date de plus de 3 ans sont supprimés. '
                . 'Les rendez-vous sont rattachés à l\'utilisateur d\'id 1 s\'il existe, sinon ils sont supprimés. '
                . 'Les comptes sans aucun rendez-vous et les comptes administrateurs sont ignorés.'
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Liste les comptes concernés sans les supprimer.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));

        $io->title($dryRun ? 'Simulation de la purge des comptes inactifs' : 'Purge des comptes inactifs');

        $result = $this->purgeInactiveClients->execute($now, $dryRun);

        if (!$result->fallbackUserExists) {
            $io->warning('Aucun utilisateur d\'id 1 : les rendez-vous des comptes purgés seront supprimés.');
        } else {
            $io->comment('Utilisateur d\'id 1 trouvé : les rendez-vous seront rattachés à ce compte.');
        }

        if ($result->candidates === 0) {
            $io->success('Aucun compte inactif à purger.');

            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->success(sprintf('%d compte(s) seraient supprimé(s).', $result->candidates));

            return Command::SUCCESS;
        }

        $io->success(sprintf('%d compte(s) supprimé(s) sur %d candidat(s).', $result->purged, $result->candidates));

        return Command::SUCCESS;
    }
}
