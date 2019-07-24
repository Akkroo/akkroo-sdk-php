# Akkroo SDK for PHP

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Build Status][ico-travis]][link-travis]
[![Coverage Status][ico-scrutinizer]][link-scrutinizer]
[![Quality Score][ico-code-quality]][link-code-quality]
[![Total Downloads][ico-downloads]][link-downloads]

The **Akkroo SDK for PHP** allows developers to easily integrate Akkroo services into their applications. It provides access to our [REST API][link-akkroo-api] using handy PHP objects and interfaces that follow the [PSR-2 Coding Standard][link-psr2-style].

## Install

Via Composer

``` bash
$ composer require akkroo/akkroo-sdk-php
```

## Usage

``` php
// Use your favourite PSR-7 HTTP client
$http = new Some\Psr7\HTTPClient();

// Create an Akkroo client with your API key
$akkroo = new Client($http, 'YourAkkrooAPIUsername', 'YourAkkrooAPIKey')->login();

// Do awesome stuff...
var_dump($akkroo->test());
```

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Testing

``` bash
$ composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CONDUCT](CONDUCT.md) for details.

## Security

If you discover any security related issues, please email [dev@akkroo.com](mailto:dev@akkroo.com) instead of using the issue tracker.

## Credits

- [Alex Roche][link-author-alex]
- [Ian Edwards][link-author-ian]
- [Rodolfo Almeida][link-author-rodolfo]
- [Tom Bakowski][link-author-tom]
- [Vito Tardia][link-author-vito]
- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/akkroo/akkroo-sdk-php.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/akkroo/akkroo-sdk-php/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/akkroo/akkroo-sdk-php.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/akkroo/akkroo-sdk-php.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/akkroo/akkroo-sdk-php.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/akkroo/akkroo-sdk-php
[link-travis]: https://travis-ci.org/akkroo/akkroo-sdk-php
[link-scrutinizer]: https://scrutinizer-ci.com/g/akkroo/akkroo-sdk-php/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/akkroo/akkroo-sdk-php
[link-downloads]: https://packagist.org/packages/akkroo/akkroo-sdk-php
[link-author-alex]: https://github.com/AMRoche
[link-author-ian]: https://github.com/cagedagain
[link-author-rodolfo]: https://github.com/rodlfal
[link-author-tom]: https://github.com/TomAkkroo
[link-author-vito]: https://github.com/vtardia
[link-contributors]: ../../contributors
[link-akkroo-api]: http://docs.akkroo.com/developers/api
[link-psr2-style]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md
