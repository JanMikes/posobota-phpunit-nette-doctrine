<?php

declare(strict_types=1);

namespace Omnicado\Tests\PHPUnit;

use Doctrine\ORM\EntityManagerInterface;
use Omnicado\Bootstrap;
use Omnicado\Tests\TestingDatabase;
use PHPUnit\Event\Test\Errored;
use PHPUnit\Event\Test\ErroredSubscriber;
use PHPUnit\Event\Test\Finished as TestFinishedEvent;
use PHPUnit\Event\Test\FinishedSubscriber as TestFinishedSubscriber;
use PHPUnit\Event\Test\PreparationStarted as TestStartedEvent;
use PHPUnit\Event\Test\PreparationStartedSubscriber as TestStartedSubscriber;
use PHPUnit\Event\Test\Skipped;
use PHPUnit\Event\Test\SkippedSubscriber;
use PHPUnit\Event\TestRunner\Started as TestRunnerStartedEvent;
use PHPUnit\Event\TestRunner\StartedSubscriber as TestRunnerStartedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

class TestingDatabaseExtension implements Extension
{
    /** @var bool */
    public static $transactionStarted = false;

    public static function isRunningUnitTestOnly(): bool
    {
        // TODO
        return false;
    }

    public static function rollBack(): void
    {
        if (!self::$transactionStarted) {
            return;
        }

        Bootstrap::getTestsContainer()
            ->getByType(EntityManagerInterface::class)
            ->rollback();

        self::$transactionStarted = false;
    }

    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $facade->registerSubscriber(new class () implements TestRunnerStartedSubscriber {
            public function notify(TestRunnerStartedEvent $event): void
            {
                $cacheFilePath = __DIR__ . '/../.database.cache';
                $currentDatabaseHash = TestingDatabase::calculateDirectoriesHash(
                    __DIR__ . '/../../migrations',
                    __DIR__ . '/../DataFixtures',
                );

                // Skip database bootstrapping if running unit test(s)
                if (
                    TestingDatabaseExtension::isRunningUnitTestOnly() === false
                    && TestingDatabase::isCacheUpToDate($cacheFilePath, $currentDatabaseHash) === false
                ) {
                    TestingDatabase::init(
                        Bootstrap::getTestsContainer(),
                    );
                    \Safe\file_put_contents($cacheFilePath, $currentDatabaseHash);
                }
            }
        });

        $facade->registerSubscriber(new class () implements TestStartedSubscriber {
            public function notify(TestStartedEvent $event): void
            {
                Bootstrap::getTestsContainer()
                    ->getByType(EntityManagerInterface::class)
                    ->beginTransaction();
                TestingDatabaseExtension::$transactionStarted = true;
            }
        });

        $facade->registerSubscriber(new class () implements SkippedSubscriber {
            public function notify(Skipped $event): void
            {
                // this is a workaround to allow skipping tests within the setUp() method
                // as for those cases there is no Finished event
                TestingDatabaseExtension::rollBack();
            }
        });

        $facade->registerSubscriber(new class () implements TestFinishedSubscriber {
            public function notify(TestFinishedEvent $event): void
            {
                TestingDatabaseExtension::rollBack();
            }
        });

        $facade->registerSubscriber(new class () implements ErroredSubscriber {
            public function notify(Errored $event): void
            {
                // needed as for errored tests the "Finished" event is not triggered
                TestingDatabaseExtension::rollBack();
            }
        });
    }
}
