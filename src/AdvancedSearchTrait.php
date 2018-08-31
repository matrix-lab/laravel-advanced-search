<?php

namespace MatrixLab\LaravelAdvancedSearch;

use Cache;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use ReflectionClass;
use Request;
use Schema;
use Symfony\Component\CssSelector\Exception\InternalErrorException;

trait AdvancedSearchTrait
{
    /*
    |--------------------------------------------------------------------------
    | 通用的搜索和列表
    |--------------------------------------------------------------------------
    |
    |   搜索包含了关键字/分页/多条件查询/关系查询等功能
    |
    */

    /**
     * 根据条件获取符合条件的数据条数.
     *
     * @param array $conditions
     * @param null  $with
     * @param array $selects
     * @param bool  $withTrashed
     *
     * @return mixed
     * @throws InternalErrorException
     * @throws \ReflectionException
     */
    public static function getCount($conditions = [], $with = null, $selects = ['*'], $withTrashed = false)
    {
        unset($conditions['page']);

        return static::getListQuery($conditions, $with, $selects, $withTrashed)->count((new static())->getKeyName());
    }

    /**
     * 根据请求的条件获取列表.
     *
     * @param array        $conditions
     * @param array|string $with
     * @param bool         $withTrashed
     *
     * @return Builder
     * @throws InternalErrorException
     * @throws \ReflectionException
     */
    public static function getListQuery($conditions = [], $with = null, $withTrashed = false)
    {
        $query = static::getQueryForSearch($with);

        if ($withTrashed) {
            $query->withTrashed();
        }

        // 搜索条件 （简单的模糊搜索）
        $query = static::simpleLikeSearch($query, $conditions);

        // 添加 where 搜索 （高级搜索）
        $query = static::advanceSearch($query, $conditions);

        // 添加排序
        $query = static::orderSearch($query, $conditions);

        // 记录偏移
        $query = static::offsetSearch($query, $conditions);

        return $query;
    }

    /**
     * 根据请求的条件获取列表.
     *
     * @param array        $conditions
     * @param array|string $with
     * @param array        $selects
     * @param bool         $withTrashed
     *
     * @return mixed
     * @throws InternalErrorException
     * @throws \ReflectionException
     */
    public static function getList($conditions = [], $with = null, $selects = ['*'], $withTrashed = false)
    {
        $query = static::getListQuery($conditions, $with, $withTrashed);

        // 根据请求中是否存在 page 参数来返回 collection | paginator
        if (array_has($conditions, 'page')) {
            $pageSize = Request::has('pageSize') ? Request::input('pageSize') : config('erp.page_size');
            $pageSize = Request::has('page_size') ? Request::input('page_size') : $pageSize;
            $pageSize = array_has($conditions, 'pageSize') ? $conditions['pageSize'] : $pageSize;
            $pageSize = array_has($conditions, 'page_size') ? $conditions['page_size'] : $pageSize;

            /* @var Builder $query */
            return $query->paginate((int)$pageSize, $selects, 'page', $conditions['page'])->appends(Request::except([
                'page',
            ]));
        } else {
            /* @var Builder $query */
            return $query->select($selects)->get();
        }
    }

    /**
     * 根据是否进行了搜索引擎，进行搜索.
     *
     * @param array $conditions
     * @param null  $with
     * @param array $selects
     * @param bool  $withTrashed
     *
     * @return mixed
     * @throws InternalErrorException
     * @throws \ReflectionException
     */
    public static function dynamicGetList($conditions = [], $with = null, $selects = ['*'], $withTrashed = false)
    {
        $list = static::getList($conditions, $with, $selects, $withTrashed);

        return $list;
    }

    /**
     * 为搜索准备查询对象
     *
     * @param $with
     *
     * @return $this|Builder
     */
    private static function getQueryForSearch($with)
    {
        $query = static::query();

        if (!empty($with)) {
            $query = $query->with($with);
        }

        return $query;
    }

    /**
     * 简单的搜索名称等字段内容（模糊搜索）.
     *
     * @param $query
     * @param $conditions
     *
     * @return Builder
     */
    private static function simpleLikeSearch($query, $conditions)
    {
        $keyword = '';
        if (array_has($conditions, 'keyword')) {
            $keyword = array_get($conditions, 'keyword', '');
        } elseif (array_has($conditions, 'search')) {
            $keyword = array_get($conditions, 'search', '');
        } elseif (array_has($conditions, 'key')) {
            $keyword = array_get($conditions, 'key', '');
        }

        if ($keyword) {
            $query->searchKeyword($keyword);
        }

        return $query;
    }

    /**
     * 高级搜索.
     *
     * @param Builder $query
     * @param         $conditions
     *
     * @return mixed
     * @throws \ReflectionException
     * @throws InternalErrorException
     */
    private static function advanceSearch($query, $conditions)
    {
        $wheres = self::sortOutWhereConditionsPro($conditions);

        foreach ($wheres as $where) {
            if (is_array($where)) {
                foreach ($where as $field => $operatorAndValue) {
                    $mixType = array_get($operatorAndValue, 'mix', 'and');
                    unset($operatorAndValue['mix']);

                    // 关联查询
                    if (str_contains($field, '.')) {
                        $query->whereHas(
                            camel_case(explode('.', $field)[0]),
                            function ($q) use ($operatorAndValue, $mixType, $field) {
                                self::makeComboQueryPro($q, $field, $mixType, $operatorAndValue);
                            }
                        );
                    } else {
                        // 常规查询
                        $query->where(function ($q) use ($field, $mixType, $operatorAndValue) {
                            self::makeComboQueryPro($q, $field, $mixType, $operatorAndValue);
                        });
                    }
                }
            } elseif ($where instanceof Closure) {
                $query->where($where);
            } elseif ($where instanceof Expression) {
                $query->whereRaw($where);
            } elseif ($where instanceof ModelScope) {
                $method      = $where->getScopeName();
                $className   = $where->getClassName() ?: static::class;
                $args        = $where->getArgs();
                $scopeMethod = 'scope'.title_case($method);

                if ($className === static::class
                    && (new ReflectionClass(static::class))->hasMethod($scopeMethod)) {
                    $query->{$method}(...$args);
                } else {
                    throw new InternalErrorException(static::class.'类中找不到'.$scopeMethod.'方法');
                }
            }
        }

        return $query;
    }

