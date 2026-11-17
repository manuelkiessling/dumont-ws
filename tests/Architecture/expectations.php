<?php

declare(strict_types=1);

use Pest\Arch\Contracts\ArchExpectation;
use Pest\Arch\Expectations\Targeted;
use Pest\Arch\Support\FileLineFinder;
use PHPUnit\Architecture\Elements\ObjectDescription;

// Expectations the architecture tests share; each test file requires this file once.

/*
 * Pest's toUse() looks a dependency up through Composer's PSR-4 prefixes and parses every file under it, which fails
 * for whole vendor namespaces (loading Symfony's DBAL 3 debug middleware under DBAL 4 is fatal). This expectation
 * looks at the target classes only: none of the names a class uses may match one of the patterns, unless it is
 * exactly one of the allowed class names. The allow-list is compared exactly, not as a prefix as Pest's ignoring()
 * compares, so that allowing EventType does not allow EventTypeRegistry.
 */
expect()->extend('toUseNothingMatching', function (
    array  $patterns,
    array  $allowed,
    string $description
): ArchExpectation {
    return Targeted::make(
        $this,
        static function (ObjectDescription $object) use ($patterns, $allowed): bool {
            foreach ($object->uses as $use) {
                if (in_array($use, $allowed, true)) {
                    continue;
                }

                foreach ($patterns as $pattern) {
                    if (1 === preg_match($pattern, $use)) {
                        return false;
                    }
                }
            }

            return true;
        },
        'to use ' . $description,
        FileLineFinder::where(static fn (string $line): bool => str_contains($line, 'class '))
    );
});
