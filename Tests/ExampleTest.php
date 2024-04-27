<?php

declare(strict_types=1);

namespace Tests;

use Doctrine\ORM\EntityManagerInterface;
use Omnicado\Bootstrap;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function testGetMostRecentStockQuantities(): void
    {
        $container = Bootstrap::getTestsContainer();

        $repository = $container->getByType(SomeRepository::class);

        $this->assertCount(0, $repository->countSomething());

        $repository->save(new SomeEntity());

        $this->assertCount(1, $repository->countSomething());
    }
}
