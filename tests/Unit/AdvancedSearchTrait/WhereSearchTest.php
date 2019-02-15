<?php

namespace Tests\Unit;

use MatrixLab\LaravelAdvancedSearch\ModelScope;
use Tests\DBTestCase;
use Tests\Utils\Models\Company;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class WhereSearchTest extends DBTestCase
{
    protected function setUp()
    {
        parent::setUp();
        factory(User::class, 15)->create()->each(function ($user) {
            /** @var User $user */
            $user->posts()->save(factory(Post::class)->make());
        });
        factory(Company::class)->make([
            'name' => 'this name is testing for search, foooo name',
        ])->save();
        factory(Company::class)->make([
            'name' => 'this name is testing for search, foooo name',
        ])->save();
        factory(Company::class)->make([
            'name' => 'this name is testing for search, barrr name',
        ])->save();
    }

    public function test_array_where_conditions()
    {
        $pageSize = 10;
        $list = Company::getList(['page' => 1, 'page_size' => $pageSize, 'wheres' => [
            'name.like' => '%foooo%',
        ]]);

        $this->assertEquals(2, $list->count());
    }

    public function test_relation_where_conditions()
    {
        /** @var LengthAwarePaginator $list */
        $list = User::getList(['page' => 1, 'page_size' => 10, 'wheres' => [
            'posts$id.gte' => '1',
        ]]);

        $list->each(function($user) {
            $this->assertEquals(1, $user->posts->count());
        });
    }

    public function test_closure_where_conditions()
    {
        /** @var LengthAwarePaginator $list */
        $list = User::getList(['page' => 1, 'page_size' => 10, 'wheres' => [
            function($q) {
                $q->whereHas('posts');
            },
        ]]);

        $this->assertEquals(10, $list->count());
    }

    public function test_expression_where_conditions()
    {
        /** @var LengthAwarePaginator $list */
        $list = Company::getList(['page' => 1, 'page_size' => 10, 'wheres' => [
            DB::raw('name = "this name is testing for search, foooo name"'),
        ]]);

        $this->assertEquals(2, $list->count());
    }

    public function test_model_scope_where_conditions()
    {
        /** @var LengthAwarePaginator $list */
        $list = Company::getList(['page' => 1, 'page_size' => 10, 'wheres' => [
            new ModelScope('name', 'this name is testing for search, foooo name'),
        ]]);

        $this->assertEquals(2, $list->count());
    }

}
