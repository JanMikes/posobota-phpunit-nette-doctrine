<?php

declare(strict_types=1);

namespace Omnicado\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolsException;
use Nette\DI\Container;
use Nette\Utils\Finder;
use Throwable;

final class TestingDatabase
{
    public static function init(Container $container): void
    {
        /** @var Connection $connection */
        $connection = $container->getByType(Connection::class);

        $params = $connection->getParams();
        $name = $params['dbname'] ?? '';
        if (str_contains($name, 'prod')) {
            throw new \Exception('Please doublececk your tests database connection if it is not production!!');
        }
        unset($params['dbname'], $params['path'], $params['url']);

        $tmpConnection = DriverManager::getConnection($params);
        $tmpConnection->connect();
        $schemaManager = $tmpConnection->createSchemaManager();

        if (in_array($name, $schemaManager->listDatabases(), true)) {
            $schemaManager->dropDatabase($name);
        }

        $schemaManager->createDatabase($name);
        $tmpConnection->close();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->getByType(EntityManagerInterface::class);

        $metadatas = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);
        $createSchemaSql = $schemaTool->getCreateSchemaSql($metadatas);

        foreach ($createSchemaSql as $sql) {
            // Already exists ...
            if ($sql === 'CREATE SCHEMA public') {
                continue;
            }

            try {
                $entityManager->getConnection()->executeStatement($sql);
            } catch (Throwable $e) {
                throw ToolsException::schemaToolFailure($sql, $e);
            }
        }

        // TODO: fixtures loading is missing here
    }

    private const int|float CACHE_VALID_FOR_SECONDS = 24 * 60 * 60;  // 24 hours

    public static function calculateDirectoriesHash(string ...$directories): string
    {
        $finder = Finder::find('*.php')->in(...$directories);
        $files = array_keys(iterator_to_array($finder->getIterator()));
        $hash = '';

        foreach ($files as $file) {
            $hash .= md5_file($file);
        }

        return $hash;
    }

    public static function isCacheUpToDate(string $cacheFilePath, string $currentDatabaseHash): bool
    {
        if (!file_exists($cacheFilePath)) {
            return false;
        }

        $cachedDatabaseHash = file_get_contents($cacheFilePath);
        $lastModificationTimestamp = filemtime($cacheFilePath);
        $cacheValidUntil = time() + self::CACHE_VALID_FOR_SECONDS;

        if ($cacheValidUntil < $lastModificationTimestamp) {
            return false;
        }

        return $currentDatabaseHash === $cachedDatabaseHash;
    }
}
