<?php

namespace Laravel\Cashier;

use Stripe\Coupon as StripeCoupon;

class Coupon
{
    /**
     * The Stripe coupon instance.
     *
     * @var \Stripe\Coupon
     */
    protected $coupon;

    /**
     * Create a new coupon instance.
     *
     * @param  StripeCoupon  $coupon
     * @return void
     */
    public function __construct(StripeCoupon $coupon)
    {
        $this->coupon = $coupon;
    }

    /**
     * Get whether the coupon can be applied to a charge.
     *
     * @return array
     */
    public function validForCharges()
    {
        return $this->coupon->duration == "once";
    }

    /**
     * Get the array form of the coupon.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'id' => $this->charge->id,
            'amount_off' => $this->charge->amount_off,
            'duration' => $this->charge->duration,
            'duration_in_months' => $this->charge->duration_in_months,
            'percent_off' => $this->charge->percent_off,
        ];
    }

    /**
     * Get the Stripe coupon instance.
     *
     * @return \Stripe\Coupon
     */
    public function asStripeCoupon()
    {
        return $this->coupon;
    }

    /**
     * Dynamically get values from the Stripe Coupon.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->coupon->{$key};
    }
}
