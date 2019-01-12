<?php

namespace MatrixLab\LaravelAdvancedSearch\Lighthouse\Directives;

use App\Models\BaseModel;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Str;
use MatrixLab\LaravelAdvancedSearch\ConditionsGeneratorTrait;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;

class GetlistDirective extends BaseDirective implements FieldResolver, FieldManipulator
{
    use ConditionsGeneratorTrait;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'getlist';
    }

    /**
     * @param FieldDefinitionNode $fieldDefinition
     * @param ObjectTypeDefinitionNode $parentType
     * @param DocumentAST $current
     *
     * @return DocumentAST
     * @throws DirectiveException
     * @throws \Nuwave\Lighthouse\Exceptions\ParseException
     */
    public function manipulateSchema(FieldDefinitionNode $fieldDefinition, ObjectTypeDefinitionNode $parentType, DocumentAST $current): DocumentAST
    {
        return PaginationManipulator::transformToPaginatedField(
            $this->getPaginationType(),
            $fieldDefinition,
            $parentType,
            $current,
            $this->directiveArgValue('defaultCount')
        );
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $fieldValue
     *
     * @return FieldValue
     * @throws DirectiveException
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        if ($this->directiveHasArgument('resolver')) {
            return $this->redirectQueryResolver($fieldValue);
        }

        return $this->paginatorTypeResolver($fieldValue);
    }

    /**
     * @return string
     * @throws DirectiveException
     */
    protected function getPaginationType(): string
    {
        return PaginationManipulator::assertValidPaginationType(
            $this->directiveArgValue('type', PaginationManipulator::PAGINATION_TYPE_PAGINATOR)
        );
    }

    /**
     * Create a paginator resolver.
     *
     * @param FieldValue $value
     *
     * @return FieldValue
     */
    protected function paginatorTypeResolver(FieldValue $value): FieldValue
    {
        return $value->setResolver(
            function ($root, array $args) use ($value) {
                $this->inputArgs = $args;

                /** @var BaseModel $model */
                $model = $this->getPaginatorModel();
                return $model::getGraphQLPaginator($this->getConditions($args), func_get_args()[3]);
            }
        );
    }

    /**
     * Set wheres for getlist condition
     *
     * @return array
     */
    public function wheres()
    {
        return collect($this->directiveArgValue('args', []))
            ->merge($this->inputArgs)
            ->except('paginator')
            ->filter()
            ->toArray();
    }

    /**
     * @param array $resolveArgs
     * @param int $page
     * @param int $perPage
     *
     * @param mixed $order
     * @return \Illuminate\Pagination\LengthAwarePaginator
     * @throws DirectiveException
     */
    protected function getPaginatedResults(array $resolveArgs, int $page, int $perPage, $order = '')
    {
        /** @var BaseModel $model */
        $model      = $this->getPaginatorModel();
        $conditions = [
            'page'      => $page,
            'page_size' => $perPage,
        ];
        if ($order) {
            $conditions['order'] = $order;
        }

        return $model::getGraphQLPaginator($conditions, $resolveArgs[3]);
    }

    /**
     * Custom query resolver
     *
     * @param FieldValue $fieldValue
     * @return FieldValue
     * @throws DirectiveException
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    private function redirectQueryResolver(FieldValue $fieldValue)
    {
        [$className, $methodName] = $this->getMethodArgumentParts('resolver');

        $namespacedClassName = $this->namespaceClassName(
            $className,
            $fieldValue->defaultNamespacesForParent()
        );

        $resolver = construct_resolver($namespacedClassName, $methodName);

        $additionalData = $this->directiveArgValue('args');

        return $fieldValue->setResolver(
            function ($root, array $args, $context = null, $info = null) use ($resolver, $additionalData) {
                return $resolver(
                    $root,
                    array_merge($args, ['directive' => $additionalData]),
                    $context,
                    $info
                );
            }
        );
    }


    /**
     * Get the model class from the `model` argument of the field.
     *
     * This works differently as in other directives, so we define a seperate function for it.
     *
     * @throws DirectiveException
     *
     * @return string
     */
    protected function getPaginatorModel(): string
    {
        $model = $this->directiveArgValue('model');

        // Fallback to using information from the schema definition as the model name
        if (!$model) {
            $model = ASTHelper::getUnderlyingTypeName($this->definitionNode);

            // Cut the added type suffix to get the base model class name
            $model = Str::before($model, 'Paginator');
            $model = Str::before($model, 'Connection');
        }

        if (!$model) {
            throw new DirectiveException(
                "A `model` argument must be assigned to the '{$this->name()}'directive on '{$this->definitionNode->name->value}"
            );
        }

        return $this->namespaceClassName(
            $model,
            (array)config('lighthouse.namespaces.models')
        );
    }
}
