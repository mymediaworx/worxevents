<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use BelongsToOrganization, HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'venue_id',
        'category_id',
        'title',
        'slug',
        'summary',
        'description',
        'status',
        'timezone',
        'is_multi_day',
        'starts_at',
        'ends_at',
        'sales_starts_at',
        'sales_ends_at',
        'capacity',
        'admits_sold',
        'admits_held',
        'image_path',
        'seo_title',
        'seo_description',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EventStatus::class,
            'is_multi_day' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'sales_starts_at' => 'datetime',
            'sales_ends_at' => 'datetime',
            'published_at' => 'datetime',
            'capacity' => 'integer',
            'admits_sold' => 'integer',
            'admits_held' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Venue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * @return BelongsTo<EventCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'category_id');
    }

    /**
     * @return BelongsToMany<EventTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(EventTag::class, 'event_event_tag');
    }

    /**
     * @return HasMany<EventSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(EventSession::class)->orderBy('sort')->orderBy('starts_at');
    }

    /**
     * @return HasMany<TicketType, $this>
     */
    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class)->orderBy('sort')->orderBy('name');
    }

    /**
     * @return HasMany<EventQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(EventQuestion::class)->orderBy('sort');
    }

    public function isPubliclyVisible(): bool
    {
        return in_array($this->status, [
            EventStatus::Published,
            EventStatus::Paused,
            EventStatus::SoldOut,
            EventStatus::Canceled,
            EventStatus::Completed,
        ], true);
    }
}
