<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Paymob\Laravel\Billing\PlanManager;
use Paymob\Laravel\Billing\SubscriptionManager;
use Paymob\Laravel\Enums\Currency;
use Paymob\Laravel\Enums\PlanFrequency;
use Paymob\Laravel\Enums\PlanType;
use Paymob\Laravel\Enums\Region;
use Paymob\Laravel\Enums\SubscriptionStatus;
use Paymob\Laravel\Enums\TransactionStatus;
use Paymob\Laravel\Enums\WebhookType;
use Paymob\Laravel\Events\PaymentMethodUpdated;
use Paymob\Laravel\Events\SubscriptionUpdated;
use Paymob\Laravel\Events\TransactionUpdated;
use Paymob\Laravel\Events\WebhookHandled;
use Paymob\Laravel\Exceptions\ApiException;
use Paymob\Laravel\Exceptions\ConfigurationException;
use Paymob\Laravel\Exceptions\InvalidSignatureException;
use Paymob\Laravel\Exceptions\PaymobException;
use Paymob\Laravel\Facades\Paymob as PaymobFacade;
use Paymob\Laravel\Http\PaymobHttpClient;
use Paymob\Laravel\Http\TokenManager;
use Paymob\Laravel\Models\PaymentMethod;
use Paymob\Laravel\Models\Plan;
use Paymob\Laravel\Models\Subscription;
use Paymob\Laravel\Models\Transaction;
use Paymob\Laravel\Models\WebhookCall;
use Paymob\Laravel\Paymob;
use Paymob\Laravel\Resources\Intentions;
use Paymob\Laravel\Resources\Payments;
use Paymob\Laravel\Resources\QuickLinks;
use Paymob\Laravel\Resources\SavedCards;
use Paymob\Laravel\Resources\SubscriptionPlans;
use Paymob\Laravel\Resources\Subscriptions;
use Paymob\Laravel\Resources\Transactions;

beforeEach(function () {
    $this->setUpConfig();
});

it('resolves Paymob facade to Paymob class', function () {
    expect(class_exists(PaymobFacade::class))->toBeTrue();
    $accessor = (new ReflectionMethod(PaymobFacade::class, 'getFacadeAccessor'))->invoke(null);
    expect($accessor)->toBe(Paymob::class);
});

it('resolves Paymob from the container', function () {
    expect(app(Paymob::class))->toBeInstanceOf(Paymob::class);
});

it('makes Billable trait available', function () {
    $this->ensureUserTable();
    $billable = $this->createBillable(['name' => 'Test', 'email' => 'test@example.com']);
    $billable->save();
    expect(method_exists($billable, 'paymobSubscriptions'))->toBeTrue();
});

it('has all Currency enum cases', function () {
    expect(Currency::EGP->value)->toBe('EGP');
    expect(Currency::SAR->value)->toBe('SAR');
    expect(Currency::AED->value)->toBe('AED');
    expect(Currency::OMR->value)->toBe('OMR');
    expect(Currency::USD->value)->toBe('USD');
});

it('has all PlanFrequency enum cases', function () {
    expect(PlanFrequency::WEEKLY->value)->toBe(7);
    expect(PlanFrequency::BIWEEKLY->value)->toBe(15);
    expect(PlanFrequency::MONTHLY->value)->toBe(30);
    expect(PlanFrequency::BIMONTHLY->value)->toBe(60);
    expect(PlanFrequency::QUARTERLY->value)->toBe(90);
    expect(PlanFrequency::SEMIANNUAL->value)->toBe(180);
    expect(PlanFrequency::ANNUAL->value)->toBe(360);
});

it('has all PlanType enum cases', function () {
    expect(PlanType::RENT->value)->toBe('rent');
    expect(PlanType::INSTALLMENT->value)->toBe('installment');
    expect(PlanType::PURCHASE->value)->toBe('purchase');
    expect(PlanType::BUNDLE->value)->toBe('bundle');
    expect(PlanType::MERCHANT_SUBSCRIPTION->value)->toBe('merchant_subscription');
    expect(PlanType::OTHER->value)->toBe('other');
});

it('has all Region enum cases', function () {
    expect(Region::EGYPT->value)->toBe('egypt');
    expect(Region::KSA->value)->toBe('ksa');
    expect(Region::UAE->value)->toBe('uae');
    expect(Region::OMAN->value)->toBe('oman');
});

