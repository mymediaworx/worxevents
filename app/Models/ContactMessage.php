<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    use HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'name',
        'email',
        'body',
    ];
}
