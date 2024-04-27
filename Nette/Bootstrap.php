<?php

declare(strict_types=1);

namespace Omnicado;

use Nette\DI\Container;
use Nette\Bootstrap\Configurator;
use Symfony\Component\Dotenv\Dotenv;

class Bootstrap
{
    private static null|Container $testsContainer = null;

    public static function boot(): Configurator
    {
        (new Dotenv())->loadEnv(__DIR__ . '/../.env');

        $configurator = new Configurator();

        $configurator->addDynamicParameters([
            'env' => $_ENV,
        ]);

        $isDebug = (bool) ($_ENV['APP_DEBUG'] ?? false);

        $configurator->setDebugMode($isDebug);
        $configurator->enableTracy(__DIR__ . '/../var/log');

        $configurator->setTimeZone('Europe/Prague');
        $configurator->setTempDirectory(__DIR__ . '/../var/temp');

        $configurator->addConfig(__DIR__ . '/../config/common.neon');

        $localConfigPath = __DIR__ . '/../config/local.neon';

        if (is_file($localConfigPath)) {
            $configurator->addConfig($localConfigPath);
        }

        return $configurator;
    }

    public static function getTestsContainer(): Container
    {
        if (self::$testsContainer === null) {
            $_ENV['APP_ENV'] = 'test';

            $configurator = self::boot();

            $configurator->addConfig(__DIR__ . '/../config/tests.neon');

            self::$testsContainer = $configurator->createContainer();
        }

        return self::$testsContainer;
    }
}
