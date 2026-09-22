<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if (! $schema->hasTable('itqan_reaction_types')) {
            $schema->create('itqan_reaction_types', function (Blueprint $table) {
                $table->increments('id');
                $table->string('identifier', 32)->unique();
                $table->string('emoji', 16);
                $table->string('label', 64)->nullable();
                $table->boolean('enabled')->default(true);
                $table->unsignedSmallInteger('position')->default(0);
            });
        }

        if (! $schema->hasTable('itqan_post_reactions')) {
            $schema->create('itqan_post_reactions', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('post_id');
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('reaction_id');
                $table->timestamp('created_at')->useCurrent();

                // One reaction type per user per post (GitHub-style single active type:
                // unique on post+user means switching replaces; enforced in app layer.
                // Allow multiple types: unique (post_id, user_id, reaction_id).
                $table->unique(['post_id', 'user_id', 'reaction_id'], 'itqan_post_reactions_unique');
                $table->index(['post_id', 'reaction_id'], 'itqan_post_reactions_post_reaction');
                $table->foreign('post_id')->references('id')->on('posts')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('reaction_id')->references('id')->on('itqan_reaction_types')->onDelete('cascade');
            });
        }

        if (! $schema->hasColumn('posts', 'reaction_counts')) {
            $schema->table('posts', function (Blueprint $table) {
                $table->json('reaction_counts')->nullable();
            });
        }
    },

    'down' => function (Builder $schema) {
        $schema->dropIfExists('itqan_post_reactions');
        $schema->dropIfExists('itqan_reaction_types');

        if ($schema->hasColumn('posts', 'reaction_counts')) {
            $schema->table('posts', function (Blueprint $table) {
                $table->dropColumn('reaction_counts');
            });
        }
    },
];
