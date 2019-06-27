<?php

namespace Laravel\Cashier;

use Carbon\Carbon;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\View;
use Stripe\Charge as StripeCharge;
use Symfony\Component\HttpFoundation\Response;

class Charge
{
    /**
     * The Stripe model instance.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $owner;

    /**
     * The Stripe invoice instance.
     *
     * @var \Stripe\Charge
     */
    protected $charge;

    /**
     * Create a new invoice instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @param  \Stripe\Charge  $charge
     * @return void
     */
    public function __construct($owner, StripeCharge $charge)
    {
        $this->owner = $owner;
        $this->charge = $charge;
    }

    /**
     * Get the Stripe coupon instance.
     *
     * @return \Stripe\Coupon
     */
    public function asStripeCharge()
    {
        return $this->charge;
    }

    /**
     * Dynamically get values from the Stripe coupon.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->charge->{$key};
    }
}
