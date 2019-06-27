<?php

namespace Laravel\Cashier;

use Stripe\Coupon as StripeCoupon;
use Stripe\InvoiceItem as StripeInvoiceItem;
use Stripe\PaymentIntent as StripePaymentIntent;

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
     * @var int
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
     * The quantity of the charge.
     *
     * @var int
     */
    protected $quantity = 1;

    /**
     * The coupon code being applied to the charge.
     *
     * @var string|null
     */
    protected $coupon;

    /**
     * The metadata to apply to the charge.
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
        $stripeCoupon = StripeCoupon::retrieve($coupon, Cashier::stripeOptions());

        $this->coupon = new Coupon($stripeCoupon);

        if (!$this->coupon->validForCharges()) {
            throw new \Exception("Coupon not valid for charge.", 1);
        }

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
        $options = array_merge([
            'confirmation_method' => 'automatic',
            'confirm' => true,
            'currency' => $this->currency,
        ], $options);

        $options['amount'] = $this->calculateFinalAmount() * $this->quantity;

        $options['customer'] = $this->getStripeCustomer($token)->id;

        $options['metadata'] = $this->metadata;

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
        $options = array_merge([
            'customer' => $this->getStripeCustomer($token)->id,
            'unit_amount' => $this->calculateFinalAmount(),
            'quantity' => $this->quantity,
            'currency' => $this->currency,
            'description' => $this->name,
        ], $options);

        $tab = StripeInvoiceItem::create($options, Cashier::stripeOptions());

        return $this->owner->invoice([]);
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
        $amount = $this->amount;

        if ($this->coupon && $this->coupon->validForCharges()) {
            if ($this->coupon->amount_off !== null) {
                $amount = $amount - $this->coupon->amount_off;
            } elseif ($this->coupon->percent_off !== null) {
                $amount = $amount * ($this->coupon->percent_off / 100);
            }
        }

        $amount = (int) (round($amount));

        return $amount;
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

    /**
     * Build the payload for subscription creation.
     *
     * @return array
     */
    protected function buildPayload()
    {
        return array_filter([
            'metadata' => $this->metadata,
            'plan' => $this->plan,
            'quantity' => $this->quantity,
            'tax_percent' => $this->getTaxPercentageForPayload(),
            'trial_end' => $this->getTrialEndForPayload(),
            'expand' => ['latest_invoice.payment_intent'],
        ]);
    }

}
