<?php

declare(strict_types=1);

// The checks are not weakened to make a change pass: no error of PHPStan is ignored, no test is skipped, and the
// configuration of PHPStan and PHP CS Fixer keeps the exclusions it has. A change to these lists is a change to the
// checks themselves, never ordinary work.

$root = dirname(__DIR__, 2);

/**
 * The PHP files the application and its tests consist of, but for the fixtures, which hold violations on purpose.
 *
 * @return list<string>
 */
$phpFiles = static function () use ($root): array {
    $files = [];
    foreach (['src', 'tests', 'tools', 'config', 'bin'] as $folder) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            "{$root}/{$folder}",
            FilesystemIterator::SKIP_DOTS
        ));
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo
                && $file->getExtension() === 'php'
                && !str_contains($file->getPathname(), '/tests/Fixtures/')
                && $file->getPathname() !== __FILE__
            ) {
                $files[] = $file->getPathname();
            }
        }
    }

    return $files;
};

it('ignores no error of PHPStan in the code', function () use ($phpFiles, $root): void {
    $ignoring = array_values(array_filter(
        $phpFiles(),
        static fn (string $file): bool => 1 === preg_match(
            '/@phpstan-ignore|@phpstan-ignore-line|@phpstan-ignore-next-line/',
            (string) file_get_contents($file)
        )
    ));

    expect(array_map(static fn (string $file): string => substr($file, strlen($root) + 1), $ignoring))->toBe([]);
});

it('skips no test', function () use ($phpFiles, $root): void {
    $skipping = array_values(array_filter(
        $phpFiles(),
        static fn (string $file): bool => str_contains($file, '/tests/')
            && 1 === preg_match(
                '/markTestSkipped|markTestIncomplete|->skip\(|->todo\(/',
                (string) file_get_contents($file)
            )
    ));

    expect(array_map(static fn (string $file): string => substr($file, strlen($root) + 1), $skipping))->toBe([]);
});

it('keeps PHPStan without ignored errors or a baseline, and with only its own exclusions', function () use ($root): void {
    $neon = (string) file_get_contents("{$root}/phpstan.dist.neon");

    expect($neon)->not->toMatch('/ignoreErrors|baseline|reportUnmatchedIgnoredErrors|^includes:/m');

    preg_match('/excludePaths:\s*\n\s*analyse:\s*\n((?:\s+- .+\n)+)/', $neon, $matches);
    expect(array_map('trim', explode("\n", trim($matches[1] ?? ''))))->toBe([
        '- tests/Architecture/*',
        '- tests/Fixtures/*',
        '- config/reference.php',
    ]);

    preg_match('/^    level: (\d+)$/m', $neon, $level);
    expect($level[1] ?? null)->toBe('10');
});

it('keeps PHP CS Fixer with only its own exclusions', function () use ($root): void {
    preg_match_all(
        '/->exclude\(\'([^\']+)\'\)/',
        (string) file_get_contents("{$root}/.php-cs-fixer.dist.php"),
        $matches
    );

    expect($matches[1])->toBe(['var', 'Fixtures']);
});

it('keeps the dependency analyser with only its own exclusions and ignores', function () use ($root): void {
    $configuration = (string) file_get_contents("{$root}/composer-dependency-analyser.php");

    preg_match_all(
        '/->(addPathToExclude|addPathsToExclude|ignoreErrorsOnPackage|ignoreErrorsOnPath|ignoreErrorsOnExtension|ignoreUnknownClasses|ignoreUnknownFunctions|disableComposerAutoloadPathScan|ignoreErrors)\(([^)]*)\)/',
        $configuration,
        $matches,
        PREG_SET_ORDER
    );
    expect(array_map(
        static fn (array $match): string => $match[1] . '(' . preg_replace('/\s+/', ' ', $match[2]) . ')',
        $matches
    ))->toBe([
        "addPathToExclude(__DIR__ . '/tests/Fixtures')",
        "ignoreErrorsOnPackage('symfony/runtime', [ErrorType::UNUSED_DEPENDENCY])",
        "ignoreErrorsOnPackage('symfony/yaml', [ErrorType::UNUSED_DEPENDENCY])",
        "ignoreErrorsOnPackage('symfony/dotenv', [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV])",
    ]);
});
