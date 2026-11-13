<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RunningApp extends Model
{
    protected $fillable = ['device_id', 'name', 'title', 'pid', 'started_at', 'last_seen_at', 'ended_at'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function openSeconds(): int
    {
        return (int) $this->started_at->diffInSeconds($this->ended_at ?? now(), true);
    }
}
