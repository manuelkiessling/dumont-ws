<?php

declare(strict_types=1);

use App\Tools\PhpCsFixer\Fixers\ArgumentsOneBelowTheOtherFixer;
use App\Tools\PhpCsFixer\Fixers\AttributeArgumentsInParameterOrderFixer;
use ErickSkrauch\PhpCsFixer\Fixers;

$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__ . DIRECTORY_SEPARATOR . 'src',
        __DIR__ . DIRECTORY_SEPARATOR . 'tests',
        __DIR__ . DIRECTORY_SEPARATOR . 'config',
        __DIR__ . DIRECTORY_SEPARATOR . 'tools',
    ])
    ->exclude('var')
    ->exclude('Fixtures')
;

return (new PhpCsFixer\Config())
    ->registerCustomFixers(new Fixers())
    // The project's own fixers, in tools/PhpCsFixer/Fixers
    ->registerCustomFixers([new AttributeArgumentsInParameterOrderFixer(), new ArgumentsOneBelowTheOtherFixer()])
    ->setRules([
        '@Symfony'                                   => true,
        '@PHP8x5Migration'                           => true,
        'ErickSkrauch/align_multiline_parameters'    => true,
        'ErickSkrauch/blank_line_before_return'      => true,
        'ErickSkrauch/multiline_if_statement_braces' => true,
        'concat_space'                               => [
            'spacing' => 'one'
        ],
        'class_attributes_separation' => [
            'elements' => [
                'method' => 'one',
                'const'  => 'none'
            ],
        ],
        'cast_spaces'                 => false,
        'yoda_style'                  => false,
        'trailing_comma_in_multiline' => false,
        'braces_position'             => [
            'classes_opening_brace'   => 'next_line_unless_newline_at_signature_end',
            'functions_opening_brace' => 'next_line_unless_newline_at_signature_end'
        ],
        'fully_qualified_strict_types' => [
            'import_symbols' => true
        ],
        'global_namespace_import' => [
            'import_classes'   => true,
            'import_constants' => true,
            'import_functions' => true
        ],
        'binary_operator_spaces' => [
            'default' => 'align_single_space_minimal',
        ],
        'single_line_throw'                              => false,
        'App/attribute_arguments_in_parameter_order' => true,
        'App/arguments_one_below_the_other'          => true,
    ])
    ->setFinder($finder)
;
