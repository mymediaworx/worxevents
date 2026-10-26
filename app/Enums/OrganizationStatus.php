<?php

declare(strict_types=1);

namespace App\Enums;

enum OrganizationStatus: string
{
    case Onboarding = 'onboarding';
    case Active = 'active';
    case Suspended = 'suspended';
    case Terminated = 'terminated';
}
