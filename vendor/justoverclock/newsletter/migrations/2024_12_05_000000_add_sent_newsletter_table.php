<?php

use Illuminate\Database\Schema\Blueprint;

use Illuminate\Database\Schema\Builder;


return [
    'up' => function (Builder $schema) {
        $schema->create('sent_newsletters', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('newsletter_title');
            $table->timestamps();
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('sent_newsletters');
    }
];