    /**
     * 构造 where 查询
     *
     * @param $q
     * @param $field
     * @param $mixType
     * @param $operatorAndValue
     */
    private static function makeComboQueryPro($q, $field, $mixType, $operatorAndValue)
    {
        // where 类型
        $whereType = $mixType == 'and' ? 'where' : 'orWhere';

        // 构造查询
        foreach ($operatorAndValue as $operator => $value) {
            if ($operator == 'in') {
                if ((is_array($value) || $value instanceof Collection) && !empty($value)) {
                    $q->{"{$whereType}In"}($field, $value);
                }
            } elseif ($operator == 'not_in') {
                if (is_array($value) && !empty($value)) {
                    $q->{"{$whereType}NotIn"}($field, $value);
                }
            } elseif ($operator == 'is') {
                $q->{"{$whereType}null"}($field);
            } elseif (snake_case($operator) == 'is_not') {
                $q->{"{$whereType}NotNull"}($field);
            } else {
                $q->{$whereType}($field, self::convertOperator($operator), $value);
            }
        }
    }

    /**
     * 整理出 where 条件，第二版.
     *
     * @param $conditions
     *
     * @return array
     *
     * @throws InternalErrorException
     */
    private static function sortOutWhereConditionsPro($conditions)
    {
        $newConditions = [];

        foreach ($conditions['wheres'] as $key => $item) {
            if (is_int($key)) {
                // 如果是闭包的话，直接 push ，不做处理，构造时进行处理
                if ($item instanceof Closure || $item instanceof Expression || $item instanceof ModelScope) {
                    $newConditions[] = $item;
                    continue;
                }
                $field = explode('->', $item['field'])[0];

                $operatorAndValue = $item['value'];
            } else {
                if (str_contains($key, '.')) {  // 如果 $k 是  name.like 则要解析出正确的 field，以及映射好 operator
                    // eg: 'name.like' => 'ccc' 得到 'name' => [ 'like' => 'ccc']
                    $field            = explode('.', $key)[0];
                    $operatorAndValue = [explode('.', $key)[1] => $item];
                } elseif (!is_array($item)) {   // 如果没有操作符的话，那么默认就是等号
                    //eg: 'name' => 'chaoyang' 得到 'name' => ['eq' => 'chaoyang']
                    $field            = $key;
                    $operatorAndValue = ['eq' => $item];
                } else {
                    $field            = $key;
                    $operatorAndValue = $item;
                }

                // 上面的操作一定会将 操作符 和 值 处理为数组
                if (!is_array($operatorAndValue)) {
                    throw new InternalErrorException('搜索查询的传参有误，请检查');
                }

                // 处理 $field 里面包含 $ 的情况
                // eg: 'agent$name' => [ 'like' => '%猪队友%' ] 转换为  'agent.name' => [ 'like' => '%猪队友%' ]
                $field = str_replace('$', '.', $field);
            }
            $newConditions[] = [
                $field => $operatorAndValue,
            ];
        }

        return $newConditions;
    }

    private static function convertOperator($operator)
    {
        $operator = $operator == 'eq' ? '=' : $operator;
        $operator = $operator == 'ne' ? '<>' : $operator;
        $operator = $operator == 'gt' ? '>' : $operator;
        $operator = $operator == 'gte' ? '>=' : $operator;
        $operator = $operator == 'ge' ? '>=' : $operator;
        $operator = $operator == 'lt' ? '<' : $operator;
        $operator = $operator == 'lte' ? '<=' : $operator;
        $operator = $operator == 'le' ? '<=' : $operator;

        return $operator ?? '=';
    }

    /**
     * 排序功能.
     *
     * @param $query
     * @param $conditions
     *
     * @return mixed
     */
    private static function orderSearch($query, $conditions)
    {
        $order = isset($conditions['order']) ? $conditions['order'] : [];
        if (is_string($order)) {
            $order = [
                $order => array_get($conditions, 'direction', 'desc'),
            ];
        }

        foreach ($order as $field => $direction) {
            $query->orderBy($field, $direction);
        }

        return $query;
    }

    /**
     * 分页.
     *
     * @param $query
     * @param $conditions
     *
     * @return mixed
     */
    private static function offsetSearch($query, $conditions)
    {
        $offset = array_has($conditions, 'offset') ? $conditions['offiset'] : 0;
        $limit  = array_has($conditions, 'limit') ? $conditions['limit'] : 0;
        if ($limit > 0) {
            $query->skip((int)$offset)->take((int)$limit);
        }

        return $query;
    }

    /**
     * 统统都要有的搜索方法.
     *
     * @param $q
     * @param $key
     *
     * @return mixed
     */
    public function scopeSearchKeyword($q, $key)
    {
        if (is_array($key)) {
            return $q;
        }

        if (Schema::hasColumn($this->getTable(), 'name') && !empty(trim($key))) {
            $key = trim($key, ' ');
            $key = trim($key, '%');
            $key = "%{$key}%";
            $q->where('name', 'like', $key);
        }

        return $q;
    }
}
