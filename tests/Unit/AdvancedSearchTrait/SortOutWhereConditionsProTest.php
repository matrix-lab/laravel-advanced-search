<?php

namespace Tests\Unit;

use Tests\DBTestCase;
use Tests\Utils\Models\User;
use Illuminate\Support\Facades\DB;
use MatrixLab\LaravelAdvancedSearch\ModelScope;

class SortOutWhereConditionsProTest extends DBTestCase
{
    private $user;

    protected function setUp()
    {
        parent::setUp();
        $this->user = new User;
    }

    public function test_closure_value()
    {
        $getClosure = function () {
            return function ($q) {
                return $q->where('name', 'like', '%bar%');
            };
        };

        $result = $this->invokeMethod($this->user, 'sortOutWhereConditionsPro', [
            [
                'wheres' => [
                    $getClosure(),
                ],
            ],
        ]);

        $this->assertEquals($getClosure(), $result[0]);
    }

    public function test_expression_value()
    {
        $expression = DB::raw('2=3');

        $result = $this->invokeMethod($this->user, 'sortOutWhereConditionsPro', [
            [
                'wheres' => [
                    $expression,
                ],
            ],
        ]);

        $this->assertEquals($expression, $result[0]);
    }

    public function test_model_scope_value()
    {
        $modelScope = new ModelScope('searchKeyword', 'foo');

        $result = $this->invokeMethod($this->user, 'sortOutWhereConditionsPro', [
            [
                'wheres' => [
                    $modelScope,
                ],
            ],
        ]);

        $this->assertEquals($modelScope, $result[0]);
    }

    public function test_field_with_dot_and_operator()
    {
        $result = $this->invokeMethod($this->user, 'sortOutWhereConditionsPro', [
            [
                'wheres' => [
                    'name.like' => 'foo%',
                    'age.gt'    => 14,
                ],
            ],
        ]);

        $this->assertEquals([
            [
                'name' => [
                    'like' => 'foo%',
                ],
            ],
            [
                'age' => [
                    'gt' => 14,
                ],
            ],
        ], $result);
    }
}
