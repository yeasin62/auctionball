<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class PublicPageController extends Controller
{
    public const PAGE_SLUGS = [
        'features',
        'pricing',
        'live-demo',
        'changelog',
        'roadmap',
        'getting-started',
        'auction-guide',
        'big-screen-setup',
        'team-device-guide',
        'community',
        'status',
        'contact',
        'terms',
        'privacy',
        'refunds',
        'acceptable-use',
    ];

    public function show(string $slug): Response
    {
        abort_unless(in_array($slug, self::PAGE_SLUGS, true), 404);

        if ($slug === 'contact') {
            return $this->contact();
        }

        return Inertia::render('Public/Page', [
            'page' => $this->pages()[$slug],
        ]);
    }

    public function contact(): Response
    {
        return Inertia::render('Public/Contact', [
            'phone' => '+8801770001090',
            'email' => 'gazi.yeasin@yahoo.com',
        ]);
    }

    public function submitContact(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'organization' => ['nullable', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
            'website' => ['nullable', 'string', 'max:0'],
        ]);

        $body = implode("\n", [
            'New AuctionBall contact message',
            '',
            "Name: {$data['name']}",
            "Email: {$data['email']}",
            'Phone: ' . ($data['phone'] ?: 'Not provided'),
            'Organization: ' . ($data['organization'] ?: 'Not provided'),
            'IP: ' . $request->ip(),
            '',
            'Message:',
            $data['message'],
        ]);

        Mail::raw($body, function ($message) use ($data) {
            $message->to('gazi.yeasin@yahoo.com')
                ->replyTo($data['email'], $data['name'])
                ->subject('New AuctionBall contact message');
        });

        return back()->with('success', 'Thanks, your message has been sent.');
    }

    public function blog(): Response
    {
        return Inertia::render('Public/Blog', [
            'posts' => BlogPost::query()
                ->published()
                ->latest('published_at')
                ->get()
                ->map(fn (BlogPost $post) => $this->blogPostPayload($post)),
        ]);
    }

    public function blogPost(BlogPost $post): Response
    {
        abort_unless(
            $post->is_published && $post->published_at && $post->published_at->lte(now()),
            404
        );

        return Inertia::render('Public/BlogShow', [
            'post' => $this->blogPostPayload($post, true),
        ]);
    }

    private function pages(): array
    {
        return [
            'features' => $this->page(
                'Features',
                'Product',
                'Everything needed to run a live player auction in one hosted workspace.',
                [
                    ['Live auction control', 'Start, pause, extend, mark sold or unsold, and keep every screen synchronized through Reverb-powered realtime updates.'],
                    ['Player and team management', 'Import CSV player lists, approve public registrations, manage team budgets, and export season summaries.'],
                    ['Venue-ready display', 'Big-screen and roster boards are built for projectors, TVs, and captain devices.'],
                ],
                ['Create an organization', 'Add a season, teams, and players', 'Open the auction control panel', 'Share team bidding links']
            ),
            'pricing' => $this->page(
                'Pricing',
                'Plans',
                'Start free, then upgrade when your tournament needs more capacity.',
                [
                    ['Free', 'Good for testing a small auction workflow before the real event.'],
                    ['Starter', 'Adds practical room for real tournament seasons, teams, and player lists.'],
                    ['Pro and Enterprise', 'Built for larger leagues, exports, advanced branding, and higher operating limits.'],
                ],
                ['Monthly billing', 'Cancel any time', 'PayPal and manual bKash support', 'Invoices for completed payments']
            ),
            'live-demo' => $this->page(
                'Live Demo',
                'Preview',
                'A guided preview of the auctioneer screen, big screen, and team bidding experience.',
                [
                    ['Auctioneer view', 'Control the current lot, bids, timer, and final result from one dashboard.'],
                    ['Captain view', 'Teams can bid from a mobile-friendly link without seeing admin tools.'],
                    ['Public display', 'Show the current player, leading bid, budgets, and result animation on a projector.'],
                ],
                ['Create a free organization', 'Use demo players', 'Open dashboard, big screen, and team device in separate tabs']
            ),
            'changelog' => $this->page(
                'Changelog',
                'Product updates',
                'Recent improvements shipped to AuctionBall.',
                [
                    ['Realtime stability', 'Reverb configuration, websocket routing, and build cache workflows were tightened for production.'],
                    ['PayPal checkout', 'PayPal order checkout, callback verification, and successful plan activation are supported.'],
                    ['Player operations', 'CSV import, public registration, image storage, and approval flows were hardened.'],
                ],
                ['Realtime auction updates', 'Persistent sold and unsold results', 'Safer dashboard permissions', 'Webhook verification']
            ),
            'roadmap' => $this->page(
                'Roadmap',
                'Upcoming',
                'The near-term direction for AuctionBall.',
                [
                    ['Tournament templates', 'Reusable setup templates for cricket, football, and custom auction formats.'],
                    ['Better reporting', 'More sponsor-ready exports, financial summaries, and post-auction analytics.'],
                    ['Organizer workflow', 'More guardrails around roles, invitations, and event-day recovery.'],
                ],
                ['Multi-sport presets', 'More payment options', 'Advanced branding', 'Improved email deliverability tooling']
            ),
            'getting-started' => $this->page(
                'Getting Started',
                'Documentation',
                'A complete first-to-last guide for setting up AuctionBall, preparing your tournament, running a live auction, and managing everything after the event.',
                [
                    ['Plan your workspace', 'Create the right organization, season, teams, and user roles before adding real players.'],
                    ['Prepare the auction room', 'Configure budgets, bid increments, player categories, big screen, team devices, and registration links.'],
                    ['Run and close cleanly', 'Control lots, bids, timer, sold or unsold results, exports, payments, and post-event records from one dashboard.'],
                ],
                ['Create account', 'Configure organization', 'Create season', 'Add teams', 'Add or import players', 'Test big screen', 'Run auction', 'Export results'],
                [
                    'practice_title' => 'How to use this guide',
                    'practice_body' => 'Work through the steps in order the first time. After your first test auction, you can jump directly to the specific feature area you need: players, teams, auction control, billing, exports, settings, or troubleshooting.',
                    'doc_sections' => [
                        [
                            'title' => '1. Create your AuctionBall account',
                            'body' => 'Start from the registration page and create the first admin account for your tournament. Use an email address you can access because account verification, password reset, billing messages, and support follow-ups may be sent there.',
                            'items' => [
                                'Enter your name, email, password, and organization details.',
                                'Choose an organization slug carefully. It becomes part of your workspace identity and can be useful in support requests.',
                                'Verify your email if verification is enabled, then log in to the dashboard.',
                                'Keep the first account as the main organization admin so you can manage billing, settings, users, and invitations.',
                            ],
                        ],
                        [
                            'title' => '2. Understand the main dashboard',
                            'body' => 'The dashboard is organized around the auction workflow. Most setup happens before auction day; auction control and big screen are used during the live event; exports and audit logs help after the event.',
                            'items' => [
                                'Dashboard Home shows the active season and quick operating metrics.',
                                'Seasons controls tournament settings such as sport, budget, bid increment, categories, registration forms, and active season.',
                                'Players is where you add, import, approve, edit, delete, and export player data.',
                                'Teams manages team names, short codes, budgets, device links, approvals, and exports.',
                                'Auction contains Control, Big Screen, and Rosters views for the live room.',
                                'Analytics and Audit help you review activity, spending, and important changes.',
                                'Settings controls display currency, conversion rate, custom domain, and organization information.',
                                'Billing controls subscriptions, plan upgrades, renewals, and payment history.',
                            ],
                        ],
                        [
                            'title' => '3. Configure organization settings',
                            'body' => 'Before creating your final auction data, open Settings and confirm the organization-level options. These settings affect how money, branding, and public URLs appear across the app.',
                            'items' => [
                                'Set display currency to BDT or USD depending on how you want users to see prices.',
                                'Set BDT per USD conversion rate when USD display is enabled. Internal auction amounts still use the canonical stored values.',
                                'If your plan supports it, configure a custom domain and complete DNS verification.',
                                'Use the current organization name and logo consistently so big-screen and public pages look polished.',
                            ],
                        ],
                        [
                            'title' => '4. Create and activate a season',
                            'body' => 'A season is one auction campaign or tournament. You can create multiple seasons, but only one should usually be active while you are preparing or running an event.',
                            'items' => [
                                'Choose the sport, such as cricket or football, because sport controls which player fields matter most.',
                                'Set budget per team before creating teams so each new team gets the right starting budget.',
                                'Set BDT bid increment and USD bid increment. Team devices use the correct increment for the current display currency.',
                                'Add player categories such as A, B, C, Icon, Local, Overseas, Batter, Bowler, All-rounder, or any custom structure.',
                                'Activate the season you want the dashboard, registration links, team devices, and auction room to use.',
                            ],
                        ],
                        [
                            'title' => '5. Build the player registration form',
                            'body' => 'Public registration lets players submit their own details. You can customize the form so you collect only the data needed for your tournament.',
                            'items' => [
                                'Open Seasons and enable public player registration for the active season.',
                                'Add custom fields when you need extra data such as previous team, district, shirt size, batting role, bowling type, or phone number.',
                                'Share the public registration link privately or publicly depending on your tournament rules.',
                                'Review submitted players in the Players page and approve or reject them before auction day.',
                            ],
                        ],
                        [
                            'title' => '6. Add players manually',
                            'body' => 'Manual entry is best for a small list, late additions, or corrections. Use the Add Player button from the Players page.',
                            'items' => [
                                'Enter player name, category, base price, position, and sport-specific details.',
                                'Upload a clear player photo when possible. Photos make the big screen and roster pages much easier to follow.',
                                'Keep base prices consistent with your category rules.',
                                'Use approve status to keep draft players separate from final auction-ready players.',
                            ],
                        ],
                        [
                            'title' => '7. Import players by CSV',
                            'body' => 'CSV import is the fastest path for a large tournament. Download the template first, fill it carefully, then preview before confirming.',
                            'items' => [
                                'Use the built-in CSV template from the Players import page.',
                                'Keep required columns such as name present and spelled exactly as the template expects.',
                                'Preview the import to catch missing columns, invalid prices, or malformed rows.',
                                'Confirm only after reviewing the preview. After import, spot-check a few players before the event.',
                            ],
                        ],
                        [
                            'title' => '8. Create teams and budgets',
                            'body' => 'Teams are the buyers in your auction. Their budgets control whether bids are valid and how much they can spend.',
                            'items' => [
                                'Add each team with a clear name and short code. Short codes look better on the big screen.',
                                'Confirm initial budget and remaining budget before starting the auction.',
                                'Use team registration if teams need to apply or submit details before approval.',
                                'Keep one admin responsible for reviewing budgets before the first lot starts.',
                            ],
                        ],
                        [
                            'title' => '9. Invite users and assign roles',
                            'body' => 'Not everyone needs full admin access. Assign roles based on the work each person will do during setup and live operation.',
                            'items' => [
                                'Organization admins manage settings, billing, users, seasons, players, teams, and auction setup.',
                                'Auctioneers can operate the live auction controls and bids.',
                                'Viewers can monitor screens and reports without changing key setup data.',
                                'Send invitations early and ask every operator to log in before auction day.',
                            ],
                        ],
                        [
                            'title' => '10. Prepare team device links',
                            'body' => 'Team device links let captains bid from phones or tablets without seeing admin tools. Treat each link like a private access key for that team.',
                            'items' => [
                                'Open Teams and generate or copy each team device link.',
                                'Send each team only its own link. Do not post all links publicly.',
                                'Ask captains to open their link before the auction starts so device and network issues are caught early.',
                                'Keep a backup admin bidder ready in case a team phone loses internet.',
                            ],
                        ],
                        [
                            'title' => '11. Set up the big screen',
                            'body' => 'The big screen is for the venue projector or TV. It shows the current player, bid, timer, leading team, bid history, budgets, and sold or unsold result.',
                            'items' => [
                                'Open Dashboard > Auction > Big Screen on the display laptop.',
                                'Use browser fullscreen mode and check that player photos, names, bids, and timer are readable from the room.',
                                'Test websocket updates by changing a player or placing a test bid from auction control.',
                                'Disable sleep mode on the display laptop and keep power connected.',
                            ],
                        ],
                        [
                            'title' => '12. Run a full test auction',
                            'body' => 'Before the real event, create a short test with two teams and two players. This catches almost every setup issue while there is still time to fix it.',
                            'items' => [
                                'Select a player in Auction Control and start the timer.',
                                'Place test bids from auction control and from a team device.',
                                'Mark one player SOLD and another UNSOLD to confirm result animations.',
                                'Check that team budget decreases only after sold confirmation.',
                                'Open Rosters to confirm sold players appear under the correct team.',
                            ],
                        ],
                        [
                            'title' => '13. Run the live auction',
                            'body' => 'During the event, keep one person on Auction Control and one person watching the big screen or room. The auctioneer should announce each action clearly before clicking.',
                            'items' => [
                                'Select the next player only when the room is ready.',
                                'Start, pause, resume, or extend the timer as needed.',
                                'Use valid bid increments and confirm the leading team before marking SOLD.',
                                'Use UNSOLD when no team buys the player. The stamp stays visible until the next player is selected.',
                                'Avoid refreshing or changing settings in the middle of an active bid unless necessary.',
                            ],
                        ],
                        [
                            'title' => '14. Review rosters and exports',
                            'body' => 'After the auction, use Rosters and exports to review final teams, spending, unsold players, and records for captains or sponsors.',
                            'items' => [
                                'Open Rosters to inspect final team lists and remaining budgets.',
                                'Export players, teams, or season summaries as CSV or PDF depending on your plan.',
                                'Use exported files for announcements, record keeping, social media, and dispute resolution.',
                                'Keep the audit log available if you need to review who changed important data.',
                            ],
                        ],
                        [
                            'title' => '15. Billing, plans, and payment activation',
                            'body' => 'Billing controls your plan limits and renewal state. Free plans are useful for testing; paid plans unlock larger usage and production-ready capacity.',
                            'items' => [
                                'Open Billing to see your current plan, limits, payment history, and renewal status.',
                                'Use PayPal checkout where available. Successful payments should activate the selected package automatically.',
                                'Use manual bKash payment when configured, then submit the transaction ID for admin review.',
                                'Keep payment references for support questions or refund review.',
                            ],
                        ],
                        [
                            'title' => '16. Troubleshooting checklist',
                            'body' => 'Most event-day issues come from cache, internet, permissions, missing data, or display setup. Work through this list before assuming the auction data is broken.',
                            'items' => [
                                'If a page looks old after deploy, clear browser cache and Laravel/Vite build cache on the server.',
                                'If realtime updates do not show, check websocket console errors and Reverb service status.',
                                'If player images break, check storage disk configuration and public storage URL.',
                                'If imports fail, confirm the CSV uses the template headers and includes required columns.',
                                'If team bidding fails, confirm the team has enough remaining budget and the active season is correct.',
                                'If email goes to junk, verify SPF, DKIM, and DMARC records in your email provider DNS settings.',
                            ],
                        ],
                    ],
                ]
            ),
            'auction-guide' => $this->page(
                'Auction Guide',
                'Docs',
                'How to run a live auction cleanly from first lot to final export.',
                [
                    ['Before the event', 'Verify teams, budgets, player categories, and the big-screen URL.'],
                    ['During bidding', 'Use fixed increments, extend the timer when needed, and mark each player sold or unsold.'],
                    ['After the event', 'Review rosters, export reports, and keep audit history for disputes.'],
                ],
                ['Assign an auctioneer', 'Test websocket updates', 'Keep a backup laptop ready', 'Export results after closing']
            ),
            'big-screen-setup' => $this->page(
                'Big-screen Setup',
                'Docs',
                'Set up the projector or TV display for the auction room.',
                [
                    ['Open the display URL', 'Use the dashboard Big Screen link on a laptop connected to the venue display.'],
                    ['Use a stable network', 'Keep the display device and auctioneer device on reliable internet.'],
                    ['Keep the display visible', 'Browser fullscreen mode and 100 percent zoom usually work best.'],
                ],
                ['Test before captains arrive', 'Disable sleep mode', 'Keep charger connected', 'Refresh after deployment updates']
            ),
            'team-device-guide' => $this->page(
                'Team-device Guide',
                'Docs',
                'Let captains bid from their phones with scoped team links.',
                [
                    ['Share the correct link', 'Each team should receive only its own device link or assigned account.'],
                    ['Check budgets', 'Remaining budget updates after bids and sold results.'],
                    ['Keep captains focused', 'The team device shows the current player, timer, and valid bid buttons.'],
                ],
                ['Send links privately', 'Ask captains to test login early', 'Keep one backup admin bidder']
            ),
            'community' => $this->page(
                'Community',
                'Support',
                'A place for organizers to share workflows, requests, and tournament lessons.',
                [
                    ['Organizer feedback', 'Feature requests from real auction rooms shape the roadmap.'],
                    ['Templates and examples', 'Reusable player CSVs and setup checklists help new organizers start faster.'],
                    ['Support-first culture', 'Bugs from live events get treated as practical workflow problems, not abstract tickets.'],
                ],
                ['Share a feature request', 'Report a workflow issue', 'Send a tournament case study']
            ),
            'status' => $this->page(
                'System Status',
                'Support',
                'Current operating notes for the hosted AuctionBall platform.',
                [
                    ['Application', 'The web app is designed to run from the primary AuctionBall domain and configured custom domains.'],
                    ['Realtime', 'Live auction screens use websocket connections and fall back to visible connection state when offline.'],
                    ['Email and payments', 'Transactional email and payment providers depend on verified third-party credentials.'],
                ],
                ['Check websocket console errors', 'Keep queue workers running', 'Verify payment webhooks after credential changes']
            ),
            'contact' => $this->page(
                'Contact',
                'Support',
                'Reach the AuctionBall team for setup help, billing, or event-day questions.',
                [
                    ['Support email', 'Send account and setup questions to support@auctionball.com.'],
                    ['Sales and enterprise', 'For larger leagues, include expected teams, players, event date, and branding needs.'],
                    ['Event-day help', 'Include screenshots, the organization slug, and the exact page URL when reporting issues.'],
                ],
                ['support@auctionball.com', 'Dhaka, Bangladesh', 'Include your organization slug']
            ),
            'terms' => $this->page(
                'Terms of Service',
                'Legal',
                'These terms explain the rules for using AuctionBall to manage organizations, teams, players, auctions, payments, and related tournament workflows.',
                [
                    ['1. Acceptance of terms', 'By creating an account, using an organization workspace, joining a team device, or accessing an AuctionBall auction page, you agree to use the service according to these terms and all applicable laws.'],
                    ['2. Accounts and organizations', 'You are responsible for the accuracy of your account information, the users you invite, the roles you assign, and all activity that happens inside organizations you manage. Keep passwords and team links secure.'],
                    ['3. Auction and participant data', 'You must only upload player, team, bidder, and tournament information that you have permission to collect and use. You are responsible for correcting inaccurate records and removing data that should not be stored.'],
                    ['4. Payments and subscriptions', 'Paid plans, PayPal payments, manual payments, billing limits, and subscription access are governed by the plan selected at checkout. Access may be limited if payment fails, is disputed, or is reversed.'],
                    ['5. Service availability', 'AuctionBall is designed for live event use, but internet service, third-party providers, hosting, email, payment processors, and venue networks can affect availability. Organizers should test before auction day.'],
                    ['6. Suspension and misuse', 'We may restrict or suspend access if the platform is used for abuse, spam, unauthorized access, illegal activity, attacks on realtime services, payment fraud, or activity that harms other users.'],
                    ['7. Changes to the service', 'AuctionBall may add, remove, rename, or improve features, adjust usage limits, update pricing, or change these terms as the product evolves. Continued use means you accept the updated terms.'],
                ],
                ['Use accurate account information', 'Keep admin credentials secure', 'Test your auction setup before the event', 'Contact support for billing or account concerns'],
                [
                    'updated_at' => 'Last updated: May 20, 2026',
                    'practice_title' => 'Plain-English summary',
                    'practice_body' => 'Use AuctionBall honestly, protect participant data, pay for the plan you use, and test your live auction setup before event day. These terms are written to keep the platform reliable for organizers and fair for participants.',
                ]
            ),
            'privacy' => $this->page(
                'Privacy Policy',
                'Legal',
                'This policy explains what information AuctionBall processes, why it is used, and how organizers can manage tournament and account data.',
                [
                    ['1. Information we collect', 'AuctionBall may process names, email addresses, phone numbers, organization details, player profiles, team records, photos, bid history, payment references, support messages, device information, and operational logs.'],
                    ['2. How the information is used', 'We use data to create accounts, run live auctions, synchronize bidding screens, manage rosters, process payments, send transactional email, provide support, prevent abuse, and improve product reliability.'],
                    ['3. Organizer-controlled data', 'Organization admins control much of the tournament data they add, including teams, players, seasons, images, budgets, and auction outcomes. Admins should only add data they are allowed to use.'],
                    ['4. Third-party services', 'AuctionBall may rely on hosting, email, payment, storage, analytics, and realtime infrastructure providers. These services process limited data needed to deliver the application.'],
                    ['5. Security and access', 'We use reasonable technical and operational safeguards, but no online system is perfect. Admins should use strong passwords, limit user access, and avoid sharing private team or admin links publicly.'],
                    ['6. Retention and deletion', 'Tournament and account data may be retained while an organization is active, for support, billing, audit, legal, or operational reasons. You may contact support for account or data questions.'],
                    ['7. Contact', 'For privacy questions or data requests, contact the AuctionBall team through the contact page or the support email configured for your account.'],
                ],
                ['Do not upload unnecessary sensitive data', 'Limit admin access', 'Remove old test data when no longer needed', 'Use verified email addresses for account recovery'],
                [
                    'updated_at' => 'Last updated: May 20, 2026',
                    'practice_title' => 'Your privacy controls',
                    'practice_body' => 'Most auction records are managed by organization admins. Keep only the player and tournament details you actually need, review who has dashboard access, and contact support when you need help with account or data questions.',
                ]
            ),
            'refunds' => $this->page(
                'Refund Policy',
                'Legal',
                'This policy explains how refund requests are reviewed for paid AuctionBall plans, failed activations, duplicate payments, and event-related issues.',
                [
                    ['1. General approach', 'Refunds are reviewed case by case. We try to be fair when there is a clear billing error, duplicate charge, failed plan activation, or technical issue that prevented reasonable use of the paid service.'],
                    ['2. Monthly subscriptions', 'Monthly plan payments are generally for the current billing period. If you do not want the next billing cycle, cancel or contact support before renewal. Used billing periods may not be refundable.'],
                    ['3. Duplicate or failed payments', 'If you were charged twice, payment succeeded but the plan did not activate, or PayPal/manual payment references do not match your account, send the payment reference and organization slug.'],
                    ['4. Event-day responsibility', 'Live auctions depend on venue internet, organizer devices, display setup, and third-party services. Contact support before the event if something looks wrong so there is time to help.'],
                    ['5. Non-refundable cases', 'Refunds may be denied for completed events, misuse of the service, violation of terms, late requests after substantial use, or issues caused only by local venue/network problems.'],
                    ['6. Processing time', 'Approved refunds are returned through the original payment method when possible. Payment provider rules, bank timing, and transaction fees may affect the final amount or timing.'],
                    ['7. How to request a refund', 'Contact support with your account email, organization slug, payment reference, plan name, charge date, and a clear explanation of the issue.'],
                ],
                ['Send payment reference', 'Include organization slug', 'Mention the charge date', 'Explain what went wrong clearly'],
                [
                    'updated_at' => 'Last updated: May 20, 2026',
                    'practice_title' => 'Before requesting a refund',
                    'practice_body' => 'Collect the PayPal or manual payment reference, the organization slug, and screenshots if activation failed. Clear details help us confirm the issue quickly and avoid back-and-forth.',
                ]
            ),
            'acceptable-use' => $this->page(
                'Acceptable Use',
                'Legal',
                'Rules that keep AuctionBall reliable for all organizers.',
                [
                    ['No abuse', 'Do not spam registrations, attack websocket endpoints, scrape private data, or upload harmful files.'],
                    ['Respect participants', 'Only collect player data you are allowed to collect for your tournament.'],
                    ['Operational safety', 'Do not intentionally overload live auctions, payment flows, or email systems.'],
                ],
                ['Use private links carefully', 'Remove abusive users', 'Report suspicious activity']
            ),
        ];
    }

    private function page(string $title, string $eyebrow, string $description, array $sections, array $checklist, array $extra = []): array
    {
        return array_merge(compact('title', 'eyebrow', 'description', 'sections', 'checklist'), $extra);
    }

    private function blogPostPayload(BlogPost $post, bool $includeBody = false): array
    {
        return array_filter([
            'title' => $post->title,
            'slug' => $post->slug,
            'url' => route('public.blog.show', $post),
            'category' => $post->category,
            'excerpt' => $post->excerpt,
            'body' => $includeBody ? $post->body : null,
            'meta_title' => $post->meta_title,
            'meta_description' => $post->meta_description,
            'read_time' => $post->read_time,
            'date' => $post->formattedDate(),
        ], fn ($value) => $value !== null);
    }
}
