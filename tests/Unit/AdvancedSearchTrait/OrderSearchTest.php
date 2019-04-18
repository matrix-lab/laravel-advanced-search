<?php

namespace Tests\Unit;

use MatrixLab\LaravelAdvancedSearch\ModelScope;
use Tests\DBTestCase;
use Tests\Utils\Models\Company;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class OrderSearchTest extends DBTestCase
{
    protected function setUp()
    {
        parent::setUp();
        factory(Company::class)->make([
            'name' => 'a',
        ])->save();
        factory(Company::class)->make([
            'name' => 'b',
        ])->save();
        factory(Company::class)->make([
            'name' => 'c',
        ])->save();
    }

    public function test_operator_in()
    {
        $list = Company::getList([
            'order' => 'name',
        ]);

        dd($list->pluck('name'));
    }

    public function test_operator_not_in()
    {
        $this->assertEquals(10, Company::getList([
            'wheres' => [
                'name.not_in' => ['foooo', 'barrr'],
            ],
        ])->count());
    }

    public function test_operator_is()
    {
        $this->assertEquals(1, Company::getList([
            'wheres' => [
                'name.is' => 'null',
            ],
        ])->count());
    }

    public function test_operator_is_not()
    {
        $this->assertEquals(12, Company::getList([
            'wheres' => [
                'name.is_not' => 'null',
            ],
        ])->count());
    }
}
