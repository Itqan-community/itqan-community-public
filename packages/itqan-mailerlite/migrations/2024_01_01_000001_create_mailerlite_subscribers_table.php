<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->create('mailerlite_subscribers', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->primary();
            $table->string('mailerlite_subscriber_id', 50)->nullable();
            $table->string('email', 255);
            $table->string('sync_status', 20)->default('pending'); // 'pending', 'synced', 'failed', 'unsubscribed'
            $table->json('groups')->nullable(); // Array of group names the user is in
            $table->timestamp('first_post_at')->nullable(); // Track when user made first post
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->index('sync_status');
            $table->index('mailerlite_subscriber_id');
            $table->index('first_post_at');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('mailerlite_subscribers');
    },
];
