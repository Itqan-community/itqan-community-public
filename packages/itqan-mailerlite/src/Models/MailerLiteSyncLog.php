<?php

namespace Itqan\MailerLite\Models;

use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailerLiteSyncLog extends AbstractModel
{
    protected $table = 'mailerlite_sync_log';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'status',
        'group_name',
        'error_message',
        'request_data',
        'response_data',
    ];

    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
        'created_at' => 'datetime',
    ];

    public const ACTION_SYNC = 'sync';
    public const ACTION_ADD_TO_GROUP = 'add_to_group';
    public const ACTION_REMOVE_FROM_GROUP = 'remove_from_group';
    public const ACTION_UNSUBSCRIBE = 'unsubscribe';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function log(
        int $userId,
        string $action,
        string $status,
        ?string $groupName = null,
        ?string $errorMessage = null,
        ?array $requestData = null,
        ?array $responseData = null
    ): self {
        return static::create([
            'user_id' => $userId,
            'action' => $action,
            'status' => $status,
            'group_name' => $groupName,
            'error_message' => $errorMessage,
            'request_data' => $requestData,
            'response_data' => $responseData,
        ]);
    }

    public static function logSuccess(
        int $userId,
        string $action,
        ?string $groupName = null,
        ?array $requestData = null,
        ?array $responseData = null
    ): self {
        return static::log($userId, $action, self::STATUS_SUCCESS, $groupName, null, $requestData, $responseData);
    }

    public static function logFailure(
        int $userId,
        string $action,
        string $errorMessage,
        ?string $groupName = null,
        ?array $requestData = null,
        ?array $responseData = null
    ): self {
        return static::log($userId, $action, self::STATUS_FAILED, $groupName, $errorMessage, $requestData, $responseData);
    }
}
