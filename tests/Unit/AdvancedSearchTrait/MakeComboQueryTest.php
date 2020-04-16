<?php

namespace Tests\Unit\AdvancedSearchTrait;

use MatrixLab\LaravelAdvancedSearch\ModelScope;
use Tests\DBTestCase;
use Tests\Utils\Models\Company;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class MakeComboQueryTest extends DBTestCase
{
    protected function setUp()
    {
        parent::setUp();
        factory(Company::class)->make([
            'name' => 'foooo',
        ])->save();
        factory(Company::class)->make([
            'name' => 'barrr',
        ])->save();
        factory(Company::class)->make([
            'name' => null,
        ])->save();
        factory(Company::class, 10)->create();
    }

    public function test_operator_in()
    {
        $this->assertEquals(2, Company::getList([
            'wheres' => [
                'name.in' => ['foooo', 'barrr'],
            ],
        ])->count());
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
