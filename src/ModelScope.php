<?php

namespace MatrixLab\LaravelAdvancedSearch;

class ModelScope
{

    protected $className;

    private $scopeName;
    /**
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