<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->create('mailerlite_sync_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('action', 50); // 'sync', 'add_to_group', 'remove_from_group', 'unsubscribe'
            $table->string('status', 20); // 'pending', 'success', 'failed'
            $table->string('group_name', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('user_id');
            $table->index('status');
            $table->index('action');
            $table->index('created_at');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('mailerlite_sync_log');
    },
];
