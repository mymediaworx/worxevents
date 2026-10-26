<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use RuntimeException;

trait ImmutableRecord
{
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new RuntimeException(static::class.' records are immutable.');
        }

        return parent::save($options);
    }
}
