<?php

namespace Tests\Unit;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\DBTestCase;
use Tests\Utils\Models\User;

class GetCountTest extends DBTestCase
{
    public function test_get_count()
    {
        $inserCount = 33;
        factory(User::class, $inserCount)->create();

        $this->assertEquals($inserCount, User::getCount());
    }
}
