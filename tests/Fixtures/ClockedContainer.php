<?php

declare(strict_types=1);

namespace Marko\ErrorsAdvanced\Tests\Fixtures;

use Marko\Core\Container\Container;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Testing\Fake\FakeClock;
use Psr\Clock\ClockInterface;

/**
 * A bare container with the ClockInterface that marko/clock's module would bind.
 */
class ClockedContainer
{
    public static function create(): Container
    {
        $container = new Container(new PreferenceRegistry());
        $container->instance(ClockInterface::class, new FakeClock());

        return $container;
    }
}
