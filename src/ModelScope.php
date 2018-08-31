<?php

namespace MatrixLab\LaravelAdvancedSearch;

class ModelScope
{
    /**
     * 类名
     *
     * @var
     */
    protected $className;

    /**
     * scope 方法名称
     *
     * @var
     */
    private $scopeName;

    /**
     * 构造参数
     *
     * @var array
     */
    private $args;

    public function __construct($scopeName, ...$args)
    {
        $this->scopeName = $scopeName;
        $this->args      = $args;
    }

    /**
     * @return mixed
     */
    public function getScopeName()
    {
        return $this->scopeName;
    }

    /**
     * @return array
     */
    public function getArgs(): array
    {
        return $this->args;
    }

    /**
     * @param mixed $className
     * @return ModelScope
     */
    public function setClassName($className): ModelScope
    {
        $this->className = $className;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getClassName()
    {
        return $this->className;
    }
}
