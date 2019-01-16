<?php

namespace Tests\Unit;

use Tests\DBTestCase;
use Tests\Utils\Models\User;
use Illuminate\Database\Eloquent\Builder;

class GetListQueryTest extends DBTestCase
{
    public function test_get_list_query()
    {
        $builder = User::getListQuery();

        $this->assertEquals(Builder::class, get_class($builder));
    }
}
