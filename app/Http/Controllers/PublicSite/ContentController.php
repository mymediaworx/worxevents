<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Domain\Fees\MoneyFormat;
use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\FeeRule;
use App\Models\Organization;
use App\Tenancy\OrganizationManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ContentController extends Controller
{
    public function __construct(private readonly OrganizationManager $organizations) {}

    public function about(): Response
    {
        return $this->page('About', 'WorxEvents is registration software for organizers. Each organization has its own address, its own team, and its own ticket inventory.');
    }

    public function pricing(): Response
    {
        $rule = FeeRule::query()->where('effective_at', '<=', now())->orderByDesc('effective_at')->first();
        $summary = 'The booking fee is calculated on the server, once per order, on ticket prices after discounts.';

        if ($rule instanceof FeeRule) {
            $summary = 'The current booking fee is '.MoneyFormat::bpsToPercent($rule->percent_bps).'% of the ticket subtotal, with a minimum of $'.MoneyFormat::centsToDollars($rule->minimum_cents).' and a maximum of $'.MoneyFormat::centsToDollars($rule->maximum_cents).'. Free registrations are $0. The minimum is not applied when the eligible ticket total is $0. Merchandise, donations, and add-ons are excluded.';
        }

        return $this->page('Pricing', $summary.' One fee is recorded per order and is not rewritten when the fee rule changes later. Card processing is not enabled in this release.');
    }

    public function help(): Response
    {
        return $this->page('Help', 'Create an organization, add a venue and an event, then add a ticket type and publish. Buyers register on your organization address and receive a PDF and QR code by email. The QR payload is a random token. WorxEvents stores only a hash of that token. Draft events stay off the public site and out of the sitemap. Paid checkout uses Authorize.net Accept.js after the organizer validates sandbox credentials. Card numbers are not posted to this site. Stripe is not connected.');
    }

    public function contact(): Response
    {
        return $this->page('Contact', 'Send a note to the WorxEvents team, or to this organization when you are on an organizer site. This form stores the message. It does not open a support case yet.');
    }

    public function terms(): Response
    {
        return $this->page('Terms of use', 'These terms are a draft for review and are not legal advice. Organizers are responsible for their events, their buyer terms, and the accuracy of event information. Buyers receive a digital ticket for a completed free registration. A canceled event follows the organizer refund policy.');
    }

    public function privacy(): Response
    {
        return $this->page('Privacy', 'This privacy note is a draft for review and is not legal advice. WorxEvents stores account emails, organization membership, event content, registration names and emails, and a hash of each ticket token. The raw ticket token is emailed to the buyer and is not stored. Payment card numbers are not collected in this release.');
    }

    public function refunds(): Response
    {
        return $this->page('Refunds', 'This refund note is a draft for review and is not legal advice. Organizer refund decisions are recorded before anything is sent to a processor. Sending a refund does not mark it complete until the processor confirms it. The platform booking fee is not automatically refunded when an organizer refunds a ticket. No card refunds run until a processor is connected.');
    }

    public function accessibility(): Response
    {
        return $this->page('Accessibility', 'WorxEvents aims for WCAG 2.2 AA: text contrast, a visible focus ring, 44 pixel targets, a skip link, labels on fields, and reduced motion when requested. Pages work without the menu script. A formal accessibility audit is still outstanding. If a page blocks you, use the contact form and include the address you were on.');
    }

    public function sendContact(Request $request): RedirectResponse
    {
        $validated = $this->validateOr422($request, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
        ], 'public.page', [
            'title' => 'Contact',
            'description' => 'Contact WorxEvents.',
            'body' => 'Send a note to the WorxEvents team.',
            'contact' => true,
        ]);

        $organization = $request->attributes->get('organization');

        ContactMessage::query()->create([
            'organization_id' => $organization instanceof Organization ? $organization->id : $this->organizations->id(),
            'name' => $validated['name'],
            'email' => $validated['email'],
            'body' => $validated['body'],
        ]);

        return redirect()->route('contact')->with('status', 'Message received.');
    }

    private function page(string $title, string $body): Response
    {
        return response()->view('public.page', [
            'title' => $title,
            'description' => $body,
            'body' => $body,
            'contact' => $title === 'Contact',
        ]);
    }
}
