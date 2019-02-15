<?php

namespace Tests\Unit;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

class GetCountTest extends DBTestCase
{
    public function test_get_count()
    {
        $inserCount = 18;
        factory(User::class, $inserCount)->create();

        $this->assertEquals($inserCount, User::getCount());
    }
}
