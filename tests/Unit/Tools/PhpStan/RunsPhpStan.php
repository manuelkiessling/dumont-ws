<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tools\PhpStan;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Process\Process;

use function is_array;
use function is_dir;
use function is_int;
use function is_string;
use function rmdir;
use function unlink;

/**
 * Runs PHPStan in a process of its own, as `mise run quality` does, on a fixture file or directory, with nothing but
 * the given rule configuration: level 0, and the ergebnis rules, which the extension installer would add, switched off.
 */
trait RunsPhpStan
{
    /**
     * What the names of the temporary files of this process's runs start with: the workers of a parallel test run
     * write theirs into the same directory at the same time.
     */
    private static function temporaryFilePrefix(): string
    {
        return 'phpstan-rule-test-' . getmypid() . '-';
    }

    /**
     * The errors PHPStan reports, each as "<file name>:<line> <message>", sorted by file and line.
     *
     * @return list<string>
     */
    private function phpStanErrors(string $ruleConfiguration, string $fixturePath): array
    {
        $root = dirname(__DIR__, 4);
        // tempnam() makes the file it names; PHPStan reads its configuration only from a file that ends in ".neon"
        $reserved = (string) tempnam(sys_get_temp_dir(), self::temporaryFilePrefix());
        $neon     = $reserved . '.neon';
        // A directory of the run's own for PHPStan's cache: after a run, PHPStan removes each container of its directory
        // that it does not know, and in a directory the workers share, one that another worker is about to load
        $cache = $reserved . '.cache';
        file_put_contents(
            $neon,
            "parameters:\n    level: 0\n    tmpDir: {$cache}\n    paths:\n        - {$fixturePath}\n    ergebnis:\n        allRules: false\n" . $ruleConfiguration
        );

        $process = new Process(
            [$root . '/vendor/bin/phpstan', 'analyse', '--no-progress', '--error-format=json', '-c', $neon],
            $root
        );

        try {
            $process->run();
        } finally {
            unlink($neon);
            unlink($reserved);
            self::remove($cache);
        }

        $decoded = json_decode($process->getOutput(), true);
        if (!is_array($decoded) || !is_array($decoded['files'] ?? null)) {
            $this->fail("PHPStan did not report as JSON.\nstdout:\n{$process->getOutput()}\nstderr:\n{$process->getErrorOutput()}");
        }

        $errors = [];
        foreach ($decoded['files'] as $file => $report) {
            foreach (is_array($report) && is_array($report['messages'] ?? null) ? $report['messages'] : [] as $message) {
                if (is_array($message) && is_string($message['message'] ?? null) && is_int($message['line'] ?? null)) {
                    $errors[] = [basename((string) $file), $message['line'], $message['message']];
                }
            }
        }
        foreach (is_array($decoded['errors'] ?? null) ? $decoded['errors'] : [] as $generalError) {
            $this->fail('PHPStan failed: ' . (is_string($generalError) ? $generalError : ''));
        }

        usort($errors, static fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return array_map(static fn (array $error): string => "{$error[0]}:{$error[1]} {$error[2]}", $errors);
    }

    /**
     * Removes a directory with everything in it, where it exists.
     */
    private static function remove(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($entries as $entry) {
            if (!$entry instanceof SplFileInfo) {
                continue;
            }
            if ($entry->isDir() && !$entry->isLink()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($directory);
    }
}
