# Saferpay - Extension to the JMSPaymentCoreBundle providing access to Saferpay.

This bundle now uses the new [Saferpay JSON API](http://saferpay.github.io/jsonapi/1.2/) 

Installation
------------

Install the bundle with composer:

```
composer require valiton/saferpay-bundle
```

and activate the bundle in your kernel.


Configuration
-------------

Configure the bundle according to your needs, full config example:

```
valiton_payment_saferpay:
    account: <some account>  # your saferpay account, usually account-terminalid
    jsonapi_key: <some key>  # API key generated  through saferpay backend
    jsonapi_pwd: <some pw>   # API password generated through saferpay backend
    return_url: <some url>   # url called on successfull payment
    error_url: <some url>    # url called on error
    cancel_url: <some url>   # url called on user cancel
    saferpay_test: true      # use the saferpay test system at test.saferpay.com
    cardrefid: new|random    # create card alias: new: alias is generated by saferpay, random: alias is generated by us
    cardrefid_prefix: TST    # prefix of the randomly generated alias
    cardrefid_length: 33     # length of the randomly generated alias (inclusive prefix)
```
