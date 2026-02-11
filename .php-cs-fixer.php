<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->name('*.php')
    ->notPath('vendor')
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12'                     => true,
        '@PHP84Migration'            => true,
        'strict_param'               => true,
        'declare_strict_types'       => true,
        'array_syntax'               => ['syntax' => 'short'],
        'no_unused_imports'          => true,
        'ordered_imports'            => ['sort_algorithm' => 'alpha'],
        'single_quote'               => true,
        'trailing_comma_in_multiline' => ['elements' => ['arguments', 'arrays', 'match', 'parameters']],
        'concat_space'               => ['spacing' => 'one'],
        'binary_operator_spaces'     => ['default' => 'single_space'],
        'blank_line_before_statement' => ['statements' => ['return', 'throw', 'try']],
        'no_extra_blank_lines'       => ['tokens' => ['extra', 'use']],
        'class_attributes_separation' => ['elements' => ['method' => 'one']],
        'visibility_required'        => ['elements' => ['property', 'method', 'const']],
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true],
        'phpdoc_align'               => ['align' => 'vertical'],
        'phpdoc_order'               => true,
        'phpdoc_trim'                => true,
        'phpdoc_types_order'         => ['null_adjustment' => 'always_last'],
        'global_namespace_import'    => ['import_classes' => true, 'import_functions' => false, 'import_constants' => false],
    ])
    ->setFinder($finder)
;
