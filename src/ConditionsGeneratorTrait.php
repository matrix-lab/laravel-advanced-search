<?php

namespace MatrixLab\LaravelAdvancedSearch;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Database\Query\Expression;

/**
 * 条件搜索的条件生成器.
 *
 * Trait ConditionsGeneratorTrait
 */
trait ConditionsGeneratorTrait
{
    /**
     * 需要输出的 conditions.
     *
     * @var array
     */
    protected $conditions = [
        'wheres' => [],
    ];

    /**
     * 从外部输入的字段内容.
     *
     * @var array
     */
    protected $inputArgs;

    /**
     * 追加条件.
     *
     * @param $appendItems
     *
     * @return static
     */
    public function appendConditions($appendItems)
    {
        // 合并到 wheres
        $this->conditions['wheres'] = array_merge($this->conditions['wheres'], array_get($appendItems, 'wheres', []));
        array_forget($appendItems, 'wheres');

        // 追加数据到根节点
        $this->conditions = array_merge($this->conditions, $appendItems);

        return $this;
    }

    /**
     * @param array $inputArgs
     *
     * @return static
     */
    public function setInputArgs(array $inputArgs)
    {
        $this->inputArgs = $inputArgs;

        return $this;
    }

    protected function wheres()
    {
        return [];
    }

    protected function order()
    {
        return [];
    }

    protected function groupBy()
    {
        return [];
    }

    protected function having()
    {
        return [];
    }

    /**
     * 自由处理请求参数.
     *
     * @param         $requestKey
     * @param Closure $fire
     *
     * @return array|mixed|string
     */
    protected function fireInput($requestKey, Closure $fire = null)
    {
        if (! $this->isVaildInput($requestKey)) {
            return;
        }

        $inputArg = $this->getInputArgs($requestKey);

        return $fire ? $fire($inputArg) : $inputArg;
    }

    /**
     * 获取 where 查询条件.
     *
     * @param $args
     *
     * @return Collection
     */
    public function getConditions($args)
    {
        $this->inputArgs = $args;

        // 加工 inputArgs
        $this->handleInputArgs();

        // 加工分页(该方法可以重写)
        $this->handlePaginate();

        // 加工 group by
        $this->handleGroupBy();

        // 加工 having
        $this->handleHaving();

        // 将 wheres 方法内配置的条件拿过来
        $this->conditions['wheres'] = array_merge($this->conditions['wheres'], $this->wheres());

        // 遍历 where 配置，生成 getList 所需要的 where 结构
        $this->conditions['wheres'] = collect($this->conditions['wheres'])
            ->filter()
            ->mapWithKeys(function ($item, $key) use ($args) {
                return $this->generateWhereKeyValue($item, $key);
            })->all();

        return collect($this->conditions);
    }

    /**
     * 处理 where 数组内容.
     *
     * @param $item
     * @param $key
     *
     * @return array
     */
    private function generateWhereKeyValue($item, $key)
    {
        // 对于 When 对象处理
        if ($item instanceof When) {
            $item = $item->result();
        }

        // 如果传递的是闭包或原生查询条件
        if (is_int($key) && ($item instanceof Closure || $item instanceof Expression || $item instanceof ModelScope)) {
            return [$key => $item];
        }

        // 默认索引的话， item 就是 field ，如果非默认索引的话， key 就是 field
        $field = is_int($key) ? $item : $key;

        if (is_null($field)) {
            return [];
        }

        // 获取要查询的字段值内容
        $value = is_int($key) ?
            $this->getInputArgs($field) :
            ($item instanceof Closure ? $item() : $item);

        // 过滤无效的 where 条件
        // 针对  is null 或 is not null 的情况，请传一个 null 字符串
        if (is_null($value) || $value === '') {
            return [];
        }

        return [$field => $value];
    }

    protected function getPageAlias()
    {
        return [
            'paginator.page'  => 'page',
            'page'            => 'page',
            'paginator.limit' => 'page_size',
            'page_size'       => 'page_size',
        ];
    }

