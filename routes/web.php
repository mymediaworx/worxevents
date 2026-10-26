<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Marketing\DesignSystemController;
use App\Http\Controllers\Marketing\HomeController;
use App\Http\Controllers\Organization\BoxOfficeController;
use App\Http\Controllers\Organization\CheckInController;
use App\Http\Controllers\Organization\DashboardController;
use App\Http\Controllers\Organization\EventController;
use App\Http\Controllers\Organization\InvitationController;
use App\Http\Controllers\Organization\PaymentSettingsController;
use App\Http\Controllers\Organization\PublicHomeController;
use App\Http\Controllers\Organization\RefundController;
use App\Http\Controllers\Organization\ReportController;
use App\Http\Controllers\Organization\SectionController;
use App\Http\Controllers\Organization\SetupController;
use App\Http\Controllers\Organization\TeamController;
use App\Http\Controllers\Organization\VenueController;
use App\Http\Controllers\Platform\DashboardController as PlatformDashboardController;
use App\Http\Controllers\Platform\FeeRuleController;
use App\Http\Controllers\PublicSite\AuthorizeNetWebhookController;
use App\Http\Controllers\PublicSite\BrowseController;
use App\Http\Controllers\PublicSite\CheckoutController;
use App\Http\Controllers\PublicSite\ContentController;
use App\Http\Controllers\PublicSite\EventPageController;
use App\Http\Controllers\PublicSite\MediaController;
use App\Http\Controllers\PublicSite\RegistrationController;
use App\Http\Controllers\PublicSite\SitemapController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    if ($request->attributes->get('host_kind') === 'platform') {
        return app(HomeController::class)();
    }

    return app(PublicHomeController::class)($request);
})->name('home');

Route::get('/design', DesignSystemController::class)->name('design');
Route::get('/browse', [BrowseController::class, 'index'])->name('browse');
Route::get('/categories/{slug}', [BrowseController::class, 'category'])->name('public.category');
Route::get('/about', [ContentController::class, 'about'])->name('about');
Route::get('/pricing', [ContentController::class, 'pricing'])->name('pricing');
Route::get('/help', [ContentController::class, 'help'])->name('help');
Route::get('/contact', [ContentController::class, 'contact'])->name('contact');
Route::post('/contact', [ContentController::class, 'sendContact'])->middleware('throttle:contact')->name('contact.store');
Route::get('/legal/terms', [ContentController::class, 'terms'])->name('legal.terms');
Route::get('/legal/privacy', [ContentController::class, 'privacy'])->name('legal.privacy');
Route::get('/legal/refunds', [ContentController::class, 'refunds'])->name('legal.refunds');
Route::get('/accessibility', [ContentController::class, 'accessibility'])->name('accessibility');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/organizer', [BrowseController::class, 'currentOrganizer'])->name('public.current-organizer');
Route::get('/o/{organization}', [BrowseController::class, 'organizer'])->name('public.organizer');
Route::get('/o/{organization}/e/{event}', [EventPageController::class, 'platform'])->name('public.platform.event');
Route::get('/o/{organization}/v/{venue}', [BrowseController::class, 'platformVenue'])->name('public.platform.venue');
Route::get('/v/{venue}', [BrowseController::class, 'venue'])->name('public.venue');
Route::get('/e/{slug}', [EventPageController::class, 'organization'])->name('public.event');
Route::post('/e/{slug}/hold', [CheckoutController::class, 'hold'])->middleware('throttle:checkout')->name('checkout.hold');
Route::post('/e/{slug}/quote', [CheckoutController::class, 'quote'])->middleware('throttle:checkout')->name('checkout.quote');
Route::post('/e/{slug}/checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout')->name('checkout.store');
Route::post('/webhooks/authorize-net/{organizationId}', [AuthorizeNetWebhookController::class, 'store'])
    ->middleware('throttle:webhooks')
    ->name('webhooks.authorize-net');
