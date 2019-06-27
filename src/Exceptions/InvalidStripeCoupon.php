<?php

namespace Laravel\Cashier\Exceptions;

use Exception;

class InvalidStripeCoupon extends Exception
{
    /**
     * Create a new exception instance.
     *
     * @param  string  $coupon
     * @return self
     */
    public static function invalid($coupon)
    {
        return new static("{$coupon} is not valid.");
    }

    /**
     * Create a new exception instance.
     *
     * @param  string  $coupon
     * @return self
     */
    public static function invalidForCharge($coupon)
    {
        return new static("{$coupon} cannot be used on a charge, please ensure the coupon type is set to once.");
    }
}
