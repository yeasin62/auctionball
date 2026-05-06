<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Support\Audit;
use Illuminate\Console\Command;

/**
 * DNS-based custom-domain verification.
 *
 * The org admin gets a token on save (e.g. "ab-verify=abc123def"). They publish
 * it as a TXT record on `_auctionball.{their-domain}`. This command (or the
 * one-shot verify endpoint) does a `dns_get_record(... DNS_TXT)` and stamps
 * `custom_domain_verified_at = now()` on a match.
 */
class VerifyCustomDomain extends Command
{
    protected $signature = 'auctionball:verify-domain
                            {org : Organization id or slug}
                            {--all : Re-verify all orgs that have a domain set (cron-friendly)}';

    protected $description = 'Verify an organization\'s custom domain by DNS TXT record lookup.';

    public function handle(): int
    {
        $orgs = $this->option('all')
            ? Organization::whereNotNull('custom_domain')->get()
            : Organization::where(fn ($q) => $q->where('id', $this->argument('org'))->orWhere('slug', $this->argument('org')))->get();

        if ($orgs->isEmpty()) {
            $this->error('No matching organization.');
            return self::FAILURE;
        }

        foreach ($orgs as $org) {
            if (empty($org->custom_domain) || empty($org->custom_domain_verification_token)) {
                $this->line("  · skip #{$org->id} ({$org->name}) — no domain or token");
                continue;
            }

            $expected = $org->custom_domain_verification_token;
            $hostname = '_auctionball.' . $org->custom_domain;
            $records  = @dns_get_record($hostname, DNS_TXT) ?: [];
            $match    = collect($records)->contains(fn ($r) => str_contains($r['txt'] ?? '', $expected));

            if ($match) {
                $org->update(['custom_domain_verified_at' => now()]);
                Audit::log(
                    'domain.verified',
                    "Custom domain {$org->custom_domain} verified",
                    ['domain' => $org->custom_domain],
                    $org,
                    $org->id,
                );
                $this->info("  ✓ {$org->custom_domain} verified ({$org->name})");
            } else {
                $this->warn("  ✗ {$org->custom_domain} TXT record missing or mismatch");
                $this->line("     expected: {$hostname} TXT \"ab-verify={$expected}\"");
            }
        }

        return self::SUCCESS;
    }
}
