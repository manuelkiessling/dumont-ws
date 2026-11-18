<?php

declare(strict_types=1);

namespace App\Tests\Integration\Common;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * What config/services.yaml must hold for every vertical (see docs/archbook.md, "Infrastructure" and "Verticals working
 * together"): the tests keep their data apart from the application's, and data classes are no services.
 */
final class ConfigurationTest extends TestCase
{
    public function testEveryDataFileOfTheTestsLiesApartFromTheApplicationsData(): void
    {
        $application = self::declared('dev')->getParameterBag()->all();
        $tested      = self::declared('test')->getParameterBag()->all();

        $files = array_values(array_filter(
            array_keys($application),
            static fn (string $name): bool => str_starts_with($name, 'app.') && str_ends_with($name, '_file')
        ));
        self::assertNotSame([], $files);

        foreach ($files as $name) {
            self::assertIsString($application[$name]);
            self::assertIsString($tested[$name] ?? null, "{$name} has a value in the test environment.");
            self::assertStringContainsString('/var/data/', $application[$name], "{$name} lies under var/data/.");
            self::assertStringContainsString(
                '/var/test/',
                $tested[$name],
                "{$name} lies under var/test/ in the tests."
            );
        }
    }

    public function testNoModelRefusalDtoOrEventIsAService(): void
    {
        $dataClasses = [];
        foreach (self::declared('test')->getDefinitions() as $id => $definition) {
            $class = $definition->getClass() ?? $id;
            if (1 === preg_match(
                '/\AApp\\\\[^\\\\]+\\\\(Domain\\\\(Model|Exception)|Facade\\\\(Dto|SymfonyEvent))\\\\/',
                $class
            )) {
                $dataClasses[] = $class;
            }
        }

        self::assertSame(
            [],
            $dataClasses,
            'Models, refusals, DTOs and events are data, not services: exclude their folders in config/services.yaml.'
        );
    }

    /**
     * The parameters and services as config/services.yaml declares them for an environment, before the compiler
     * removes the services that nothing uses.
     */
    private static function declared(string $environment): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', dirname(__DIR__, 3));
        new YamlFileLoader(
            $container,
            new FileLocator(dirname(__DIR__, 3) . '/config'),
            $environment
        )->load('services.yaml');

        return $container;
    }
}
