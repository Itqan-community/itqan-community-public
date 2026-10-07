# Arabic language pack for [Flarum](https://flarum.org/)

This language pack contains Arabic translations for Flarum (compatible with `1.3.0` or newer) and popular extensions. Full list of supported extensions is available below.


## Installation

You can install language pack using [Composer](https://getcomposer.org/):


## manually installation steps :

### 1. add package to flarum composer file

```
        "repositories": [
            {
                "type": "vcs",
                "url":  "git@github.com:Itqan-community/flarum-lang-arabic.git",
                "no-api": true
            }
        ]
```
### 2. install package
```console
composer require flarum-locale/arabic:dev-main
```

Then enable extension in admin panel of your Flarum.


## Updating

You can update language pack using [Composer](https://getcomposer.org/):

```console
composer update flarum-lang/arabic
```

Then clear the cache:

```console
php flarum cache:clear
```

