<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrowsingVisit extends Model
{
    protected $fillable = ['device_id', 'url', 'domain', 'title', 'browser', 'incognito', 'visited_at'];

    protected function casts(): array
    {
        return [
            'incognito' => 'boolean',
            'visited_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
