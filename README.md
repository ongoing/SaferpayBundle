# Saferpay - Extension to the JMSPaymentCoreBundle providing access to Saferpay.

This bundle now uses the new [Saferpay JSON API](http://saferpay.github.io/jsonapi)

This is a Fork of [valiton SaferpayBundle](https://github.com/valiton/SaferpayBundle). On top of the parent functionalities, 
it adds the possibility to use the payment process with the [Transaction Interface](http://saferpay.github.io/jsonapi/index.html#ChapterTransaction).

### Install and enable

Install the bundle with composer:

```
composer require ongoing/saferpay-bundle
```

Activate the bundle in your kernel.


```php
<?php
// app/AppKernel.php

// ...
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            // ...
            new Ongoing\Payment\SaferpayBundle\OngoingPaymentSaferpayBundle()
        );
    }

    // ...
}
```

### Example Configuration
```yaml

ongoing_payment_saferpay:
    account: 155432-17638731
    jsonapi_key: JsonApiPwd1_abc
    jsonapi_pwd: API_234234_234234
    cardrefid: random_unique
    authorize_direct: true
```



### saferpay_checkout (JMS ChoosePaymentMethodType Form creation)

```php
<?php

    $form = $this->createForm(ChoosePaymentMethodType::class, null, [
        'amount'   => $order->getAmount(),
        'currency' => 'CHF',
        'predefined_data' => [          
            'saferpay_checkout' => [
                'checkout_params' => [
                    'orderid' => $order->getId(),
                    'description' => $order->getOrderItems()->first()->getName()
                ],
                'return_url' => 'example.com/saferpay/success',
                'cancel_url' => 'example.com/saferpay/cancel',
                'error_url' => 'example.com/saferpay/error'
            ]   
        ],
    ]);
}
```

*URLS can be overwritten or set in predefined data, to generate dynamic URLS for Example!*

To additionally send user data with saferpay initialization, following fields could be added under array key `checkout_params`:
- `firstname`, `lastname`, `street`, `zip`, `languagecode`, `city` and `user_ip`

### Credit Card Data

On a successful response, creditcard data is saved in extended data of the transaction with keys *token, CARDREFID, CARDMASK, CARDBRAND, CARDVALIDMONTH, CARDVALIDYEAR* (take a look at approve method in the SaferpayPlugin).

The generated alias is saved under CARDREFID. The alias could be used to initialize, authorize and capture a transaction in one step. By setting authorize_direct to true or false, additional security step (like 3DS) could be skipped. Be aware of liability reversal.      


To let the bundle use the alias, you only have to set the **creditcard_alias** key (and correct alias as value) on the extended data of a JMSPaymentCoreBundle transaction/paymentinstruction **BEFORE** the transaction is initialized. This could be done by a form listener, a controller or any other service which is able to modify extended data.  

```php
//showAction -> http://jmspaymentcorebundle.readthedocs.io/en/stable/guides/accepting_payments.html
$ppc = $this->get('payment.plugin_controller');
$ppc->createPaymentInstruction($instruction = $form->getData());
$instrucation->getExtendedData()->set('creditcard_alias', 'validcreditcardalias');
```

Be aware using Saferpay Transaction with 'authorize_direct', the redirect to a success/failure/error page needs to be implemented manually.

### Configuration Reference

Configure the bundle according to your needs, full config example:

```
valiton_payment_saferpay:
    account: <some account>                 # your saferpay account, usually account-terminalid (e.g. 123562-32173665)
    jsonapi_key: <some key>                 # API key generated  through saferpay backend
    jsonapi_pwd: <some pw>                  # API password generated through saferpay backend
    return_url: <some url>                  # url called on successfull payment
    error_url: <some url>                   # url called on error
    cancel_url: <some url>                  # url called on user cancel
    saferpay_test: true                     # use the saferpay test system at test.saferpay.com
    cardrefid: new|random|random_unique     # create card alias: new: alias is generated by saferpay, random: alias is generated by us, random_unique: 
    cardrefid_prefix: TST                   # prefix of the randomly generated alias
    cardrefid_length: 33                    # length of the randomly generated alias (inclusive prefix)
    authorize_direct: false                 # if Transaction Interface with CC alias is used, this option forces authorizeDirect method. A Transaction is initialized, authorized and captured without user interaction. 
```


