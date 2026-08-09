<?php

/*
 * This file is part of bendary/flarum-admin-audit.
 *
 * Copyright (c) 2026 Amr Bendary.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Bendary\AdminAudit;

use Flarum\Extend;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less')
        ->route('/audit', 'admin-audit'),

    (new Extend\Routes('api'))
        ->get('/admin_audit_logs', 'admin-audit.index', Controllers\ListAuditLogsController::class),

    new Extend\Locales(__DIR__ . '/locale'),

    (new Extend\Middleware('api'))
        ->add(Middlewares\CaptureRequestMiddleware::class)
        ->add(Middlewares\AuditAdminActionsMiddleware::class),

    (new Extend\Event())
        ->subscribe(Listeners\AuditLogEvents::class),
];
