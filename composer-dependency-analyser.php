<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

// Every package the application's code uses is declared in composer.json, not merely installed because another
// package needs it: a package that is only there through another one can go with that one's next update.

return new Configuration()
    // The fixtures of the project's own PHPStan rules use made-up classes on purpose
    ->addPathToExclude(__DIR__ . '/tests/Fixtures')
    // Used where no code names them: the runtime starts bin/console and loads .env through symfony/dotenv, and the
    // container reads config/*.yaml through symfony/yaml
    ->ignoreErrorsOnPackage('symfony/runtime', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('symfony/yaml', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('symfony/dotenv', [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV]);
