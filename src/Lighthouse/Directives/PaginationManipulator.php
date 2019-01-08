<?php

namespace MatrixLab\LaravelAdvancedSearch\Lighthouse\Directives;

use MatrixLab\LaravelAdvancedSearch\Lighthouse\Types\PaginatorField;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Schema\Directives\Fields\PaginationManipulator as BasePaginationManipulator;

class PaginationManipulator extends BasePaginationManipulator
{

    /**
     * Transform the definition for a field to a field with pagination.
     *
     * This makes either an offset-based Paginator or a cursor-based Connection.
     * The types in between are automatically generated and applied to the schema.
     *
     * @param string $paginationType
     * @param FieldDefinitionNode $fieldDefinition
     * @param ObjectTypeDefinitionNode $parentType
     * @param DocumentAST $current
     * @param int|null $defaultCount
     *
     * @return DocumentAST
     * @throws DirectiveException
     * @throws \Nuwave\Lighthouse\Exceptions\ParseException
     */
    public static function transformToPaginatedField(string $paginationType, FieldDefinitionNode $fieldDefinition, ObjectTypeDefinitionNode $parentType, DocumentAST $current, int $defaultCount = null): DocumentAST
    {
        switch (self::assertValidPaginationType($paginationType)) {
            case self::PAGINATION_TYPE_CONNECTION:
                return self::registerConnection($fieldDefinition, $parentType, $current, $defaultCount);
            case self::PAGINATION_TYPE_PAGINATOR:
            default:
                return self::registerPaginator($fieldDefinition, $parentType, $current, $defaultCount);
        }
    }

    /**
     * Register paginator w/ schema.
     *
     * @param FieldDefinitionNode $fieldDefinition
     * @param ObjectTypeDefinitionNode $parentType
     * @param DocumentAST $documentAST
     * @param int|null $defaultCount
     *
     * @return DocumentAST
     * @throws \Nuwave\Lighthouse\Exceptions\ParseException
     */
    public static function registerPaginator(FieldDefinitionNode $fieldDefinition, ObjectTypeDefinitionNode $parentType, DocumentAST $documentAST, int $defaultCount = null): DocumentAST
    {
        $fieldTypeName = ASTHelper::getUnderlyingTypeName($fieldDefinition);
        $paginatorTypeName = "{$fieldTypeName}Paginator";
        $paginatorFieldClassName = addslashes(PaginatorField::class);

        // register paginator.
        $paginatorType = PartialParser::objectTypeDefinition("
            type $paginatorTypeName {
                cursor: PaginationCursor! @field(resolver: \"{$paginatorFieldClassName}@paginatorInfoResolver\")
                items: [$fieldTypeName!]! @field(resolver: \"{$paginatorFieldClassName}@dataResolver\")
            }
        ");

        // register Pagination Cursor object.
        $paginationCursor = PartialParser::objectTypeDefinition('
            """Paginator input type"""
            type PaginationCursor {
                total: Int!
                perPage: Int!
                currentPage: Int!
                hasPages: Boolean!
            }
        ');

        // register Paginator Input object.
        $paginatorInput = PartialParser::inputObjectTypeDefinition('
            """Paginator input type"""
            input PaginatorInput {
                """Display a specific page"""
                page: Int
                """Limit the items per page"""
                limit: Int
                """Sort someone field"""
                sort: String
                """Sort some fields"""
                sorts: [String]
            }
        ');

        $fieldDefinition->type = PartialParser::namedType($paginatorTypeName);
        $parentType->fields = ASTHelper::mergeNodeList($parentType->fields, [$fieldDefinition]);

        $documentAST->setDefinition($paginatorType);
        $documentAST->setDefinition($parentType);
        $documentAST->setDefinition($paginationCursor);
        $documentAST->setDefinition($paginatorInput);

        return $documentAST;
    }
}
