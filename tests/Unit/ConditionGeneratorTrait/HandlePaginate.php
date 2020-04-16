<?php

namespace Tests\Unit\ConditionGeneratorTrait;

use Illuminate\Support\Arr;
use Tests\DBTestCase;
use Tests\Utils\FakeRequest;
use Tests\Utils\Models\User;

class HandlePaginate extends DBTestCase
{
    public function test_sorts()
    {
        $fakeRequest = new FakeRequest();
        $conditions = $fakeRequest->getConditions([
            'paginator' => [
                'sorts' => [
                    '+rank',
                    '-id',
                ],
            ],
        ]);

        $this->assertEquals([
            'rank' => 'asc',
            'id' => 'desc',
        ], Arr::get($conditions, 'order'));
    }

    public function test_order()
    {
        $fakeRequest = new FakeRequest();
        $conditions = $fakeRequest->getConditions([]);

        $this->assertEquals([
            'id' => 'asc',
        ], Arr::get($conditions, 'order'));
    }
}
