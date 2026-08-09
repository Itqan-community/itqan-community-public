<?php

namespace Bendary\AdminAudit\Serializers;

use Flarum\Api\Serializer\AbstractSerializer;
use Flarum\Api\Serializer\UserSerializer;

class AuditLogSerializer extends AbstractSerializer
{
    /**
     * {@inheritdoc}
     */
    protected $type = 'admin_audit_logs';

    /**
     * {@inheritdoc}
     */
    protected function getDefaultAttributes($model)
    {
        return [
            'category'   => $model->category,
            'action'     => $model->action,
            'target'     => $model->target,
            'oldValue'   => $model->old_value,
            'newValue'   => $model->new_value,
            'meta'       => $model->meta,
            'ip'         => $model->ip,
            'createdAt'  => $this->formatDate($model->created_at),
        ];
    }

    /**
     * @param $model
     * @return \Tobscure\JsonApi\Relationship
     */
    protected function user($model)
    {
        return $this->hasOne($model, UserSerializer::class);
    }
}
