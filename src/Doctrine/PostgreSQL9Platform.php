<?php

namespace App\Doctrine;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\PostgreSQLSchemaManager;

/**
 * Platform PostgreSQL 9 compatible pour éviter l'utilisation de attgenerated
 */
class PostgreSQL9Platform extends PostgreSQLPlatform
{
    public function createSchemaManager(\Doctrine\DBAL\Connection $connection): PostgreSQLSchemaManager
    {
        return new PostgreSQL9SchemaManager($connection, $this);
    }
}
