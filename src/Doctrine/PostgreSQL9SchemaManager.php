<?php

namespace App\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\PostgreSQLSchemaManager as BasePostgreSQLSchemaManager;

/**
 * SchemaManager PostgreSQL 9 qui évite l'utilisation de attgenerated
 */
class PostgreSQL9SchemaManager extends BasePostgreSQLSchemaManager
{
    protected function _getPortableTableColumnDefinition($tableColumn): array
    {
        // Remplacer attgenerated par NULL pour PostgreSQL 9
        if (isset($tableColumn['attgenerated'])) {
            unset($tableColumn['attgenerated']);
        }
        
        return parent::_getPortableTableColumnDefinition($tableColumn);
    }

    protected function fetchTableColumnsByTableName(string $databaseName, string $tableName): array
    {
        // Requête adaptée pour PostgreSQL 9 (sans attgenerated)
        // Version simplifiée qui évite attgenerated
        $sql = <<<'SQL'
SELECT
    a.attnum,
    quote_ident(a.attname) AS field,
    t.typname AS type,
    format_type(a.atttypid, a.atttypmod) AS complete_type,
    a.attnotnull AS isnotnull,
    (SELECT t1.typname FROM pg_catalog.pg_type t1 WHERE t1.oid = a.atttypid) AS typname,
    CASE WHEN a.atttypmod != -1 THEN a.atttypmod - 4 ELSE NULL END AS length,
    (SELECT t1.typname FROM pg_catalog.pg_type t1 WHERE t1.oid = a.atttypid) AS type_name,
    pg_get_expr(adbin, adrelid) AS default,
    a.attndims,
    a.atttypmod,
    CASE WHEN a.atthasdef THEN pg_get_expr(adbin, adrelid) ELSE NULL END AS default_value,
    NULL AS attgenerated
FROM pg_catalog.pg_attribute a
    LEFT JOIN pg_catalog.pg_attrdef ad ON a.attrelid = ad.adrelid AND a.attnum = ad.adnum
    JOIN pg_catalog.pg_type t ON a.atttypid = t.oid
    JOIN pg_catalog.pg_class c ON a.attrelid = c.oid
    JOIN pg_catalog.pg_namespace n ON c.relnamespace = n.oid
WHERE
    a.attnum > 0
    AND NOT a.attisdropped
    AND n.nspname = ?
    AND c.relname = ?
ORDER BY a.attnum
SQL;

        return $this->_conn->fetchAllAssociative($sql, [$databaseName, $tableName]);
    }
}
