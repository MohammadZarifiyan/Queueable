<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ServiceProvider;

class QueueableServiceProvider extends ServiceProvider
{
    public function register()
    {
        Blueprint::macro('queueable', function () {
            static::boolean('status')->default(false);
            static::unsignedBigInteger('expires_in')->nullable()->comment('Based on Seconds');
            static::unsignedBigInteger('inactive_for')->nullable()->comment('Based on Seconds');
            static::timestamp('started_at')->nullable();
            static::timestamp('paused_at')->nullable();
            static::timestamp('continued_at')->nullable();
            static::timestamp('expired_at')->nullable();
        });
    }
}
