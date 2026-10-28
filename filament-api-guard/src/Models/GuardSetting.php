<?php

declare(strict_types=1);

namespace Spiggle\ApiGuard\Models;

use Illuminate\Database\Eloquent\Model;

class GuardSetting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
    ];

    public function getTable(): string
    {
        return (string) config('api-guard.database.settings_table', 'api_guard_settings');
    }

    public function getConnectionName(): ?string
    {
        return config('api-guard.database.connection');
    }
}
