<?php

use Illuminate\Database\Schema\Blueprint;

use Illuminate\Database\Schema\Builder;


return [
    'up' => function (Builder $schema) {
       $schema->create('newsletter_subscribers', function (Blueprint $table) {
           $table->bigIncrements('id');
           $table->string('email')->unique();
           $table->timestamps();
       });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('newsletter_subscribers');
    }
];
