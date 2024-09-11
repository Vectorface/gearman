<?php

/**
 * List of rules to be enforced by PHP-CS-Fixer
 * @see https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/master/doc/rules/index.rst
 * @see https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/master/doc/ruleSets/index.rst
 */
$rules = [
    '@PER-CS2.0' => true,                                   // Enforce PER 2.0
    'array_indentation' => true,                            // Indent arrays by one
    'array_syntax' => true,                                 // Replace array() with []
    'binary_operator_spaces' => [
        'default' => 'single_space',                        // Use single space around operators
        'operators' => [
            '=>' => 'align_single_space_minimal',           // Align => inside array
        ],
    ],
    'cast_spaces' => [
        'space' => 'none',                                  // Remove spaces between cast and value
    ],
    'combine_consecutive_issets' => true,                   // Combine isset() into one call
    'dir_constant' => true,                                 // Replace dirname(__FILE__) with __DIR__
    'echo_tag_syntax' => ['format' => 'short'],             // Replace <?= with <?php echo
    'explicit_string_variable' => true,                     // Convert vars in strings from "$foo" to "{$foo}"
    'function_declaration' => [
        'closure_function_spacing' => 'none',               // No space between function keyword and parameter list
        'closure_fn_spacing' => 'none',                     // No space between fn keyword and parameter list
    ],
    'heredoc_indentation' => true,                          // Indent heredocs by one
    'include' => true,                                      // Use single space after include/require, remove brackets
    'is_null' => true,                                      // Replace is_null() with $foo === null
    'list_syntax' => true,                                  // Replace list() with []
    'magic_constant_casing' => true,                        // Use uppercase for magic constants
    'magic_method_casing' => true,                          // Use camelCase for magic methods
    'method_chaining_indentation' => true,                  // Indent method chains by one
    'method_argument_space' => [
        'on_multiline' => 'ignore',                         // Do not force all function arguments to be on separate lines
    ],
    'multiline_whitespace_before_semicolons' => true,       // Place semicolon on same line as last chained method
    'native_function_casing' => true,                       // Use lowercase for native functions
    'native_type_declaration_casing' => true,               // Use lowercase for native type hints
    'new_with_parentheses' => false,                        // Do not enforce empty parentheses for constructors
    'no_blank_lines_after_phpdoc' => true,                  // Remove whitespace between docblock and element
    'no_empty_statement' => true,                           // Remove useless statements/semicolons
    'no_singleline_whitespace_before_semicolons' => true,   // Remove whitespace between statement and semicolon
    'no_spaces_around_offset' => true,                      // Remove spaces around array offset
    'no_trailing_comma_in_singleline' => [
        'elements' => ['arguments', 'array', 'group_import']  // Remove trailing commas in singleline arrays, function calls, and group imports
    ],
    'no_unneeded_control_parentheses' => [
        'statements' => ['return'],                         // Remove parentheses around return statements
    ],
    'no_unused_imports' => true,                            // Remove unused imports
    'no_useless_return' => true,                            // Remove empty return at end of function
    'no_whitespace_before_comma_in_array' => true,          // Remove whitespace before commas in arrays
    'not_operator_with_successor_space' => false,           // Remove space around NOT operator
    'nullable_type_declaration_for_default_null_value' => true, // Add ? before typehint for nullable default values
    'operator_linebreak' => true,                           // Place multiline operators at the start of a line
    'ordered_imports' => [
        'sort_algorithm' => 'alpha',                        // Sort imports alphabetically
    ],
    'phpdoc_indent' => true,                                // Align PHPDoc with element
    'php_unit_construct' => true,                           // Replace assertEquals(true, $foo) with assertTrue($foo)
    'semicolon_after_instruction' => false,                 // Use semicolon after instruction (disabled to avoid <?= adding a semicolon)
    'simple_to_complex_string_variable' => true,            // Convert vars in strings from ${foo} to {$foo}
    'simplified_if_return' => true,                         // Simplify return inside if statement to boolean type cast
    'single_line_comment_style' => [
        'comment_types' => ['hash'],                        // Use // for # comments
    ],
    'single_space_around_construct' => true,                // Use single space around constructs
    'standardize_not_equals' => true,                       // Replace <> with !=
    'ternary_to_null_coalescing' => true,                   // Replace isset($foo) ? $foo : $bar with $foo ?? $bar
    'trailing_comma_in_multiline' => true,                  // Use trailing comma in multiline arrays
    'trim_array_spaces' => true,                            // Remove leading/trailing spaces in arrays
    'type_declaration_spaces' => true,                      // Use single space between typehints and fn argument/object property name
    'visibility_required' => [
        'elements' => ['property', 'method'],               // Require visibility to be declared on properties and methods
    ],
    'whitespace_after_comma_in_array' => true,              // Use whitespace after every comma in arrays
    'yoda_style' => [                                       // Enforces non-yoda style
        'equal' => false,
        'identical' => false,
        'less_and_greater' => false,
    ],
];

$excludes = [
    'vendor',
    'node_modules',
];

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules($rules)
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->exclude($excludes)
            ->notName('*.js')
            ->notName('*.css')
            ->notName('*.md')
            ->notName('*.xml')
            ->notName('*.yml')
            ->notName('*.tpl.php')
    );
