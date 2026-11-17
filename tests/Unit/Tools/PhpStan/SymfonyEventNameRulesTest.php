<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tools\PhpStan;

use PHPUnit\Framework\TestCase;

final class SymfonyEventNameRulesTest extends TestCase
{
    use RunsPhpStan;

    private const string CONFIGURATION = <<<'NEON'
        rules:
            - App\Tools\PhpStan\Rules\SymfonyEventClassNameRule
            - App\Tools\PhpStan\Rules\DispatchedSymfonyEventNameRule

        NEON;

    public function testReportsSymfonyEventsByNamespaceByParentAndByDispatchWhoseNameDoesNotEndWithSymfonyEvent(): void
    {
        $this->assertSame(
            [
                'LegacyThingEvent.php:9 The class App\Alpha\Domain\Event\LegacyThingEvent is a Symfony event, as it extends Symfony\Contracts\EventDispatcher\Event, so its name ends with "SymfonyEvent".',
                'ThingDeleted.php:7 The class App\Alpha\Facade\SymfonyEvent\ThingDeleted is a Symfony event, as it is in a SymfonyEvent namespace, so its name ends with "SymfonyEvent".',
                'ThingDispatcher.php:29 The class App\Alpha\Domain\Message\ThingRenamed is dispatched as a Symfony event, so its name ends with "SymfonyEvent".',
                'ThingDispatcher.php:30 The class App\Alpha\Domain\Message\ThingRenamed is dispatched as a Symfony event, so its name ends with "SymfonyEvent".',
            ],
            $this->phpStanErrors(self::CONFIGURATION, dirname(__DIR__, 3) . '/Fixtures/PhpStan/SymfonyEventName')
        );
    }
}