it('has all SubscriptionStatus enum cases', function () {
    expect(SubscriptionStatus::INCOMPLETE->value)->toBe('incomplete');
    expect(SubscriptionStatus::INCOMPLETE_EXPIRED->value)->toBe('incomplete_expired');
    expect(SubscriptionStatus::ACTIVE->value)->toBe('active');
    expect(SubscriptionStatus::PAST_DUE->value)->toBe('past_due');
    expect(SubscriptionStatus::SUSPENDED->value)->toBe('suspended');
    expect(SubscriptionStatus::CANCELED->value)->toBe('canceled');
    expect(SubscriptionStatus::EXPIRED->value)->toBe('expired');
});

it('has all TransactionStatus enum cases', function () {
    expect(TransactionStatus::PENDING->value)->toBe('pending');
    expect(TransactionStatus::SUCCEEDED->value)->toBe('succeeded');
    expect(TransactionStatus::FAILED->value)->toBe('failed');
    expect(TransactionStatus::REFUNDED->value)->toBe('refunded');
    expect(TransactionStatus::VOIDED->value)->toBe('voided');
    expect(TransactionStatus::CAPTURED->value)->toBe('captured');
});

it('has all WebhookType enum cases', function () {
    expect(WebhookType::TRANSACTION->value)->toBe('transaction');
    expect(WebhookType::TOKEN->value)->toBe('token');
    expect(WebhookType::SUBSCRIPTION->value)->toBe('subscription');
    expect(WebhookType::UNKNOWN->value)->toBe('unknown');
});

it('instantiates Plan model', function () {
    $plan = new Plan;
    expect($plan)->toBeInstanceOf(Model::class);
    expect($plan->getTable())->toBe('paymob_plans');
});

it('instantiates Subscription model', function () {
    $subscription = new Subscription;
    expect($subscription)->toBeInstanceOf(Model::class);
    expect($subscription->getTable())->toBe('paymob_subscriptions');
});

it('instantiates Transaction model', function () {
    $transaction = new Transaction;
    expect($transaction)->toBeInstanceOf(Model::class);
    expect($transaction->getTable())->toBe('paymob_transactions');
});

it('instantiates PaymentMethod model', function () {
    $paymentMethod = new PaymentMethod;
    expect($paymentMethod)->toBeInstanceOf(Model::class);
    expect($paymentMethod->getTable())->toBe('paymob_payment_methods');
});

it('instantiates WebhookCall model', function () {
    $webhookCall = new WebhookCall;
    expect($webhookCall)->toBeInstanceOf(Model::class);
    expect($webhookCall->getTable())->toBe('paymob_webhook_calls');
});

it('config file has all expected keys', function () {
    $config = config('paymob');
    expect($config)->toHaveKey('region');
    expect($config)->toHaveKey('keys');
    expect($config)->toHaveKey('integrations');
    expect($config)->toHaveKey('checkout');
    expect($config)->toHaveKey('http');
    expect($config)->toHaveKey('webhooks');
    expect($config)->toHaveKey('models');
    expect($config)->toHaveKey('tables');
});

it('config file has expected nested keys', function () {
    expect(config('paymob.keys'))->toHaveKeys(['secret', 'public', 'api', 'hmac']);
    expect(config('paymob.integrations'))->toHaveKeys(['default', 'card_3ds', 'moto', 'verification_3ds']);
    expect(config('paymob.webhooks'))->toHaveKeys(['enabled', 'prefix', 'middleware', 'verify_transaction_hmac', 'subscription_secret', 'dispatch_after_commit', 'retain_days']);
    expect(config('paymob.models'))->toHaveKeys(['plan', 'subscription', 'transaction', 'payment_method', 'webhook_call']);
    expect(config('paymob.tables'))->toHaveKeys(['plans', 'subscriptions', 'transactions', 'payment_methods', 'webhook_calls']);
});

it('config models reference correct class names', function () {
    expect(config('paymob.models.plan'))->toBe(Plan::class);
    expect(config('paymob.models.subscription'))->toBe(Subscription::class);
    expect(config('paymob.models.transaction'))->toBe(Transaction::class);
    expect(config('paymob.models.payment_method'))->toBe(PaymentMethod::class);
    expect(config('paymob.models.webhook_call'))->toBe(WebhookCall::class);
});

