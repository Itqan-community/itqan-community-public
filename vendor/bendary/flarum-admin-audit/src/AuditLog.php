<?php

namespace Bendary\AdminAudit;

use Flarum\Database\AbstractModel;
use Flarum\User\User;

class AuditLog extends AbstractModel
{
    protected $table = 'admin_audit_logs';

    public $timestamps = false; // We will manually handle created_at or use custom dates

    protected $casts = [
        'old_value' => 'array',
        'new_value' => 'array',
        'meta' => 'array',
        'created_at' => 'datetime',
        'user_id' => 'integer'
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Create a new AuditLog.
     *
     * @param int|null $userId
     * @param string $category
     * @param string $action
     * @param string|null $target
     * @param array|null $oldValue
     * @param array|null $newValue
     * @param array|null $meta
     * @param string|null $ip
     * @return static
     */
    public static function build($userId, $category, $action, $target = null, $oldValue = null, $newValue = null, $meta = null, $ip = null)
    {
        $log = new static();

        $log->user_id = $userId;
        $log->category = $category;
        $log->action = $action;
        $log->target = $target;
        $log->old_value = $oldValue;
        $log->new_value = $newValue;
        $log->meta = $meta;
        $log->ip = $ip;
        $log->created_at = \Carbon\Carbon::now();

        return $log;
    }
}
