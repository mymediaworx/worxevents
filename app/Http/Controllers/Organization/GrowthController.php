<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Domain\Growth\EmailList;
use App\Domain\Growth\PromoQr;
use App\Http\Controllers\Controller;
use App\Mail\AnnouncementMail;
use App\Models\Announcement;
use App\Models\EmailConsent;
use App\Models\Event;
use App\Models\Organization;
use App\Models\SupportCase;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

final class GrowthController extends Controller
{
    public function __construct(
        private readonly EmailList $list,
        private readonly PromoQr $qr,
    ) {}

    public function support(): Response
    {
        return response()->view('organization.support', [
            'title' => 'Support',
            'cases' => SupportCase::query()->latest()->limit(50)->get(),
        ]);
    }

    public function storeSupport(Request $request): Response
    {
        $validated = $this->validateOr422($request, [
            'subject' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
        ], 'organization.support', [
            'title' => 'Support',
            'cases' => SupportCase::query()->latest()->limit(50)->get(),
        ]);

        $user = $request->user();
        SupportCase::query()->create([
            'user_id' => $user instanceof User ? $user->id : null,
            'subject' => (string) $validated['subject'],
            'body' => (string) $validated['body'],
            'status' => 'open',
        ]);

        return response()->view('organization.support', [
            'title' => 'Support',
            'cases' => SupportCase::query()->latest()->limit(50)->get(),
            'status' => 'Support case opened.',
        ]);
    }

    public function announcements(): Response
    {
        return response()->view('organization.announcements', $this->announcementPage());
    }

    public function sendAnnouncement(Request $request): Response
    {
        $organization = $request->attributes->get('organization');
        $validated = $this->validateOr422($request, [
            'subject' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
        ], 'organization.announcements', $this->announcementPage());

        if (! $organization instanceof Organization) {
            abort(404);
        }

        $sent = 0;

        foreach ($this->list->recipients($organization->id) as $consent) {
            try {
                Mail::to($consent->email)->send(new AnnouncementMail(
                    (string) $validated['subject'],
                    (string) $validated['body'],
                    URL::signedRoute('announcements.unsubscribe', ['consent' => $consent->id]),
                ));
                $sent++;
            } catch (Throwable) {
                // A failed recipient does not stop the rest of the consented list.
            }
        }

        Announcement::query()->create([
            'organization_id' => $organization->id,
            'subject' => (string) $validated['subject'],
            'body' => (string) $validated['body'],
            'sent_count' => $sent,
        ]);

        return response()->view('organization.announcements', array_merge($this->announcementPage(), [
            'status' => 'Sent to '.$sent.' consented addresses.',
        ]));
    }

    public function unsubscribe(EmailConsent $consent): Response
    {
        $consent->forceFill(['unsubscribed_at' => now()])->save();

        return response()->view('public.unsubscribed', [
            'title' => 'Unsubscribed',
            'robots' => 'noindex, nofollow',
        ]);
    }

    public function promo(Request $request, Event $event): Response
    {
        $code = trim((string) $request->query('ref', 'event'));

        if (preg_match('/\A[A-Za-z0-9._~-]{1,40}\z/', $code) !== 1) {
            $code = 'event';
        }

        $url = url('/e/'.$event->slug).'?ref='.$code;

        return response()->view('organization.promo', [
            'title' => 'Promo QR',
            'event' => $event,
            'url' => $url,
            'svg' => $this->qr->svg($url),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function announcementPage(): array
    {
        return [
            'title' => 'Announcements',
            'consents' => EmailConsent::query()->orderBy('email')->limit(100)->get(),
            'announcements' => Announcement::query()->latest()->limit(20)->get(),
        ];
    }
}
