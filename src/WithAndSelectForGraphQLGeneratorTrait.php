<?php

namespace MatrixLab\LaravelAdvancedSearch;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use ReflectionClass;
use Symfony\Component\CssSelector\Exception\InternalErrorException;

trait WithAndSelectForGraphQLGeneratorTrait
{
    /**
     * @param $conditions
     * @param ResolveInfo $info
     * @return LengthAwarePaginator
     */
    public static function getGraphQLPaginator($conditions, ResolveInfo $info)
    {
        $fields = $info->getFieldSelection(5);

        // 如果没有 total 则返回简单分页
        if (!array_has($fields, 'cursor.total')) {
            return static::getSimpleList($conditions, ...static::getWithAndSelect($info));
        }

        // 如果有查询内容的话
        if (array_has($fields, 'items')) {
            return static::getList($conditions, ...static::getWithAndSelect($info));
        }

        // 如果没有查询内容，则认为是只获取分页
        return new LengthAwarePaginator([], static::getCount($conditions), 10);
    }

    public function getAllColumns()
    {
        if (!(new ReflectionClass(static::class))->hasProperty('allColumns')) {
            return ['*'];
        }

        $allColumns = (new static())->allColumns;

        if (empty($allColumns)) {
            throw new InternalErrorException(" SQL 的 select 内容为空，请检查 ".static::class." 中是否有 \$allColumns 字段，如果没有，请执行 php artisan make:models-columns 生成。");
        }

        return $allColumns;
    }

    public static function getWithAndSelect($info)
    {
        $fields = $info->getFieldSelection(5);

        return static::parseResolveInfoToWithColumns($fields);
    }

    /**
     * 解析 with 关系和 selects 关系
     *
     * @param $fields
     * @return array
     * @throws \ReflectionException
     */
    protected static function parseResolveInfoToWithColumns($fields)
    {
        $fields = isset($fields['items']) ? $fields['items'] : $fields;

        $columns         = [];
        $withes          = [];
        $modelReflection = new ReflectionClass(static::class);
        foreach ($fields as $field => $isSingleField) {
            if ($modelReflection->hasMethod($field)) {
                //  $withes[] = $field.':'.join(',', static::getRelationSelect($field, static::parseResolveInfoToWithColumns($isSingleField)[1]));

                //  list($subWith, $subSelect) = static::parseResolveInfoToWithColumns($isSingleField);
                //
                //  foreach ($subWith as $with) {
                //      $withes[] = $field.'.'.$with;
                //  }

                // 目前支持嵌套3层查询

                $relation = (new static)->{$field}(); // 关联模型对象
                /** @var Model $relation */
                $relationModel      = $relation->getModel();
                $relationReflection = new ReflectionClass($relationModel); // 关联模型对象的反射
                $withColumns        = [];
                foreach ($isSingleField as $subField => $isSingleSubField) {
                    if (static::canBeSelected($relationModel, $subField)) {
                        $withColumns[] = $subField;
                    } elseif ($relationReflection->hasMethod($subField)) {
                        $subRelationModelInstance = $relationReflection->newInstance()->{$subField}()->getModel();
                        $withes[]                 = $field.'.'.$subField.':'.join(',', ($subRelationModelInstance)::parseResolveInfoToWithColumns($isSingleSubField)[1]);
                    }
                }

                $withColumns = static::getRelationSelect($field, $withColumns);

                $withes[] = empty($withColumns) ? $field : $field.':'.join(',', $withColumns);
            } elseif (static::canBeSelected((new static), $field)) {
                $columns[] = $field;
            }
        }

        return [$withes, empty($columns) ? ['*'] : $columns];
    }

    private static function canBeSelected($model, $field)
    {
        return in_array($field, $model->getAllColumns()) || $model->getAllColumns() === ['*'];
    }

    /**
     * 获取关联关系里面的 select
     *
     * @param $relation
     * @param $columns
     * @return array
     * @throws \ReflectionException
     */
    private static function getRelationSelect($relation, $columns)
    {
        $relation = (new static)->{$relation}();

        if ((new ReflectionClass($relation))->hasMethod('getModel')) {
            $relationModel = $relation->getModel();
            if (!(new ReflectionClass(static::class))->hasProperty('allColumns')) {
                return ['*'];
            }

            return array_intersect($columns, $relationModel->getAllColumns());
        }

        return $columns;
    }
}
