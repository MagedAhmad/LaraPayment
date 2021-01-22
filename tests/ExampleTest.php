<?php

namespace MagedAhmad\LaraPayment\Tests;

use Orchestra\Testbench\TestCase;
use MagedAhmad\LaraPayment\LaraPaymentServiceProvider;

class ExampleTest extends TestCase
{

    protected function getPackageProviders($app)
    {
        return [LaraPaymentServiceProvider::class];
    }
    
    /** @test */
    public function true_is_true()
    {
        $this->assertTrue(true);
    }
}
