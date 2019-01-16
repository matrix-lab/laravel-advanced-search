<?php

namespace Tests\Unit;

use Tests\DBTestCase;
use Tests\Utils\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class GetListTest extends DBTestCase
{
    public function test_get_list()
    {
        factory(User::class, 30)->create();
        $pageSize = 10;
        $list = User::getList(['page' => 1, 'page_size' => $pageSize]);

        $this->assertEquals(LengthAwarePaginator::class, get_class($list));
        $this->assertEquals($pageSize, $list->count());
    }

    public function test_get_colletion()
    {
        factory(User::class, 20)->create();
        $list = User::getList();

        $this->assertTrue(get_class($list) === Collection::class);
        $this->assertTrue($list->count() === DB::table('users')->count());
    }

    public function test_get_length_aware_paginator()
    {
        factory(User::class, 20)->create();
        $paginator = User::getList(['page' => 1]);

        $this->assertTrue(get_class($paginator) === LengthAwarePaginator::class);
        $this->assertTrue($paginator->count() === (new User)->getPerPage());
    }

    public function test_get_paginator()
    {
        factory(User::class, 20)->create();
        $paginator = User::getSimpleList(['page' => 1]);

        $this->assertTrue(get_class($paginator) === Paginator::class);
        $this->assertTrue($paginator->count() === (new User)->getPerPage());
    }

    public function test_with_param()
    {
        factory(User::class, 20)->create();
        $paginator = User::getList(['page' => 1], 'company:id,name');

        $this->assertCount((new User)
            ->getPerPage(), data_get($paginator->toArray(), 'data.*.company.id'));
    }

    public function test_selects_param()
    {
        factory(User::class, 5)->create();
        $paginator = User::getList(['page' => 1], [], ['id', 'name']);
        $paginator = $paginator->toArray();

        foreach ($paginator['data'] as $user) {
            $this->assertTrue(['id', 'name'] === array_keys($user));
            $this->assertArrayNotHasKey('company_id', array_keys($user));
        }
    }
}
