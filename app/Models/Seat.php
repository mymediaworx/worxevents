<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Seat extends Model
{
    use BelongsToOrganization, HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'seating_row_id',
        'label',
        'accessible',
        'status',
        'held_until',
        'order_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accessible' => 'boolean',
            'held_until' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SeatingRow, $this>
     */
    public function row(): BelongsTo
    {
        return $this->belongsTo(SeatingRow::class, 'seating_row_id');
    }
}
