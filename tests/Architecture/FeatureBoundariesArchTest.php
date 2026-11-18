<?php

declare(strict_types=1);

$features = array_map(
    static fn (string $path): string => basename($path),
    glob(__DIR__ . '/../../src/*', GLOB_ONLYDIR)
);

// Common is a shared vertical for cross-cutting concerns and can be used by other verticals
$sharedVerticals = ['Common'];

foreach ($features as $from) {
    foreach ($features as $to) {
        if ($from === $to) {
            continue;
        }

        // Skip rules where the target is a shared vertical that can be used by others
        if (in_array($to, $sharedVerticals, true)) {
            continue;
        }

        arch("{$from} must not use {$to} internals")
            ->expect("App\\{$from}")
            ->classes()
            ->not->toUse([
                "App\\{$to}\\Api",
                "App\\{$to}\\Domain",
                "App\\{$to}\\Infrastructure",
                "App\\{$to}\\Presentation",
                "App\\{$to}\\TestHarness",
            ]);
    }
}

// Common is used by every vertical, and uses none: not even a vertical's facade
foreach ($features as $vertical) {
    if (in_array($vertical, $sharedVerticals, true)) {
        continue;
    }

    arch("Common must not use {$vertical}")
        ->expect('App\Common')
        ->not->toUse("App\\{$vertical}");
}

// A vertical's unit tests use another vertical only as the application does: through its facade, or a stand-in for
// the facade's interface. Integration tests may wire the real services of several verticals together.
foreach ($features as $from) {
    foreach ($features as $to) {
        if ($from === $to || in_array($to, $sharedVerticals, true)) {
            continue;
        }

        arch("the unit tests of {$from} must not use {$to} internals")
            ->expect("App\\Tests\\Unit\\{$from}")
            ->not->toUse([
                "App\\{$to}\\Api",
                "App\\{$to}\\Domain",
                "App\\{$to}\\Infrastructure",
                "App\\{$to}\\Presentation",
                "App\\{$to}\\TestHarness",
            ]);
    }
}
