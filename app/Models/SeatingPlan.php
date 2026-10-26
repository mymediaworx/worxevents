<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeatingPlan extends Model
{
    use BelongsToOrganization, HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'venue_id',
        'event_id',
        'name',
    ];

    /**
     * @return HasMany<SeatingSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(SeatingSection::class)->orderBy('sort')->orderBy('name');
    }
}
