<?php

namespace Tests\Unit;

use MatrixLab\LaravelAdvancedSearch\When;
use PHPUnit\Framework\TestCase;

class WhenTest extends TestCase
{
    private $successString = 'This is success string';
    private $failString = 'This is fail string';

    public function test_make_closure()
    {
        $when = When::make(function () {
            return true;
        })->success($this->successString);

        $this->assertEquals($this->successString, $when->result());
    }

    public function test_make_closure_with_args()
    {
        $when = When::make(function ($arg1, $arg2) {
            return $arg1 && $arg2;
        }, true, false)->success($this->successString)->fail($this->failString);

        $this->assertEquals($this->failString, $when->result());
        $this->assertNotEquals($this->successString, $when->result());
    }

    public function test_fail()
    {
        $when = When::make(false)->success($this->successString)->fail($this->failString);

        $this->assertEquals($this->failString, $when->result());
        $this->assertNotEquals($this->successString, $when->result());
    }

    public function test_success()
    {
        $when = When::make(true)->success($this->successString)->fail($this->failString);

        $this->assertEquals($this->successString, $when->result());
        $this->assertNotEquals($this->failString, $when->result());
    }

    public function test_success_and_fail_closure()
    {
        $when = When::make(true)->success(function () {
            return $this->successString;
        })->fail(function () {
            return $this->failString;
        });

        $this->assertEquals($this->successString, $when->result()());
        $this->assertEquals($this->successString, $when->result(true));

        $when = When::make(false)->success(function () {
            return $this->successString;
        })->fail(function () {
            return $this->failString;
        });

        $this->assertEquals($this->failString, $when->result()());
        $this->assertEquals($this->failString, $when->result(true));
    }
}
