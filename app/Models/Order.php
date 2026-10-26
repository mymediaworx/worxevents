<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use BelongsToOrganization, HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'buyer_user_id',
        'guest_name',
        'guest_email',
        'status',
        'payment_status',
        'provider',
        'provider_reference',
        'card_last_four',
        'channel',
        'referral_code',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'tender',
        'currency',
        'ticket_subtotal_cents',
        'discount_cents',
        'discount_code',
        'fee_cents',
        'total_cents',
        'reference',
        'completed_at',
        'email_failed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'ticket_subtotal_cents' => 'integer',
            'discount_cents' => 'integer',
            'fee_cents' => 'integer',
            'total_cents' => 'integer',
            'completed_at' => 'datetime',
            'email_failed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    /**
     * @return HasMany<OrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    /**
     * @return HasMany<OrderAddOn, $this>
     */
    public function addOns(): HasMany
    {
        return $this->hasMany(OrderAddOn::class);
    }

    /**
     * @return HasMany<Attendee, $this>
     */
    public function attendees(): HasMany
    {
        return $this->hasMany(Attendee::class);
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * @return HasMany<OrderAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(OrderAnswer::class);
    }
}
