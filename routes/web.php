<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Marketing\DesignSystemController;
use App\Http\Controllers\Marketing\HomeController;
use App\Http\Controllers\Organization\DashboardController;
use App\Http\Controllers\Organization\InvitationController;
use App\Http\Controllers\Organization\PublicHomeController;
use App\Http\Controllers\Organization\RefundController;
use App\Http\Controllers\Organization\SectionController;
use App\Http\Controllers\Organization\TeamController;
use App\Http\Controllers\Platform\DashboardController as PlatformDashboardController;
use App\Http\Controllers\Platform\FeeRuleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    if ($request->attributes->get('host_kind') === 'platform') {
        return app(HomeController::class)();
    }

    return app(PublicHomeController::class)($request);
})->name('home');

Route::get('/design', DesignSystemController::class)->name('design');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:register');
    Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'send'])->middleware('throttle:password-email')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
});

Route::post('/logout', [LogoutController::class, 'store'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept'])
    ->middleware(['auth', 'verified'])
    ->name('invitations.accept');

Route::middleware(['auth', 'organization.member', 'verified'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/events', [SectionController::class, 'events'])->middleware('permission:events.view')->name('events');
    Route::get('/box-office', [SectionController::class, 'boxOffice'])->middleware('permission:tickets.sell')->name('box-office');
    Route::get('/check-in', [SectionController::class, 'checkIn'])->middleware('permission:checkin.scan')->name('check-in');
    Route::get('/tickets', [SectionController::class, 'tickets'])->middleware('permission:orders.view_own')->name('tickets');
    Route::get('/team', [TeamController::class, 'index'])->middleware('permission:organization.members.view')->name('team');
    Route::post('/team/invitations', [TeamController::class, 'invite'])->middleware('permission:organization.members.manage')->name('team.invite');
    Route::get('/finance', [RefundController::class, 'index'])->middleware('permission:finance.view')->name('finance');
    Route::post('/finance/refunds', [RefundController::class, 'store'])->middleware('permission:finance.refunds.decide')->name('refunds.store');
    Route::post('/finance/refunds/{refund}/submit', [RefundController::class, 'submit'])->middleware('permission:finance.refunds.decide')->name('refunds.submit');
});

Route::middleware(['auth', 'platform.member', 'verified'])->prefix('platform')->group(function (): void {
    Route::get('/', PlatformDashboardController::class)->name('platform.dashboard');
    Route::get('/fees', [FeeRuleController::class, 'index'])->middleware('permission:platform.fees.view')->name('platform.fees');
    Route::post('/fees', [FeeRuleController::class, 'store'])->middleware('permission:platform.fees.manage')->name('platform.fees.store');
});
