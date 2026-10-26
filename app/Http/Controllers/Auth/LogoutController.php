<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Tenancy\OrganizationManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class LogoutController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly OrganizationManager $organizations,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $this->audit->write('auth.logout', $this->organizations->id(), $request->user(), []);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
