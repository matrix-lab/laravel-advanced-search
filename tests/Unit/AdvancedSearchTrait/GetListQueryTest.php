<?php

namespace Tests\Unit;

use Illuminate\Database\Eloquent\Builder;
use Tests\DBTestCase;
use Tests\Utils\Models\User;

class GetListQueryTest extends DBTestCase
{
    public function test_get_list_query()
    {
        $builder = User::getListQuery();

        $this->assertEquals(Builder::class, get_class($builder));
    }
}
