<?php

namespace SteadfastCollective\CashierExtended;

use Carbon\Carbon;
use DateTimeInterface;
use Laravel\Cashier\Exceptions\SubscriptionCreationFailed;

class ChargeBuilder
{
    /**
     * The model that is being charged.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $owner;

    /**
     * The amount of the charge in single units (eg. $19.99 would be 1999).
     *
     * @var integer
     */
    protected $amount;

    /**
     * The name of the charge.
     *
     * @var string
     */
    protected $name;

    /**
     * The currency of the charge.
     *
     * @var string
     */
    protected $currency;

    /**
     * The quantity of the subscription.
     *
     * @var int
     */
    protected $quantity = 1;

    /**
     * The coupon code being applied to the customer.
     *
     * @var string|null
     */
    protected $coupon;

    /**
     * The metadata to apply to the subscription.
     *
     * @var array|null
     */
    protected $metadata;

    /**
     * If the charge generates an invoice.
     *
     * @var boolean|null
     */
    protected $invoicable = false;

    /**
     * Create a new subscription builder instance.
     *
     * @param  mixed  $owner
     * @param  string  $name
     * @param  string  $plan
     * @return void
     */
    public function __construct($owner, $name, $amount)
    {
        $this->owner = $owner;
        $this->name = $name;
        $this->amount = $amount;
        $this->currency = $this->owner->preferredCurrency();
    }

    /**
     * Specify if the charge should generate an invoice.
     *
     * @param  boolean  $invoiced
     * @return $this
     */
    public function withInvoice($invoicable = true)
    {
        $this->invoicable = $invoicable;

        return $this;
    }

    /**
     * Specify the currency of the charge.
     *
     * @param  int  $currency
     * @return $this
     */
    public function currency($currency)
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * Specify the quantity of the subscription.
     *
     * @param  int  $quantity
     * @return $this
     */
    public function quantity($quantity)
    {
        $this->quantity = $quantity;

        return $this;
    }

    /**
     * The coupon to apply to a new subscription.
     *
     * @param  string  $coupon
     * @return $this
     */
    public function withCoupon($coupon)
    {
        $this->coupon = $coupon;

        return $this;
    }

    /**
     * The metadata to apply to a new subscription.
     *
     * @param  array  $metadata
     * @return $this
     */
    public function withMetadata($metadata)
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Add a new Stripe charge to the Stripe model.
     *
     * @param  array  $options
     * @return \SteadfastCollective\CashierExtended\Charge
     */
    public function add(array $options = [])
    {
        return $this->create(null, $options);
    }

    /**
     * Create a new Stripe charge.
     *
     * @param  string|null  $token
     * @param  array  $options
     * @return \SteadfastCollective\CashierExtended\Charge|\Laravel\Cashier\Invoice
     */
    public function create($token = null, array $options = [])
    {
        if (!$this->invoicable) {
            return $this->createWithoutInvoice($token, $options);
        }

        return $this->createWithInvoice($token, $options);
    }

    /**
     * Create a new Stripe single payment witout an invoice.
     *
     * @param  string|null  $token
     * @param  array  $options
     * @return \SteadfastCollective\CashierExtended\Charge
     */
    public function createWithoutInvoice($token = null, array $options = [])
    {
        // if (! array_key_exists('source', $options) && $this->stripe_id) {
        //     $options['customer'] = $this->stripe_id;
        // }
        //
        // if (! array_key_exists('source', $options) && ! array_key_exists('customer', $options)) {
        //     throw new InvalidArgumentException('No payment source provided.');
        // }
        //
        // return StripeCharge::create($this->buildPayload(), ['api_key' => $this->getStripeKey()]);

        $options = array_merge([
            'confirmation_method' => 'automatic',
            'confirm' => true,
            'currency' => $this->currency,
        ], $options);

        $options['amount'] = $this->calculateFinalAmount();

        $options['customer'] = $this->getStripeCustomer($token)->id;

        if (! array_key_exists('payment_method', $options) && ! array_key_exists('customer', $options)) {
            throw new InvalidArgumentException('No payment method provided.');
        }

        $payment = new Payment(
            StripePaymentIntent::create($options, Cashier::stripeOptions())
        );

        $payment->validate();

        return $payment;
    }

    /**
     * Create a new Stripe single payment with an invoice.
     *
     * @param  string|null  $token
     * @param  array  $options
     * @return \Laravel\Cashier\Invoice
     */
    public function createWithInvoice($token = null, array $options = [])
    {
        //
    }

    /**
     * Get the Stripe customer instance for the current user and token.
     *
     * @param  string|null  $token
     * @param  array  $options
     * @return \Stripe\Customer
     */
    protected function getStripeCustomer($token = null, array $options = [])
    {
        $customer = $this->owner->createOrGetStripeCustomer($options);

        if ($token) {
            $this->owner->updateCard($token);
        }

        return $customer;
    }

    protected function calculateFinalAmount()
    {
        return ($this->amount * $this->quantity);
    }

    /**
     * Get the tax percentage for the Stripe payload.
     *
     * @return int|float|null
     */
    protected function getTaxPercentageForPayload()
    {
        if ($taxPercentage = $this->owner->taxPercentage()) {
            return $taxPercentage;
        }
    }
}
