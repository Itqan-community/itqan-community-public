# Flarum Admin Audit

[![MIT license](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/Amr-Bendary/Admin-Audit/blob/main/LICENSE)
[![Latest Stable Version](https://img.shields.io/packagist/v/bendary/flarum-admin-audit.svg)](https://packagist.org/packages/bendary/flarum-admin-audit)
[![Total Downloads](https://img.shields.io/packagist/dt/bendary/flarum-admin-audit.svg)](https://packagist.org/packages/bendary/flarum-admin-audit)

A Flarum extension that provides a modern, professional audit logging system with a native Mithril-based admin dashboard UI. Keep track of what happens in your admin panel effortlessly.

## 🚀 Features

- **Modern Dashboard**: A slick, Mithril-based admin interface to visualize your forum's administrative actions.
- **Deep Logging**: Records extension changes, settings updates, and other critical admin events.
- **Detailed Insights**: View before/after JSON data diffs in a slide-out drawer for every logged event (when applicable).
- **Advanced Filtering**: Search and filter by category, admin user, or date ranges.
- **Secure**: Only accessible to admins via standard Flarum permissions. Backend endpoints are securely locked down.
- **Bilingual**: Fully localized natively for both English (LTR) and Arabic (RTL).

## 📦 Installation

Install with composer:

```bash
composer require bendary/flarum-admin-audit
```

## 🔄 Updating

```bash
composer update bendary/flarum-admin-audit
php flarum migrate
php flarum cache:clear
```

## 🗑️ Remove

```bash
composer remove bendary/flarum-admin-audit
```

## 🔗 Links

- [Packagist](https://packagist.org/packages/bendary/flarum-admin-audit)
- [GitHub](https://github.com/Amr-Bendary/Admin-Audit)

## ⚖️ License
[MIT License](LICENSE)
