# Newsletter

![License](https://img.shields.io/badge/license-MIT-blue.svg) [![Latest Stable Version](https://img.shields.io/packagist/v/justoverclock/newsletter.svg)](https://packagist.org/packages/justoverclock/newsletter) [![Total Downloads](https://img.shields.io/packagist/dt/justoverclock/newsletter.svg)](https://packagist.org/packages/justoverclock/newsletter)

A [Flarum](http://flarum.org) extension. Add a simple newsletter system to Flarum.

This extension allow admin to collect emails and to send newsletter for all those users who are
subscribed at once!
This extension have an automatic opt-out system with an "Unsubscribe" link placed in the newsletter
footer (See picture), that redirect user to a dedicated page where is possible to confirm the opt out through a form.

### Simple email template

![img](https://i.ibb.co/4fHzjN4/emailtemplate.png)

### Dedicated opt-out page

![img2](https://i.ibb.co/9YXsT7N/dedicated.png)

### Simple admin interface

![img3](https://i.ibb.co/MckMVv3/aaaaa.png)

### Simple user interface

![img3](https://i.ibb.co/JFW0Gnz/emailsubscribe.png)

### GDPR compliant
Anyone can subscribe to the newsletter after reading and confirming the rules.

![img3](https://i.ibb.co/yn1grLN/emailsubscribemodal.png)

## Installation

Install with composer:

```sh
composer require justoverclock/newsletter:"*"
```

## Updating

```sh
composer update justoverclock/newsletter:"*"
php flarum migrate
php flarum cache:clear
```

## Links

- [Packagist](https://packagist.org/packages/justoverclock/newsletter)
- [GitHub](https://github.com/justoverclock/newsletter)
- [Discuss](https://discuss.flarum.org/d/PUT_DISCUSS_SLUG_HERE)
