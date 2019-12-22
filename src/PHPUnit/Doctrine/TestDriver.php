<?php
declare(strict_types=1);

namespace App\PHPUnit\Doctrine;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\PDOMySql\Driver;
use Doctrine\DBAL\Platforms\MySQL57Platform;
use Doctrine\DBAL\Platforms\MySQL80Platform;

/**
 * We simply want to customize the generated TRUNCATE sql because
 * DoctrineFixtures\ORMPurger does not delete leaf tables first and causes
 * "1701 Cannot truncate a table referenced in a foreign key constraint".
 * But because we can only override driver_class in the config, and not the
 * platform, we have to implement this driver and override
 * createDatabasePlatformForVersion(). And because someone defined the
 * version-check functions as private we have to implement them here too...
 */
class TestDriver extends Driver
{
    /**
     * {@inheritdoc}
     *
     * @throws DBALException
     */
    public function createDatabasePlatformForVersion($version)
    {
        $mariadb = stripos($version, 'mariadb') !== false;
        if ($mariadb && version_compare($this->getMariaDbMysqlVersionNumber($version), '10.2.7', '>=')) {
            return new TestPlatform();
        }

        if (! $mariadb) {
            $oracleMysqlVersion = $this->getOracleMysqlVersionNumber($version);
            if (version_compare($oracleMysqlVersion, '8', '>=')) {
                return new MySQL80Platform();
            }
            if (version_compare($oracleMysqlVersion, '5.7.9', '>=')) {
                return new MySQL57Platform();
            }
        }

        return $this->getDatabasePlatform();
    }

    /**
     * Detect MariaDB server version, including hack for some mariadb distributions
     * that starts with the prefix '5.5.5-'
     *
     * @param string $versionString Version string as returned by mariadb server, i.e. '5.5.5-Mariadb-10.0.8-xenial'
     *
     * @throws DBALException
     */
    private function getMariaDbMysqlVersionNumber(string $versionString) : string
    {
        if (! preg_match(
            '/^(?:5\.5\.5-)?(mariadb-)?(?P<major>\d+)\.(?P<minor>\d+)\.(?P<patch>\d+)/i',
            $versionString,
            $versionParts
        )) {
            throw DBALException::invalidPlatformVersionSpecified(
                $versionString,
                '^(?:5\.5\.5-)?(mariadb-)?<major_version>.<minor_version>.<patch_version>'
            );
        }

        return $versionParts['major'] . '.' . $versionParts['minor'] . '.' . $versionParts['patch'];
    }

    /**
     * Get a normalized 'version number' from the server string
     * returned by Oracle MySQL servers.
     *
     * @param string $versionString Version string returned by the driver, i.e. '5.7.10'
     *
     * @throws DBALException
     */
    private function getOracleMysqlVersionNumber(string $versionString) : string
    {
        if (! preg_match(
            '/^(?P<major>\d+)(?:\.(?P<minor>\d+)(?:\.(?P<patch>\d+))?)?/',
            $versionString,
            $versionParts
        )) {
            throw DBALException::invalidPlatformVersionSpecified(
                $versionString,
                '<major_version>.<minor_version>.<patch_version>'
            );
        }
        $majorVersion = $versionParts['major'];
        $minorVersion = $versionParts['minor'] ?? 0;
        $patchVersion = $versionParts['patch'] ?? null;

        if ($majorVersion === '5' && $minorVersion === '7' && $patchVersion === null) {
            $patchVersion = '9';
        }

        return $majorVersion . '.' . $minorVersion . '.' . $patchVersion;
    }
}
