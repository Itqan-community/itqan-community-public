<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if (! $schema->hasTable('itqan_stats_daily_buckets')) {
            $schema->create('itqan_stats_daily_buckets', function (Blueprint $table) {
                $table->string('bucket_date', 10)->primary(); // YYYY-MM-DD in community timezone
                $table->unsignedInteger('new_users_count')->default(0);
                $table->unsignedInteger('discussions_count')->default(0);
                $table->unsignedInteger('posts_count')->default(0); // comment-type posts (openers + replies)
                $table->unsignedInteger('replies_count')->default(0); // comment-type posts with number > 1
                $table->mediumText('payload')->nullable(); // JSON: user activity counts, liker IDs, etc.
                $table->dateTime('computed_at');
            });
        }

        if (! $schema->hasTable('itqan_stats_teams')) {
            $schema->create('itqan_stats_teams', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 120);
                $table->string('description', 500)->nullable();
                $table->string('color', 24)->default('#0d9488');
                $table->boolean('is_visible')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('itqan_stats_team_members')) {
            $schema->create('itqan_stats_team_members', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('team_id');
                $table->unsignedInteger('user_id');
                $table->string('display_name', 150)->nullable();
                $table->string('role', 100)->nullable();
                $table->timestamps();

                $table->unique(['team_id', 'user_id'], 'itqan_stats_team_member_unique');
                $table->index('user_id', 'itqan_stats_team_member_user_idx');

                $table->foreign('team_id')
                    ->references('id')
                    ->on('itqan_stats_teams')
                    ->onDelete('cascade');

                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('cascade');
            });
        }
    },

    'down' => function (Builder $schema) {
        $schema->dropIfExists('itqan_stats_team_members');
        $schema->dropIfExists('itqan_stats_teams');
        $schema->dropIfExists('itqan_stats_daily_buckets');
    },
];
