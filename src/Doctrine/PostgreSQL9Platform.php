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

    /**
     * Surcharge pour PostgreSQL 9 : pas de attgenerated
     * Retourne la version de PostgreSQLPlatform (sans le CASE WHEN attgenerated)
     */
    public function getDefaultColumnValueSQLSnippet(): string
    {
        // Version pour PostgreSQL < 12 (sans attgenerated)
        return <<<'SQL'
            SELECT pg_get_expr(adbin, adrelid)
             FROM pg_attrdef
             WHERE c.oid = pg_attrdef.adrelid
                AND pg_attrdef.adnum=a.attnum
        SQL;
    }
}
