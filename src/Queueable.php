<?php

namespace MohammadZarifiyan\Queueable;

trait Queueable
{
    public static function bootQueueable()
    {
        return static::addGlobalScope(new QueueableScope);
    }

    public function initializeQueueable()
    {
        static::mergeFillable([
            'status',
            'expires_in',
            'inactive_for',
            'started_at',
            'paused_at',
            'continued_at',
            'expired_at',
        ]);

        static::mergeCasts([
            'status' => 'boolean',
            'inactive_for' => 'integer',
            'started_at' => 'datetime',
            'paused_at' => 'datetime',
            'continued_at' => 'datetime',
            'expired_at' => 'datetime',
        ]);
    }
}
