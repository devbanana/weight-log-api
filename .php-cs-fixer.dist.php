<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = new Finder()
    ->in(__DIR__)
    ->exclude('var')
    ->exclude('config/secrets')
    ->ignoreDotFiles(false)
;

return new Config()
    ->setRules([
        '@auto' => true,
        '@auto:risky' => true,
        '@autoPHPUnitMigration:risky' => true,
        '@PhpCsFixer' => true,
        '@PhpCsFixer:risky' => true,
        'attribute_empty_parentheses' => true,
        'concat_space' => [
            'spacing' => 'one',
        ],
        'date_time_immutable' => true,
        'final_class' => true,
        'final_public_method_for_abstract_class' => true,
        'mb_str_functions' => true,
        'modernize_strpos' => true,
        'native_function_invocation' => [
            'include' => [],
            'strict' => true,
        ],
        'numeric_literal_separator' => true,
        'ordered_class_elements' => [
            'order' => [
                'use_trait',
                'case',
                'constant_public',
                'constant_protected',
                'constant_private',
                'property_public',
                'property_protected',
                'property_private',
                'construct',
                'destruct',
                'phpunit',
                'method_public',
                'method_protected',
                'method_private',
            ],
        ],
        'ordered_interfaces' => true,
        'phpdoc_line_span' => true,
        'phpdoc_types_order' => [
            'null_adjustment' => 'always_last',
        ],
        'php_unit_attributes' => true,
        'regular_callable_call' => true,
        'simplified_if_return' => true,
        'single_line_empty_body' => false,
        'static_private_method' => true,
        'stringable_for_to_string' => true,
        'trailing_comma_in_multiline' => [
            'after_heredoc' => true,
            'elements' => ['arguments', 'array_destructuring', 'arrays', 'match', 'parameters'],
        ],
        'yoda_style' => false,
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder)
;
