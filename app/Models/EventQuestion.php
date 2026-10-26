<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuestionField;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventQuestion extends Model
{
    use BelongsToOrganization, HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'label',
        'field_type',
        'required',
        'options',
        'sort',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'field_type' => QuestionField::class,
            'required' => 'boolean',
            'options' => 'array',
            'sort' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
