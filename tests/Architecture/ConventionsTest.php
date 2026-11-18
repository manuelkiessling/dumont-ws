<?php

declare(strict_types=1);

use Symfony\Component\Console\Attribute\AsCommand;

// The conventions of the books that a look at the files and classes can hold: what each vertical declares, how its
// classes are made, which tests it has, and that the README and the archbook know it (see docs/archbook.md and
// docs/devbook.md).

$root = dirname(__DIR__, 2);

/** @return list<string> the verticals under src/, but for Common */
$verticals = static fn (): array => array_values(array_filter(
    array_map('basename', glob("{$root}/src/*", GLOB_ONLYDIR) ?: []),
    static fn (string $vertical): bool => $vertical !== 'Common'
));

/** @return list<class-string> the classes, interfaces and enums of a folder under src/, by their PSR-4 names */
$classesIn = static function (string $folder) use ($root): array {
    $directory = "{$root}/src/{$folder}";
    if (!is_dir($directory)) {
        return [];
    }

    $classes = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
        $directory,
        FilesystemIterator::SKIP_DOTS
    )) as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
            $relative  = substr($file->getPathname(), strlen("{$root}/src/"), -4);
            $classes[] = 'App\\' . str_replace('/', '\\', $relative);
        }
    }
    sort($classes);

    return $classes;
};

/** @return array<string, list<string>> each vertical's declared dependencies, from phpstan.dist.neon */
$declaredDependencies = static function () use ($root): array {
    preg_match_all(
        "/^\\s+- '(\\w+) ->([\\w, ]*)'\\s*$/m",
        (string) file_get_contents("{$root}/phpstan.dist.neon"),
        $matches,
        PREG_SET_ORDER
    );

    $dependencies = [];
    foreach ($matches as [, $vertical, $targets]) {
        $dependencies[$vertical] = array_values(array_filter(array_map('trim', explode(',', $targets))));
    }

    return $dependencies;
};

it('declares the dependencies of every vertical, on verticals that exist', function () use (
    $verticals,
    $declaredDependencies
): void {
    $declared = $declaredDependencies();

    $missing = array_values(array_diff($verticals(), array_keys($declared)));
    expect($missing)->toBe(
        [],
        'Every vertical is listed in phpstan.dist.neon\'s allowedDependencies, as \'<Vertical> -> <Vertical>, …\' or \'<Vertical> ->\'.'
    );

    foreach ($declared as $vertical => $targets) {
        expect(in_array(
            $vertical,
            $verticals(),
            true
        ))->toBeTrue("phpstan.dist.neon declares {$vertical}, which is no vertical under src/.");
        foreach ($targets as $target) {
            expect(in_array(
                $target,
                $verticals(),
                true
            ))->toBeTrue("{$vertical} is declared to depend on {$target}, which is no vertical under src/.");
        }
    }
});

it('declares dependencies between verticals without a cycle', function () use ($declaredDependencies): void {
    $declared = $declaredDependencies();
    $state    = [];
    $cycle    = null;

    $visit = static function (string $vertical, array $path) use (
        &$visit,
        &$state,
        &$cycle,
        $declared
    ): void {
        if ($cycle !== null || ($state[$vertical] ?? null) === 'done') {
            return;
        }
        if (($state[$vertical] ?? null) === 'visiting') {
            $cycle = implode(' -> ', [...$path, $vertical]);

            return;
        }
        $state[$vertical] = 'visiting';
        foreach ($declared[$vertical] ?? [] as $target) {
            $visit($target, [...$path, $vertical]);
        }
        $state[$vertical] = 'done';
    };

    foreach (array_keys($declared) as $vertical) {
        $visit($vertical, []);
    }

    expect($cycle)->toBeNull("The declared dependencies form a cycle: {$cycle}.");
});

it('makes every refusal a child of its vertical\'s abstract refusal', function () use ($verticals, $classesIn): void {
    expect($verticals())->not->toBeEmpty();
    foreach ($verticals() as $vertical) {
        $base = "App\\{$vertical}\\Domain\\Exception\\{$vertical}RefusedException";
        foreach ($classesIn("{$vertical}/Domain/Exception") as $exception) {
            if ($exception === $base) {
                expect(new ReflectionClass($base)->isAbstract())->toBeTrue("{$base} is abstract.");

                continue;
            }
            expect(is_subclass_of($exception, $base))->toBeTrue("{$exception} extends {$base}.");
        }
    }
});

it('makes every DTO of a facade final and readonly', function () use ($verticals, $classesIn): void {
    expect($verticals())->not->toBeEmpty();
    foreach ($verticals() as $vertical) {
        foreach ($classesIn("{$vertical}/Facade/Dto") as $dto) {
            $class = new ReflectionClass($dto);
            expect($class->isFinal() && $class->isReadOnly())->toBeTrue("{$dto} is final and readonly.");
        }
    }
});

it('builds every screen and every command after the same pattern', function () use ($verticals, $classesIn): void {
    expect($verticals())->not->toBeEmpty();
    foreach ($verticals() as $vertical) {
        foreach ($classesIn("{$vertical}/Presentation/Tui") as $screen) {
            expect($screen)->toEndWith('Screen');
            $class = new ReflectionClass($screen);
            expect($class->hasMethod('createTui') && $class->getMethod('createTui')->isStatic())
                ->toBeTrue("{$screen} has a static createTui().");
            expect($class->hasMethod('mount'))->toBeTrue("{$screen} has mount().");
        }
        foreach ($classesIn("{$vertical}/Presentation/Command") as $command) {
            expect($command)->toEndWith('Command');
            $attributes = new ReflectionClass($command)->getAttributes(AsCommand::class);
            expect($attributes)->toHaveCount(1, "{$command} is an #[AsCommand].");
            expect($attributes[0]->newInstance()->name)->toStartWith('app:');
        }
    }
});

it('tests every vertical on every level, and every domain service and screen on its own', function () use (
    $root,
    $verticals,
    $classesIn
): void {
    foreach ($verticals() as $vertical) {
        expect(is_dir("{$root}/tests/Unit/{$vertical}"))->toBeTrue("tests/Unit/{$vertical} exists.");
        expect(is_dir("{$root}/tests/Integration/{$vertical}"))->toBeTrue("tests/Integration/{$vertical} exists.");

        $tested = [
            ...array_filter(
                $classesIn("{$vertical}/Domain/Service"),
                static fn (string $class): bool => str_ends_with($class, 'DomainService')
            ),
            ...$classesIn("{$vertical}/Presentation/Tui"),
        ];
        foreach ($tested as $class) {
            $test = "tests/Unit/{$vertical}/" . substr((string) strrchr($class, '\\'), 1) . 'Test.php';
            expect(is_file("{$root}/{$test}"))->toBeTrue("{$class} is tested in {$test}.");
        }
    }
});

it('tells about every vertical in the archbook and about every command in the README', function () use (
    $root,
    $verticals,
    $classesIn
): void {
    $archbook = (string) file_get_contents("{$root}/docs/archbook.md");
    $readme   = (string) file_get_contents("{$root}/README.md");

    foreach ($verticals() as $vertical) {
        expect($archbook)->toContain("## The {$vertical} vertical");
        foreach ($classesIn("{$vertical}/Presentation/Command") as $command) {
            $name = new ReflectionClass($command)->getAttributes(AsCommand::class)[0]->newInstance()->name;
            expect($readme)->toContain((string) $name);
        }
    }
});
