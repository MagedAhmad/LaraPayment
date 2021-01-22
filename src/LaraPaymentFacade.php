<?php

namespace MagedAhmad\LaraPayment;

use Illuminate\Support\Facades\Facade;

/**
 * @see \MagedAhmad\LaraPayment\Skeleton\SkeletonClass
 */
class LaraPaymentFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'larapayment';
    }
}
