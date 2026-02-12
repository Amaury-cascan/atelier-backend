<?php

namespace App\Doctrine;

use Doctrine\DBAL\Platforms\PostgreSQL100Platform;
use Doctrine\DBAL\Schema\PostgreSQLSchemaManager;

/**
 * Platform PostgreSQL 9 compatible pour éviter l'utilisation de attgenerated
 * Hérite de PostgreSQL100Platform pour éviter PostgreSQL120Platform qui utilise attgenerated
 */
class PostgreSQL9Platform extends PostgreSQL100Platform
{
    public function createSchemaManager(\Doctrine\DBAL\Connection $connection): PostgreSQLSchemaManager
    {
        return new PostgreSQL9SchemaManager($connection, $this);
    }

    /**
     * Surcharge pour PostgreSQL 9 : pas de attgenerated
     * Retourne la version pour PostgreSQL < 12 (sans le CASE WHEN attgenerated)
     */
    public function getDefaultColumnValueSQLSnippet(): string
    {
        // Version pour PostgreSQL < 12 (sans attgenerated)
        // PostgreSQL100Platform n'utilise pas attgenerated, donc on retourne sa version
        return parent::getDefaultColumnValueSQLSnippet();
    }
}
