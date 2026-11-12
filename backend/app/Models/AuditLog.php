<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;

class AuditLog extends Model
{
    const UPDATED_AT = null;

    protected $casts = [
        'payload' => 'array',
    ];

    /** Denetim kaydı yazar. $actor: müşteri, panel kullanıcısı veya null (sistem). */
    public static function record(?EloquentModel $actor, string $action, ?EloquentModel $subject = null, array $payload = []): void
    {
        static::query()->create([
            'actor_type' => match (true) {
                $actor instanceof Customer => 'customer',
                $actor instanceof PanelUser => 'panel_user',
                default => 'system',
            },
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'payload' => $payload ?: null,
            'ip' => request()?->ip(),
        ]);
    }
}
