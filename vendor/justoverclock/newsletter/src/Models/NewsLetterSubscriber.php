<?php

namespace Justoverclock\NewsLetter\Models;

use Flarum\Database\AbstractModel;

class NewsLetterSubscriber extends AbstractModel
{
    protected $table = 'newsletter_subscribers';
    protected $fillable = ['email'];

    public $timestamps = true;

    public static function emailExists($email)
    {
        return (bool) static::query()->where('email', $email)->exists();
    }

    public static function deleteByEmail($email)
    {
        $subscriber = static::query()
            ->where('email', $email)->first();

        if ($subscriber) {
            return $subscriber->delete();
        }

        return false;
    }
}
