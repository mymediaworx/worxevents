<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PaymentSetting extends Model
{
    use BelongsToOrganization, HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'environment',
        'api_login_id',
        'transaction_key',
        'signature_key',
        'public_client_key',
        'validated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_login_id' => 'encrypted',
            'transaction_key' => 'encrypted',
            'signature_key' => 'encrypted',
            'validated_at' => 'datetime',
        ];
    }
}
