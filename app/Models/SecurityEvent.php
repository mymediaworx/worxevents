<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class SecurityEvent extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'kind',
        'message',
        'ip_hash',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
