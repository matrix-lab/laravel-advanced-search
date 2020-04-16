<?php

namespace Tests\Utils;

use MatrixLab\LaravelAdvancedSearch\ConditionsGeneratorTrait;

class FakeRequest
{
    use ConditionsGeneratorTrait;

    protected function order()
    {
        return '+id';
    }
}
