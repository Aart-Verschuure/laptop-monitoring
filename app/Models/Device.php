<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Device extends Model
{
    public const TYPES = ['laptop' => 'Laptop', 'phone' => 'Telefoon'];

    protected $fillable = ['name', 'type', 'token_hash', 'specs', 'last_seen_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'specs' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Maak een nieuw apparaat aan en geef het (eenmalig zichtbare) token terug.
     *
     * @return array{0: Device, 1: string}
     */
    public static function createWithToken(string $name, string $type): array
    {
        $token = Str::random(48);

        $device = static::create([
            'name' => $name,
            'type' => $type,
            'token_hash' => hash('sha256', $token),
        ]);

        return [$device, $token];
    }

    public static function findByToken(?string $token): ?self
    {
        if (! $token) {
            return null;
        }

        return static::where('token_hash', hash('sha256', $token))->first();
    }

    public function isPhone(): bool
    {
        return $this->type === 'phone';
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->gt(now()->subMinutes($this->isPhone() ? 30 : 5));
    }

    public function runningApps(): HasMany
    {
        return $this->hasMany(RunningApp::class);
    }

    public function browsingVisits(): HasMany
    {
        return $this->hasMany(BrowsingVisit::class);
    }

    public function systemMetrics(): HasMany
    {
        return $this->hasMany(SystemMetric::class);
    }

    public function latestMetric(): HasOne
    {
        return $this->hasOne(SystemMetric::class)->latestOfMany('recorded_at');
    }

    public function loginEvents(): HasMany
    {
        return $this->hasMany(LoginEvent::class);
    }
}
