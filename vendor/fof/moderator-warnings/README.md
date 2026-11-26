# Flarum Moderator Warnings

![License](https://img.shields.io/badge/license-MIT-blue.svg) [![Latest Stable Version](https://img.shields.io/packagist/v/fof/moderator-warnings.svg)](https://packagist.org/packages/fof/moderator-warnings)

A [Flarum](http://flarum.org) extension. This allows moderators to warn users. Warnings can be applied from a post or from the user's profile. Each warning contains a number of strikes (0 - 5), a message visible to the user, and a message visible only to other moderators. Users will recieve notifications for warnings they recieve.

### TODO

- Suspend integration (automatically suspend if users reach more than X warnings, with X configurable in settings)

### Installation

Install manually with composer:

```sh
composer require fof/moderator-warnings:"*"
```

### Updating

```sh
composer update fof/moderator-warnings
```

### Feedback and Bugs

Please feel free to report any feedback/bugs/feature ideas on the discuss thread, or as an issue on the github!

### Credit

The base UI for this extension is based on, and reuses some code from, Friends of Flarum's Moderator Notes extension. Thank you to Ian Morland and all the folks at GiffGaff for their hard work on this!

### Links

- [Packagist](https://github.com/FriendsOfFlarum/moderator-warnings)
- [Github](https://github.com/askvortsov1/flarum-moderator-warnings)
