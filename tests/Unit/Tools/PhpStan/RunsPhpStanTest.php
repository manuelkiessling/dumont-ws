<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tools\PhpStan;

use PHPUnit\Framework\TestCase;

use function array_reverse;
use function bin2hex;
use function count;
use function dirname;
use function file_put_contents;
use function glob;
use function is_dir;
use function is_file;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

/**
 * A rule's test leaves nothing behind: the configuration it writes for its PHPStan run is a temporary file, and every
 * run of the tests would add one.
 */
final class RunsPhpStanTest extends TestCase
{
    use RunsPhpStan;

    public function testARunOfPhpStanLeavesNoTemporaryFileBehind(): void
    {
        $left   = static fn (): int => count(glob(sys_get_temp_dir() . '/' . self::temporaryFilePrefix() . '*') ?: []);
        $before = $left();

        $this->phpStanErrors(
            '',
            dirname(__DIR__, 3) . '/Fixtures/PhpStan/DomainNamesNoInfrastructure/AlphaFileStore.php'
        );

        $this->assertSame($before, $left());
    }

    /**
     * After a run, PHPStan removes every container in its cache directory that its journal does not name. In a
     * directory the workers of a parallel test run share, it would remove one that another worker has just made and is
     * about to load, and that worker's PHPStan then fails with "Unable to include".
     */
    public function testARunOfPhpStanLeavesTheContainerOfAnotherRunAlone(): void
    {
        // PHPStan's own default directory, which every run without a directory of its own uses
        $root   = sys_get_temp_dir() . '/phpstan';
        $shared = $root . '/cache/nette.configurator';
        // What the test makes of the directory, it removes again
        $made = [];
        foreach ([$root, $root . '/cache', $shared] as $directory) {
            if (!is_dir($directory) && mkdir($directory)) {
                $made[] = $directory;
            }
        }
        $journal = $shared . '/container.journal';
        // Without a journal, PHPStan starts one and removes nothing
        $started = !is_file($journal) && false !== file_put_contents($journal, '');
        $another = $shared . '/Container_' . bin2hex(random_bytes(5)) . '.php';
        file_put_contents($another, "<?php\n");

        try {
            $this->phpStanErrors(
                '',
                dirname(__DIR__, 3) . '/Fixtures/PhpStan/DomainNamesNoInfrastructure/AlphaFileStore.php'
            );

            $this->assertFileExists($another);
        } finally {
            if (is_file($another)) {
                unlink($another);
            }
            if ($started) {
                unlink($journal);
            }
            foreach (array_reverse($made) as $directory) {
                rmdir($directory);
            }
        }
    }
}
