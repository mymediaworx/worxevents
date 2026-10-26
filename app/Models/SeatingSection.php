<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeatingSection extends Model
{
    use BelongsToOrganization, HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'seating_plan_id',
        'name',
        'price_zone',
        'sort',
    ];

    /**
     * @return HasMany<SeatingRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(SeatingRow::class)->orderBy('sort')->orderBy('label');
    }
}