    /**
     * 生成分页的参数.
     *
     * @return $this
     */
    protected function handlePaginate()
    {
        // 处理页码和分页
        foreach ($this->getPageAlias() as $key => $value) {
            if (array_has($this->inputArgs, $key)) {
                $this->appendConditions([
                    $value => $this->getInputArgs($key),
                ]);
                array_forget($this->inputArgs, $key);
            }
        }

        // 设置默认的页数和页码
        data_fill($this->conditions, 'page', 1);
        data_fill($this->conditions, 'page_size', 15);

        // 处理排序
        $sorts = [];
        if (array_has($this->inputArgs, 'paginator.sort')) {
            $sorts = array_merge($sorts, [$this->getInputArgs('paginator.sort')]);
            array_forget($this->inputArgs, 'paginator.sort');
        }
        if (array_has($this->inputArgs, 'paginator.sorts')) {
            $sorts = array_merge($sorts, $this->getInputArgs('paginator.sorts'));
            array_forget($this->inputArgs, 'paginator.sorts');
        }
        $sorts = collect($sorts)->filter();
        $sorts = collect($this->order())->merge($sorts);
        $orders = [];
        foreach ($sorts as $sort) {
            if (is_string($sort)) {
                if (! starts_with($sort, ['+', '-'])) {
                    continue;
                }
                $orders[substr($sort, 1)] = $sort[0] == '+' ? 'asc' : 'desc';
            }

            if ($sort instanceof Expression) {
                $orders[] = $sort;
            }
        }
        $this->appendConditions(['order' => $orders]);

        return $this;
    }

    /**
     * 生成 group by 的参数.
     *
     * @return $this
     */
    protected function handleGroupBy()
    {
        $groupBy = $this->groupBy();

        // 过滤 groupBy 支持的格式
        if (!is_string($groupBy) && !is_array($groupBy) && !($groupBy instanceof When) && !($groupBy instanceof Expression)) {
            $groupBy = [];
        }

        if (!is_array($groupBy)) {
            $groupBy = [$groupBy];
        }

        $this->appendConditions([
            'groupBy' => collect($groupBy)->filter()->map(function ($item) {
                    if ($item instanceof When) {
                        $item = $item->result();
                    }

                    return $item;
                })->unique()->values()->all()
        ]);

        return $this;
    }

    /**
     * 生成 having 的参数.
     *
     * @return $this
     */
    protected function handleHaving()
    {
        $having = $this->having();

        if (!is_array($having)) {
            $having = [$having];
        }

        $having = collect($having)->filter()->map(function ($item) {
            if ($item instanceof When) {
                $item = $item->result();
            }

            return $item;
        })->all();

        $havings = [];

        foreach ($having as $index => $item) {
            if (is_int($index) && is_array($item)) {// 判断 when 的情况
                $havings = array_merge($havings, $item);
            } else {
                $havings[$index] = $item;
            }
        }

        $this->appendConditions([
            'having' => $havings
        ]);

        return $this;
    }

    /**
     * 处理输入的参数（根据需要可以重写该方法）.
     *
     * @return $this
     */
    protected function handleInputArgs()
    {
        // 处理 more
        $moreInputs = $this->getInputArgs('more');
        if (is_array($moreInputs) && ! empty($moreInputs)) {
            unset($this->inputArgs['more']);
            $this->inputArgs = array_merge($this->inputArgs, $moreInputs);
        }

        return $this;
    }

    /**
     * 获取参数.
     *
     * @param null $key
     * @param null $default
     *
     * @return array|mixed
     */
    public function getInputArgs($key = null, $default = null)
    {
        return is_null($key) ? $this->inputArgs : array_get($this->inputArgs, $key, $default);
    }

    /**
     * 判断输入的请求值是否有效.
     *
     * @param $requestKey
     *
     * @return bool
     */
    protected function isVaildInput($requestKey)
    {
        return array_has($this->inputArgs, $requestKey)
            && array_get($this->inputArgs, $requestKey) !== null
            && array_get($this->inputArgs, $requestKey) !== '';
    }

    /**
     * 添加内容到请求参数后面.
     *
     * @param        $requestKey
     * @param string $append
     *
     * @return array|mixed|string
     */
    protected function appendInput($requestKey, $append = '')
    {
        return $this->fireInput($requestKey, function ($value) use ($append) {
            return $value.$append;
        });
    }

    /**
     * 当 $value 是 true 时，执行 callback.
     *
     * @param $value
     * @param $callback
     * @param $default
     *
     * @return mixed
     */
    protected function when($value, $callback, $default = null)
    {
        $value = is_string($value) ? trim($value) : $value;
        if ($value !== '' && ! is_null($value) && $value !== []) {
            return $callback instanceof Closure ? $callback($this->inputArgs) : $callback;
        } elseif ($default) {
            return $default instanceof Closure ? $default($this->inputArgs) : $default;
        }
    }
}
