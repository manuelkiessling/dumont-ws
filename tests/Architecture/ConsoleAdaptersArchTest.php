<?php

declare(strict_types=1);

$verticals = array_map(
    static fn (string $path): string => basename($path),
    glob(__DIR__ . '/../../src/*', GLOB_ONLYDIR)
);

foreach ($verticals as $vertical) {
    arch("{$vertical} business services do not depend on Symfony Console")
        ->expect("App\\{$vertical}\\Domain\\Service")
        ->not->toUse('Symfony\\Component\\Console');
}

it('keeps developer console adapters out of the domain', function (): void {
    expect(glob(__DIR__ . '/../../src/*/Domain/Command/Dev*Command.php'))->toBe([]);
});
