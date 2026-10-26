<?php

declare(strict_types=1);

namespace App\Enums;

enum QuestionField: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Select = 'select';
    case Checkbox = 'checkbox';
}