it('registers webhook routes when webhooks enabled', function () {
    expect(Route::has('paymob.webhooks.handle'))->toBeTrue();
    expect(Route::has('paymob.webhooks.transaction'))->toBeTrue();
    expect(Route::has('paymob.webhooks.token'))->toBeTrue();
    expect(Route::has('paymob.webhooks.subscription'))->toBeTrue();
});

it('instantiates ApiException', function () {
    $exception = new ApiException(422, ['message' => 'Error']);
    expect($exception)->toBeInstanceOf(PaymobException::class);
    expect($exception->status)->toBe(422);
});

it('instantiates ConfigurationException', function () {
    $exception = new ConfigurationException('Missing key');
    expect($exception)->toBeInstanceOf(PaymobException::class);
    expect($exception->getMessage())->toBe('Missing key');
});

it('instantiates InvalidSignatureException', function () {
    $exception = new InvalidSignatureException('Bad signature');
    expect($exception)->toBeInstanceOf(PaymobException::class);
    expect($exception->getMessage())->toBe('Bad signature');
});

it('creates SubscriptionUpdated event', function () {
    $subscription = new Subscription;
    $event = new SubscriptionUpdated($subscription);
    expect($event->subscription)->toBe($subscription);
});

it('creates TransactionUpdated event', function () {
    $transaction = new Transaction;
    $event = new TransactionUpdated($transaction);
    expect($event->transaction)->toBe($transaction);
});

it('creates PaymentMethodUpdated event', function () {
    $paymentMethod = new PaymentMethod;
    $event = new PaymentMethodUpdated($paymentMethod);
    expect($event->paymentMethod)->toBe($paymentMethod);
});

it('creates WebhookHandled event', function () {
    $webhookCall = new WebhookCall;
    $event = new WebhookHandled($webhookCall);
    expect($event->webhookCall)->toBe($webhookCall);
});

it('registers Paymob singleton binding', function () {
    expect(app()->bound(Paymob::class))->toBeTrue();
});

it('registers paymob alias', function () {
    expect(app()->bound('paymob'))->toBeTrue();
    expect(app('paymob'))->toBeInstanceOf(Paymob::class);
});

it('registers PaymobHttpClient singleton binding', function () {
    expect(app()->bound(PaymobHttpClient::class))->toBeTrue();
});

it('registers TokenManager singleton binding', function () {
    expect(app()->bound(TokenManager::class))->toBeTrue();
});

it('instantiates resource classes from the container', function () {
    expect(app(Intentions::class))->toBeInstanceOf(Intentions::class);
    expect(app(SubscriptionPlans::class))->toBeInstanceOf(SubscriptionPlans::class);
    expect(app(Subscriptions::class))->toBeInstanceOf(Subscriptions::class);
    expect(app(Transactions::class))->toBeInstanceOf(Transactions::class);
    expect(app(Payments::class))->toBeInstanceOf(Payments::class);
    expect(app(QuickLinks::class))->toBeInstanceOf(QuickLinks::class);
    expect(app(SavedCards::class))->toBeInstanceOf(SavedCards::class);
});

it('Paymob class exposes all resource managers', function () {
    $paymob = app(Paymob::class);
    expect($paymob->intentions())->toBeInstanceOf(Intentions::class);
    expect($paymob->subscriptionPlans())->toBeInstanceOf(SubscriptionPlans::class);
    expect($paymob->subscriptions())->toBeInstanceOf(Subscriptions::class);
    expect($paymob->transactions())->toBeInstanceOf(Transactions::class);
    expect($paymob->payments())->toBeInstanceOf(Payments::class);
    expect($paymob->quickLinks())->toBeInstanceOf(QuickLinks::class);
    expect($paymob->savedCards())->toBeInstanceOf(SavedCards::class);
    expect($paymob->plans())->toBeInstanceOf(PlanManager::class);
    expect($paymob->billing())->toBeInstanceOf(SubscriptionManager::class);
});

it('Paymob facade delegates to Paymob class', function () {
    expect(PaymobFacade::intentions())->toBeInstanceOf(Intentions::class);
    expect(PaymobFacade::plans())->toBeInstanceOf(PlanManager::class);
});
