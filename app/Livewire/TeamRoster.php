<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\OrganizationMember;
use App\Support\PageSize;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class TeamRoster extends Component
{
    public function render(): View
    {
        $perPage = PageSize::clamp((int) request()->query('per_page', 25));

        return view('livewire.team-roster', [
            'members' => OrganizationMember::query()
                ->with(['user', 'role'])
                ->orderBy('created_at')
                ->paginate($perPage),
        ]);
    }
}
