<?php

namespace MatrixLab\LaravelAdvancedSearch\Lighthouse\Directives;

use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\DirectiveNode;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use GraphQL\Language\AST\ObjectTypeExtensionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Schema\Directives\NamespaceDirective;
use Nuwave\Lighthouse\Support\Contracts\NodeManipulator;
use Nuwave\Lighthouse\Schema\Directives\GroupDirective as LighthouseGroupDirective;

/**
 * Class GroupDirective.
 *
 * This directive is kept for compatibility reasons but is superseded by
 * NamespaceDirective and MiddlewareDirective.
 *
 * @deprecated Will be removed in next major version
 */
class GroupDirective extends LighthouseGroupDirective implements NodeManipulator
{
    /**
     * @param ObjectTypeDefinitionNode|ObjectTypeExtensionNode $objectType
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DirectiveException
     *
     * @return ObjectTypeDefinitionNode|ObjectTypeExtensionNode
     */
    protected function setNamespaceDirectiveOnFields($objectType)
    {
        $namespaceValue = $this->directiveArgValue('namespace');

        if (! $namespaceValue) {
            return $objectType;
        }

        if (! is_string($namespaceValue)) {
            throw new DirectiveException('The value of the namespace directive on has to be a string');
        }

        $namespaceValue = addslashes($namespaceValue);

        $objectType->fields = new NodeList(
            collect($objectType->fields)
                ->map(function (FieldDefinitionNode $fieldDefinition) use ($namespaceValue) {
                    $existingNamespaces = ASTHelper::directiveDefinition(
                        $fieldDefinition,
                        NamespaceDirective::NAME
                    );

                    $newNamespaceDirective = $existingNamespaces
                        ? $this->mergeNamespaceOnExistingDirective($namespaceValue, $existingNamespaces)
                        : PartialParser::directive("@namespace(field: \"$namespaceValue\", complexity: \"$namespaceValue\", getlist: \"$namespaceValue\")");

                    $fieldDefinition->directives = $fieldDefinition->directives->merge([$newNamespaceDirective]);

                    return $fieldDefinition;
                })
                ->toArray()
        );

        return $objectType;
    }

    /**
     * @param string $namespaceValue
     * @param DirectiveNode $directive
     *
     * @return DirectiveNode
     */
    protected function mergeNamespaceOnExistingDirective(string $namespaceValue, DirectiveNode $directive): DirectiveNode
    {
        $namespaces = PartialParser::arguments([
            "field: \"$namespaceValue\"",
            "complexity: \"$namespaceValue\"",
            "getlist: \"$namespaceValue\"",
        ]);

        $directive->arguments = $directive->arguments->merge($namespaces);

        return $directive;
    }
}
