<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PlatformPlan extends Model
{
    use HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'amount_cents',
        'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'active' => 'boolean',
        ];
    }
}
