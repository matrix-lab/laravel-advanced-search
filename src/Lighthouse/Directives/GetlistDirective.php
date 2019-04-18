<?php

namespace MatrixLab\LaravelAdvancedSearch\Lighthouse\Directives;

use App\Models\BaseModel;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use MatrixLab\LaravelAdvancedSearch\ConditionsGeneratorTrait;

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
        return $this->directiveArgValue('type', PaginationManipulator::PAGINATION_TYPE_PAGINATOR);
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
                $args = collect([
                    'paginator.sort'  => $this->directiveArgValue('sort'),
                    'paginator.sorts' => $this->directiveArgValue('sorts'),
                ])->merge($args)->filter()->toArray();

                /** @var BaseModel $model */
                $model = $this->getPaginatorModel();

                return $model::getGraphQLPaginator($this->getConditions($args), func_get_args()[3]);
            }
        );
    }

    /**
     * Set wheres for getlist condition.
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
     * Custom query resolver.
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


        $additionalData = $this->directiveArgValue('args');

        return $fieldValue->setResolver(
            function ($root, array $args, $context = null, $info = null) use ($methodName, $namespacedClassName, $additionalData) {
                return (new $namespacedClassName)->{$methodName}(
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
        if (! $model) {
            $model = ASTHelper::getUnderlyingTypeName($this->definitionNode);

            // Cut the added type suffix to get the base model class name
            $model = Str::before($model, 'Pagination');
            $model = Str::before($model, 'Connection');
        }

        if (! $model) {
            throw new DirectiveException(
                "A `model` argument must be assigned to the '{$this->name()}'directive on '{$this->definitionNode->name->value}"
            );
        }

        return $this->namespaceClassName(
            $model,
            (array) config('lighthouse.namespaces.models')
        );
    }
}
