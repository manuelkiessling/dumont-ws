<?php

declare(strict_types=1);

namespace App\Alpha\Domain\Service;

/**
 * Has a dispatch() method, but is no event dispatcher.
 */
final class ThingQueue
{
    public function dispatch(object $thing): void
    {
    }
}
