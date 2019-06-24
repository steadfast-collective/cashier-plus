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
     * Get the array form of the coupon.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'id' => $this->id,
            'amount_off' => $this->amountOff,
            'duration' => $this->duration,
            'duration_in_months' => $this->durationInMonths,
            'percent_off' => $this->percentOff,
        ];
    }

    /**
     * Get the Stripe invoice instance.
     *
     * @return \Stripe\Invoice
     */
    public function asStripeCoupon()
    {
        return $this->invoice;
    }
}
