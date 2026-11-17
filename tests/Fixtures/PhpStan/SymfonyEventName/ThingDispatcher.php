<?php

declare(strict_types=1);

namespace App\Alpha\Domain\Service;

use App\Alpha\Domain\Message\ThingRenamed;
use App\Alpha\Facade\SymfonyEvent\ThingCreatedSymfonyEvent;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use stdClass;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class ThingDispatcher
{
    public function __construct(
        private EventDispatcherInterface $symfonyDispatcher,
        private PsrEventDispatcherInterface $psrDispatcher,
        private ThingQueue $queue
    ) {
    }

    public function dispatch(object $anyEvent): void
    {
        $this->symfonyDispatcher->dispatch(new ThingCreatedSymfonyEvent('thing'));
        $this->symfonyDispatcher->dispatch(new stdClass());
        $this->symfonyDispatcher->dispatch($anyEvent);
        $this->queue->dispatch(new ThingRenamed());

        $this->symfonyDispatcher->dispatch(new ThingRenamed());
        $this->psrDispatcher->dispatch(new ThingRenamed());
    }
}
