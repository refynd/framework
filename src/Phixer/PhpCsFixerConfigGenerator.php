<?php

declare(strict_types = 1);

namespace Refynd\Phixer;

/**
 * PHP-CS-Fixer Configuration Generator
 *
 * Generates PHP-CS-Fixer configuration tailored for Refynd Framework.
 */
class PhpCsFixerConfigGenerator
{
    public function generate(): string
    {
        return '<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . \'/src\')
    ->in(__DIR__ . \'/tests\')
    ->name(\'*.php\');

$config = new PhpCsFixer\Config();
return $config
    ->setRiskyAllowed(true)
    ->setRules([\'@PSR12\' => true,
        \'@PHP82Migration\' => true,
        \'array_syntax\' => [\'syntax\' => \'short\'],
        \'binary_operator_spaces\' => [\'default\' => \'single_space\'],
        \'blank_line_after_namespace\' => true,
        \'blank_line_after_opening_tag\' => true,
        \'blank_line_before_statement\' => [\'statements\' => [\'return\']],
        \'cast_spaces\' => true,
        \'class_attributes_separation\' => [\'elements\' => [\'method\' => \'one\']],
        \'concat_space\' => [\'spacing\' => \'one\'],
        \'declare_strict_types\' => false,
        \'function_typehint_space\' => true,
        \'include\' => true,
        \'increment_style\' => true,
        \'lowercase_cast\' => true,
        \'magic_constant_casing\' => true,
        \'method_argument_space\' => [\'on_multiline\' => \'ensure_fully_multiline\'],
        \'native_function_casing\' => true,
        \'new_with_braces\' => true,
        \'no_blank_lines_after_class_opening\' => true,
        \'no_blank_lines_after_phpdoc\' => true,
        \'no_empty_phpdoc\' => true,
        \'no_empty_statement\' => true,
        \'no_extra_blank_lines\' => [\'tokens\' => [\'extra\', \'throw\', \'use\']],
        \'no_leading_import_slash\' => true,
        \'no_leading_namespace_whitespace\' => true,
        \'no_mixed_echo_print\' => [\'use\' => \'echo\'],
        \'no_multiline_whitespace_around_double_arrow\' => true,
        \'no_short_bool_cast\' => true,
        \'no_singleline_whitespace_before_semicolons\' => true,
        \'no_spaces_around_offset\' => true,
        \'no_trailing_comma_in_list_call\' => true,
        \'no_trailing_comma_in_singleline_array\' => true,
        \'no_unneeded_control_parentheses\' => true,
        \'no_unused_imports\' => true,
        \'no_whitespace_before_comma_in_array\' => true,
        \'no_whitespace_in_blank_line\' => true,
        \'normalize_index_brace\' => true,
        \'object_operator_without_whitespace\' => true,
        \'ordered_imports\' => [\'sort_algorithm\' => \'alpha\'],
        \'phpdoc_indent\' => true,
        \'phpdoc_inline_tag_normalizer\' => true,
        \'phpdoc_no_access\' => true,
        \'phpdoc_no_package\' => true,
        \'phpdoc_no_useless_inheritdoc\' => true,
        \'phpdoc_scalar\' => true,
        \'phpdoc_single_line_var_spacing\' => true,
        \'phpdoc_summary\' => true,
        \'phpdoc_to_comment\' => true,
        \'phpdoc_trim\' => true,
        \'phpdoc_types\' => true,
        \'phpdoc_var_without_name\' => true,
        \'return_type_declaration\' => true,
        \'semicolon_after_instruction\' => true,
        \'short_scalar_cast\' => true,
        \'single_blank_line_before_namespace\' => true,
        \'single_class_element_per_statement\' => true,
        \'single_line_comment_style\' => [\'comment_types\' => [\'hash\']],
        \'single_quote\' => true,
        \'space_after_semicolon\' => [\'remove_in_empty_for_expressions\' => true],
        \'standardize_not_equals\' => true,
        \'ternary_operator_spaces\' => true,
        \'trailing_comma_in_multiline\' => true,
        \'trim_array_spaces\' => true,
        \'unary_operator_spaces\' => true,
        \'whitespace_after_comma_in_array\' => true,])
    ->setFinder($finder);
';
    }

    /**
     * Generate configuration with custom rules
     */
    public function generateWithRules(array $customRules): string
    {
        $baseConfig = $this->generate();

        // This would merge custom rules with the base configuration
        // For now, returning the base config
        return $baseConfig;
    }
}
