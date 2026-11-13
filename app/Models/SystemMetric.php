<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemMetric extends Model
{
    use Prunable;

    protected $fillable = [
        'device_id', 'cpu_percent', 'memory_used_mb', 'memory_total_mb', 'disk_used_gb', 'disk_total_gb',
        'battery_percent', 'charging', 'battery_temperature', 'booted_at', 'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'charging' => 'boolean',
            'booted_at' => 'datetime',
            'recorded_at' => 'datetime',
        ];
    }

    /** Metingen ouder dan 30 dagen worden opgeruimd (php artisan model:prune). */
    public function prunable(): Builder
    {
        return static::where('recorded_at', '<', now()->subDays(30));
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function memoryPercent(): ?float
    {
        if (! $this->memory_total_mb || $this->memory_used_mb === null) {
            return null;
        }

        return round($this->memory_used_mb / $this->memory_total_mb * 100, 1);
    }

    public function diskPercent(): ?float
    {
        if (! $this->disk_total_gb || $this->disk_used_gb === null) {
            return null;
        }

        return round($this->disk_used_gb / $this->disk_total_gb * 100, 1);
    }

    public function uptimeSeconds(): ?int
    {
        return $this->booted_at ? (int) $this->booted_at->diffInSeconds(now(), true) : null;
    }
}
