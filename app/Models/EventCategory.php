<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventCategory extends Model
{
    use BelongsToOrganization, HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'name',
        'slug',
    ];

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'category_id');
    }
}
