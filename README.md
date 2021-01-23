# Payment integrations for Laravel projects

[![Total Downloads](https://img.shields.io/packagist/dt/magedahmad/larapayment.svg?style=flat-square)](https://packagist.org/packages/magedahmad/larapayment) [![Latest Version](https://img.shields.io/github/v/tag/MagedAhmad/LaraPayment?sort=semver&label=version)](https://github.com/MagedAhmad/LaraPayment/)
[![Latest Version](https://img.shields.io/packagist/v/magedahmad/larapayment?label=version)](https://packagist.org/packages/magedahmad/larapayment/)
[![Development Branch](https://img.shields.io/badge/development_branch-master-green.svg)](https://github.com/MagedAhmad/LaraPayment/tree/master/)
[![Made With](https://img.shields.io/badge/made_with-php-blue)](/docs/requirements/)


A package to handle different payment gateways 

## Installation

You can install the package via composer:

```bash
composer require magedahmad/larapayment
```
under `providers` in `config/app/php` add this line
```php
MagedAhmad\LaraPayment\LaraPaymentServiceProvider::class,
```
in terminal publish the migration and config file with
```bash
php artisan vendor:publish
```
and migrate the db table 
```bash
php artisan migrate
```
in `app/config/larapament.php` you need to modify the API keys

## Usage

`paymob` instructions

``` php
use MagedAhmad\LaraPayment\LaraPayment;


$payment = new LaraPayment();

// payment gateway = paymob
// amount to pay in usd = 100$
$payment->make_payment("paymob", 100);
```

Response would return the `iframe` that you need to include in your blade file 

after completing transaction you would be redirected to a route you specify in [paymob itself](https://docs.paymob.com/docs/transaction-callbacks)

in the function handling the callback url you need to verify the transaction. 

example code:
```php
public function receive(Request $request) 
{
    $laraPayment = new LaraPayment();

    $laraPayment->verify_paymob($request->order, $request->all()); // return status
}
```

And That's it !
### Testing

``` bash
composer test
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email maged.ahmedr@gmail.com instead of using the issue tracker.

## Credits

- [Maged Ahmed](https://github.com/MagedAhmad)
<!-- - [All Contributors](../../contributors) -->

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
