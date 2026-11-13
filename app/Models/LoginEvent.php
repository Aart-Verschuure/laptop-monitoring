<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginEvent extends Model
{
    public const TYPES = [
        'logon' => 'Aanmelding',
        'unlock' => 'Ontgrendeld',
        'remote' => 'Extern (RDP)',
        'failed' => 'Mislukte poging',
    ];

    /** Types die als "succesvol ingelogd" tellen. */
    public const SUCCESS_TYPES = ['logon', 'unlock', 'remote'];

    protected $fillable = ['device_id', 'type', 'username', 'source_id', 'occurred_at'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function label(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
