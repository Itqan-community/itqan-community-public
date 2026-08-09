<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('admin_audit_logs')) {
            return;
        }

        $schema->create('admin_audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('category')->index(); // settings, extensions, permissions, system
            $table->string('action'); // update, enable, disable, delete, login, etc.
            $table->string('target')->nullable();
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip')->nullable();
            $table->timestamp('created_at')->index();

            // Note: Flarum's user IDs are integers, some setups might use bigInteger, 
            // but standard Flarum is integer for `users.id`.
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('admin_audit_logs');
    }
];
