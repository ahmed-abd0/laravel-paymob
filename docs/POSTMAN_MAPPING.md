# Postman Collection Mapping

The initial package implementation was generated against the supplied Paymob collections.

| Collection | Request | Method | Package method |
|---|---|---:|---|
| Intention APIs | Create intention | `POST` | `Paymob::intentions()->create()` |
| Intention APIs | Unified checkout redirection | `GET` | `Paymob::intentions()->checkoutUrl()` |
| Intention APIs | Update intention | `PUT` | `Paymob::intentions()->update()` |
| Intention APIs | Retrieve intention | `GET` | `Paymob::intentions()->retrieve()` |
| Pay with saved card | create card token → Create intention | `POST` | `Paymob::savedCards()->createTokenIntention()` |
| Pay with saved card | create card token → Unified checkout redirection | `GET` | `Paymob::intentions()->checkoutUrl()` |
| Pay with saved card | CIT  (customer initiated transaction ) → Create intention | `POST` | `Paymob::savedCards()->customerInitiated()` |
| Pay with saved card | CIT  (customer initiated transaction ) → Unified checkout redirection | `GET` | `Paymob::intentions()->checkoutUrl()` |
| Pay with saved card | MIT (merchant initiated transaction) → Create intention | `POST` | `Paymob::savedCards()->merchantInitiatedIntention()` |
| Pay with saved card | MIT (merchant initiated transaction) → Moto_Card_Pay | `POST` | `Paymob::savedCards()->payMoto()` |
| Paymob Subscription Module API | Authentication → Generate Authentication Token | `POST` | `TokenManager (automatic)` |
| Paymob Subscription Module API | Subscription Plans → Create Subscription Plan | `POST` | `Paymob::subscriptionPlans()->create() / Paymob::plans()->create()` |
| Paymob Subscription Module API | Subscription Plans → List Subscription Plans | `GET` | `Paymob::subscriptionPlans()->all()` |
| Paymob Subscription Module API | Subscription Plans → Update Subscription Plan | `PUT` | `Paymob::subscriptionPlans()->update()` |
| Paymob Subscription Module API | Subscription Plans → Suspend Subscription Plan | `POST` | `Paymob::subscriptionPlans()->suspend()` |
| Paymob Subscription Module API | Subscription Plans → Resume Subscription Plan | `POST` | `Paymob::subscriptionPlans()->resume()` |
| Paymob Subscription Module API | Subscriptions → Create Subscription | `POST` | `Billable::newSubscription()->checkout()` |
| Paymob Subscription Module API | Subscriptions → List Subscription Details | `GET` | `Paymob::subscriptions()->find()` |
| Paymob Subscription Module API | Subscriptions → Update Subscription | `PUT` | `Subscription::updateBilling()` |
| Paymob Subscription Module API | Subscriptions → Suspend Subscription | `POST` | `Subscription::suspend()` |
| Paymob Subscription Module API | Subscriptions → Resume Subscription | `POST` | `Subscription::resume()` |
| Paymob Subscription Module API | Subscriptions → Cancel Subscription | `POST` | `Subscription::cancel()` |
| Paymob Subscription Module API | Subscriptions → Get Last Transaction | `GET` | `Paymob::subscriptions()->lastTransaction()` |
| Paymob Subscription Module API | Subscriptions → List Subscription Transactions | `GET` | `Paymob::subscriptions()->transactions()` |
| Paymob Subscription Module API | Subscriptions → List Subscription Cards | `GET` | `Paymob::subscriptions()->cards()` |
| Paymob Subscription Module API | Subscriptions → Add Secondary Card | `POST` | `Paymob::subscriptions()->addCard()` |
| Paymob Subscription Module API | Subscriptions → Delete Subscription Secondary Card | `POST` | `Paymob::subscriptions()->deleteCard()` |
| Paymob Subscription Module API | Subscriptions → change subscription primary card | `POST` | `Paymob::subscriptions()->changePrimaryCard()` |
| Paymob Subscription Module API | Subscriptions → Register Webhook | `POST` | `Paymob::subscriptions()->registerWebhook()` |
| Refund & Void & Capture APIs | Refund | `POST` | `Paymob::payments()->refund()` |
| Refund & Void & Capture APIs | Void | `POST` | `Paymob::payments()->void()` |
| Refund & Void & Capture APIs | Capture | `POST` | `Paymob::payments()->capture()` |
| Transaction Inquiry API | Login ( using API key) | `POST` | `TokenManager (automatic)` |
| Transaction Inquiry API | Retrieve Transaction With Order ID | `POST` | `Paymob::transactions()->byOrder()` |
| Transaction Inquiry API | Retreive Transaction With Transaction ID | `GET` | `Paymob::transactions()->find()` |
| V2 QuickLink API | Login | `POST` | `TokenManager (automatic)` |
| V2 QuickLink API | Create Payment Link | `POST` | `Paymob::quickLinks()->create()` |
| V2 QuickLink API | Quick Link Cancellation | `POST` | `Paymob::quickLinks()->cancel()` |
