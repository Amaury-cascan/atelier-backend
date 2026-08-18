<?php

namespace App\Tests\Service;

use App\Entity\Client;
use App\Entity\User;
use App\Repository\ClientRepository;
use App\Repository\UserRepository;
use App\Service\PurgeInactiveClients;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PurgeInactiveClientsTest extends TestCase
{
    private ClientRepository&MockObject $clientRepository;
    private UserRepository&MockObject $userRepository;
    private Connection&MockObject $connection;
    private PurgeInactiveClients $service;

    protected function setUp(): void
    {
        $this->clientRepository = $this->createMock(ClientRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->connection = $this->createMock(Connection::class);
        $this->connection->method('quoteIdentifier')->willReturnCallback(
            static fn (string $name): string => '"' . $name . '"'
        );

        $this->service = new PurgeInactiveClients(
            $this->clientRepository,
            $this->userRepository,
            $this->connection,
        );
    }

    public function testDryRunDoesNotWrite(): void
    {
        $this->clientRepository->method('findWithLastAppointmentBefore')->willReturn([
            $this->client(12),
        ]);
        $this->userRepository->method('find')->with(1)->willReturn(new User());
        $this->connection->expects($this->never())->method('beginTransaction');
        $this->connection->expects($this->never())->method('executeStatement');

        $result = $this->service->execute(new \DateTimeImmutable('2026-08-18'), true);

        $this->assertSame(1, $result->candidates);
        $this->assertSame(0, $result->purged);
        $this->assertTrue($result->dryRun);
        $this->assertTrue($result->fallbackUserExists);
    }

    public function testReassignsAppointmentsWhenUserOneExists(): void
    {
        $this->clientRepository->method('findWithLastAppointmentBefore')->willReturn([
            $this->client(12),
        ]);
        $this->userRepository->method('find')->with(1)->willReturn(new User());

        $sql = [];
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');
        $this->connection->method('executeStatement')->willReturnCallback(
            function (string $query, array $params = []) use (&$sql): int {
                $sql[] = ['sql' => $query, 'params' => $params];

                return 1;
            }
        );

        $result = $this->service->execute(new \DateTimeImmutable('2026-08-18'));

        $this->assertSame(1, $result->purged);
        $this->assertTrue($result->fallbackUserExists);
        $this->assertSame('UPDATE appointment SET client_id = ? WHERE client_id = ?', $sql[0]['sql']);
        $this->assertSame([1, 12], $sql[0]['params']);
        $this->assertSame('DELETE FROM "user" WHERE id = ?', $sql[array_key_last($sql)]['sql']);
        $this->assertSame([12], $sql[array_key_last($sql)]['params']);
    }

    public function testDeletesAppointmentsWhenUserOneIsMissing(): void
    {
        $this->clientRepository->method('findWithLastAppointmentBefore')->willReturn([
            $this->client(12),
        ]);
        $this->userRepository->method('find')->with(1)->willReturn(null);

        $sql = [];
        $this->connection->method('executeStatement')->willReturnCallback(
            function (string $query, array $params = []) use (&$sql): int {
                $sql[] = ['sql' => $query, 'params' => $params];

                return 1;
            }
        );

        $result = $this->service->execute(new \DateTimeImmutable('2026-08-18'));

        $this->assertSame(1, $result->purged);
        $this->assertFalse($result->fallbackUserExists);
        $this->assertSame('DELETE FROM appointment WHERE client_id = ?', $sql[0]['sql']);
        $this->assertSame([12], $sql[0]['params']);
    }

    public function testSkipsAdminAndFallbackUser(): void
    {
        $this->clientRepository->method('findWithLastAppointmentBefore')->willReturn([
            $this->client(1),
            $this->client(8, ['ROLE_ADMIN']),
        ]);
        $this->userRepository->method('find')->with(1)->willReturn(new User());
        $this->connection->expects($this->never())->method('executeStatement');

        $result = $this->service->execute(new \DateTimeImmutable('2026-08-18'));

        $this->assertSame(0, $result->candidates);
        $this->assertSame(0, $result->purged);
    }

    public function testRollsBackOnFailure(): void
    {
        $this->clientRepository->method('findWithLastAppointmentBefore')->willReturn([
            $this->client(12),
        ]);
        $this->userRepository->method('find')->with(1)->willReturn(new User());
        $this->connection->method('executeStatement')->willThrowException(new \RuntimeException('db'));
        $this->connection->expects($this->once())->method('rollBack');

        $this->expectException(\RuntimeException::class);

        $this->service->execute(new \DateTimeImmutable('2026-08-18'));
    }

    public function testDeleteClientReassignsAppointmentsWhenUserOneExists(): void
    {
        $this->userRepository->method('find')->with(1)->willReturn(new User());

        $sql = [];
        $this->connection->method('executeStatement')->willReturnCallback(
            function (string $query, array $params = []) use (&$sql): int {
                $sql[] = ['sql' => $query, 'params' => $params];

                return 1;
            }
        );

        $reassigned = $this->service->deleteClient($this->client(12));

        $this->assertTrue($reassigned);
        $this->assertSame('UPDATE appointment SET client_id = ? WHERE client_id = ?', $sql[0]['sql']);
        $this->assertSame([1, 12], $sql[0]['params']);
        $this->assertSame([12], $sql[array_key_last($sql)]['params']);
    }

    public function testDeleteClientRemovesAppointmentsWhenUserOneIsMissing(): void
    {
        $this->userRepository->method('find')->with(1)->willReturn(null);

        $sql = [];
        $this->connection->method('executeStatement')->willReturnCallback(
            function (string $query, array $params = []) use (&$sql): int {
                $sql[] = ['sql' => $query, 'params' => $params];

                return 1;
            }
        );

        $reassigned = $this->service->deleteClient($this->client(12));

        $this->assertFalse($reassigned);
        $this->assertSame('DELETE FROM appointment WHERE client_id = ?', $sql[0]['sql']);
    }

    public function testDeleteClientRejectsFallbackAndAdminAccounts(): void
    {
        $this->connection->expects($this->never())->method('executeStatement');

        $this->expectException(\DomainException::class);
        $this->service->deleteClient($this->client(1));
    }

    public function testDeleteClientRejectsAdmin(): void
    {
        $this->connection->expects($this->never())->method('executeStatement');

        $this->expectException(\DomainException::class);
        $this->service->deleteClient($this->client(8, ['ROLE_ADMIN']));
    }
    private function client(int $id, array $roles = []): Client
    {
        $client = new Client();
        $client->setRoles($roles);

        $property = new \ReflectionProperty(User::class, 'id');
        $property->setValue($client, $id);

        return $client;
    }
}
