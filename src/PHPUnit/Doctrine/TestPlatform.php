<?php
declare(strict_types=1);

namespace App\PHPUnit\Doctrine;

use Doctrine\DBAL\Platforms\Keywords\MariaDb102Keywords;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Types\Types;

/**
 * We just want to override the getTruncateTableSQL() but because someone
 * defined MariaDb1027Platform as final class we have to duplicate all other
 * methods from there...
 */
class TestPlatform extends MySqlPlatform
{
    /**
     * {@inheritdoc}
     */
    public function getTruncateTableSQL($tableName, $cascade = false)
    {
        return sprintf('SET foreign_key_checks = 0;TRUNCATE %s;SET foreign_key_checks = 1;', $tableName);
    }

    /**
     * {@inheritdoc}
     *
     * @link https://mariadb.com/kb/en/library/json-data-type/
     */
    public function getJsonTypeDeclarationSQL(array $field) : string
    {
        return 'LONGTEXT';
    }

    /**
     * {@inheritdoc}
     */
    protected function getReservedKeywordsClass() : string
    {
        return MariaDb102Keywords::class;
    }

    /**
     * {@inheritdoc}
     */
    protected function initializeDoctrineTypeMappings() : void
    {
        parent::initializeDoctrineTypeMappings();

        $this->doctrineTypeMapping['json'] = Types::JSON;
    }
}