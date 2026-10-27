<?php

declare(strict_types=1);

namespace Spiggle\ApiInspector\Models;

use Illuminate\Database\Eloquent\Model;

class InspectorSetting extends Model
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
        return (string) config('api-inspector.database.settings_table', 'api_inspector_settings');
    }

    public function getConnectionName(): ?string
    {
        return config('api-inspector.database.connection');
    }
}
