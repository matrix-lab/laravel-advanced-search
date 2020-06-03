<?php

namespace Tests\Utils;

use MatrixLab\LaravelAdvancedSearch\ConditionsGeneratorTrait;

class FakeRequest
{
    use ConditionsGeneratorTrait;

    protected function wheres()
    {
        return [
            'empty_array_field' => $this->fireInput('empty_array_field', function($value) {
                return $value.'%';
            }),
        ];
    }

    protected function order()
    {
        return '+id';
    }
}
