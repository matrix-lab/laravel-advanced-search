<?php

namespace Tests\Unit\AdvancedSearchTrait;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

class SimpleLikeSearchTest extends DBTestCase
{
    public function test_custom_search_function()
    {
        factory(User::class)->make([
            'name' => 'this name is testing for simple like search, foooo name',
        ])->save();
        factory(User::class)->make([
            'name' => 'this name is testing for simple like search, foooo name',
        ])->save();
        factory(User::class)->make([
            'name' => 'this name is testing for simple like search, barrr name',
        ])->save();

        factory(User::class, 20)->create();

        $this->assertEquals(2, User::getCount([
            'keyword' => 'foooo',
        ]));
        $this->assertEquals(1, User::getCount([
            'keyword' => 'barrr',
        ]));
        $this->assertEquals(2, User::getCount([
            'search' => 'foooo',
        ]));
        $this->assertEquals(2, User::getCount([
            'key' => 'foooo',
        ]));
    }
}