Route::get('/registration/{reference}', [RegistrationController::class, 'show'])->name('registration.show');
Route::post('/registration/{reference}', [RegistrationController::class, 'confirm'])->middleware('throttle:checkout')->name('registration.confirm');
Route::get('/media/logo', [MediaController::class, 'logo'])->name('media.logo');
Route::get('/media/events/{event}', [MediaController::class, 'eventImage'])->name('media.event');

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
    Route::get('/events', [EventController::class, 'index'])->middleware('permission:events.view')->name('events');
    Route::get('/events/create', [EventController::class, 'create'])->middleware('permission:events.manage')->name('events.create');
    Route::post('/events', [EventController::class, 'store'])->middleware('permission:events.manage')->name('events.store');
    Route::get('/events/{event}/edit', [EventController::class, 'edit'])->middleware('permission:events.manage')->name('events.edit');
    Route::post('/events/{event}', [EventController::class, 'update'])->middleware('permission:events.manage')->name('events.update');
    Route::post('/events/{event}/publish', [EventController::class, 'publish'])->middleware('permission:events.manage')->name('events.publish');
    Route::post('/events/{event}/status', [EventController::class, 'status'])->middleware('permission:events.manage')->name('events.status');
    Route::get('/events/{event}/preview', [EventController::class, 'preview'])->middleware('permission:events.view')->name('events.preview');
    Route::post('/events/{event}/sessions', [EventController::class, 'storeSession'])->middleware('permission:events.manage')->name('events.sessions.store');
    Route::post('/events/{event}/tickets', [EventController::class, 'storeTicket'])->middleware('permission:events.manage')->name('events.tickets.store');
    Route::post('/events/{event}/questions', [EventController::class, 'storeQuestion'])->middleware('permission:events.manage')->name('events.questions.store');
    Route::post('/orders/{order}/reissue', [EventController::class, 'reissue'])->middleware('permission:events.manage')->name('orders.reissue');
    Route::get('/venues', [VenueController::class, 'index'])->middleware('permission:events.view')->name('venues');
    Route::get('/venues/create', [VenueController::class, 'create'])->middleware('permission:events.manage')->name('venues.create');
    Route::post('/venues', [VenueController::class, 'store'])->middleware('permission:events.manage')->name('venues.store');
    Route::get('/venues/{venue}/edit', [VenueController::class, 'edit'])->middleware('permission:events.manage')->name('venues.edit');
    Route::post('/venues/{venue}', [VenueController::class, 'update'])->middleware('permission:events.manage')->name('venues.update');
    Route::get('/reports', [ReportController::class, 'index'])->middleware('permission:finance.view')->name('reports');
    Route::get('/reports/finance.csv', [ReportController::class, 'csv'])->middleware('permission:finance.view')->name('reports.csv');
    Route::post('/reports/remittance', [ReportController::class, 'openInvoice'])->middleware('permission:finance.refunds.decide')->name('reports.remittance');
    Route::post('/reports/remittance/{invoice}/paid', [ReportController::class, 'markPaid'])->middleware('permission:finance.refunds.decide')->name('reports.remittance.paid');
    Route::get('/payments', [PaymentSettingsController::class, 'show'])->middleware('permission:organization.settings.update')->name('payments');
    Route::post('/payments', [PaymentSettingsController::class, 'store'])->middleware('permission:organization.settings.update')->name('payments.store');
    Route::get('/setup', [SetupController::class, 'show'])->middleware('permission:organization.settings.update')->name('setup');
    Route::post('/setup/details', [SetupController::class, 'details'])->middleware('permission:organization.settings.update')->name('setup.details');
    Route::post('/setup/branding', [SetupController::class, 'branding'])->middleware('permission:organization.settings.update')->name('setup.branding');
    Route::post('/setup/locale', [SetupController::class, 'locale'])->middleware('permission:organization.settings.update')->name('setup.locale');
    Route::post('/setup/finish', [SetupController::class, 'finish'])->middleware('permission:organization.settings.update')->name('setup.finish');
    Route::get('/box-office', [BoxOfficeController::class, 'index'])->middleware('permission:tickets.sell')->name('box-office');
    Route::post('/box-office', [BoxOfficeController::class, 'store'])->middleware('permission:tickets.sell')->name('box-office.store');
    Route::get('/check-in', [CheckInController::class, 'index'])->middleware('permission:checkin.scan')->name('check-in');
    Route::post('/check-in/scan', [CheckInController::class, 'scan'])->middleware('permission:checkin.scan')->name('check-in.scan');
    Route::post('/check-in/lookup', [CheckInController::class, 'lookup'])->middleware('permission:checkin.scan')->name('check-in.lookup');
    Route::post('/check-in/tickets/{ticket}/admit', [CheckInController::class, 'admit'])->middleware('permission:checkin.scan')->name('check-in.admit');
    Route::post('/check-in/tickets/{ticket}/reverse', [CheckInController::class, 'reverse'])->middleware('permission:events.manage')->name('check-in.reverse');
    Route::get('/events/{event}/attendees.csv', [CheckInController::class, 'export'])->middleware('permission:events.view')->name('events.attendees');
    Route::get('/tickets', [SectionController::class, 'tickets'])->middleware('permission:orders.view_own')->name('tickets');
    Route::get('/team', [TeamController::class, 'index'])->middleware('permission:organization.members.view')->name('team');
    Route::post('/team/invitations', [TeamController::class, 'invite'])->middleware('permission:organization.members.manage')->name('team.invite');
    Route::get('/finance', [RefundController::class, 'index'])->middleware('permission:finance.view')->name('finance');
    Route::post('/finance/refunds', [RefundController::class, 'store'])->middleware('permission:finance.refunds.decide')->name('refunds.store');
    Route::post('/finance/refunds/{refund}/submit', [RefundController::class, 'submit'])->middleware('permission:finance.refunds.decide')->name('refunds.submit');
    Route::post('/finance/refunds/{refund}/confirm', [RefundController::class, 'confirm'])->middleware('permission:finance.refunds.decide')->name('refunds.confirm');
});

Route::middleware(['auth', 'platform.member', 'verified'])->prefix('platform')->group(function (): void {
    Route::get('/', PlatformDashboardController::class)->name('platform.dashboard');
    Route::get('/fees', [FeeRuleController::class, 'index'])->middleware('permission:platform.fees.view')->name('platform.fees');
    Route::post('/fees', [FeeRuleController::class, 'store'])->middleware('permission:platform.fees.manage')->name('platform.fees.store');
});
