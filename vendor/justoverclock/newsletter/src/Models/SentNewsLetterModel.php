<?php

namespace Justoverclock\NewsLetter\Models;

use Flarum\Database\AbstractModel;

class SentNewsLetterModel extends AbstractModel
{
    protected $table = 'sent_newsletters';
    protected $fillable = ['newsletter_title'];

    public $timestamps = true;

    public static function add($title)
    {
        $sentNewsletter = new static;

        $sentNewsletter->newsletter_title = $title;
        $sentNewsletter->save();
        return $sentNewsletter;
    }
}
