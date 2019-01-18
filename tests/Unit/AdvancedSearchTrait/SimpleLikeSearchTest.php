<?php

namespace Tests\Unit;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

class SimpleLikeSearchTest extends DBTestCase
{
    public function test_custom_search_function()
    {
        factory(User::class)->make([
            'name' => 'this name is testing for simple like search, aaaaa name',
        ])->save();
        factory(User::class)->make([
            'name' => 'this name is testing for simple like search, aaaaa name',
        ])->save();
        factory(User::class)->make([
            'name' => 'this name is testing for simple like search, bbbbb name',
        ])->save();

        factory(User::class, 20)->create();

        $this->assertEquals(2, User::getCount([
            'keyword' => 'aaaaa',
        ]));
        $this->assertEquals(1, User::getCount([
            'keyword' => 'bbbbb',
        ]));
    }
}
