<?php

return [

    /* ---------- Mailables ---------- */
    'mail' => [
        'thanks_signoff'         => 'Thanks,',
        'team_signoff'           => 'The AuctionBall team',

        'invitation' => [
            'subject'       => "You've been invited to :org on AuctionBall",
            'heading'       => 'Welcome to :org on AuctionBall',
            'invited_by'    => ':name invited you to join **:org** as a **:role**:teamSuffix.',
            'team_suffix'   => ' for team **:team**',
            'role_team_owner_blurb' => "As a team owner, you'll be able to bid on players, track your team's budget, and view your squad's performance during the live auction.",
            'role_auctioneer_blurb' => "As an auctioneer, you'll be able to run the live auction control panel — start, pause, sold, unsold, and reset bids during the session.",
            'cta_accept'    => 'Accept invitation',
            'expires_note'  => "This invitation expires on **:date**. If you didn't expect this email, you can safely ignore it.",
        ],

        'renewed' => [
            'subject'       => 'Your AuctionBall :plan plan has been renewed',
            'heading'       => 'Plan renewed ✓',
            'body_intro'    => 'Hi :org,',
            'body_main'     => 'Your **:plan** plan has been renewed for another billing cycle.',
            'amount'        => 'Amount',
            'next_billing'  => 'Next billing date',
            'provider'      => 'Provider',
            'cta'           => 'View billing',
        ],

        'renewal_due' => [
            'subject'       => 'Action needed: renew your AuctionBall :plan plan',
            'heading'       => 'Renewal payment needed',
            'body_intro'    => 'Hi :org,',
            'body_main'     => 'Your AuctionBall **:plan** plan billing cycle is ending and your payment provider (:provider) does not support automatic charges. To keep your plan active, please complete a renewal payment from the billing page.',
            'cta'           => 'Renew now',
            'grace_warning' => "If we don't receive a payment, your plan will be moved to Free in **:days days**.",
        ],

        'renewal_failed' => [
            'subject'       => 'Renewal attempt :attempt of :max failed — AuctionBall',
            'heading'       => 'Renewal attempt failed',
            'body_intro'    => 'Hi :org,',
            'body_main'     => 'We tried to renew your **:plan** plan via :provider and it failed.',
            'attempt'       => 'Attempt',
            'reason'        => 'Reason',
            'grace_until'   => 'Grace period ends',
            'will_retry'    => "We'll retry automatically. To avoid losing access, please open the billing page and update your payment method or trigger a manual renewal.",
            'cta'           => 'Open billing',
        ],

        'downgraded' => [
            'subject'       => 'Your AuctionBall plan has been moved to Free',
            'heading'       => 'Plan moved to Free',
            'body_intro'    => 'Hi :org,',
            'body_main'     => 'After :max failed renewal attempts, your AuctionBall plan has been moved to **Free**.',
            'last_failure'  => 'Last failure reason',
            'data_safe'     => 'Your data is safe — players, teams, seasons, bid history all stay intact. Free-tier limits now apply (1 active season, up to 20 players, up to 4 teams). Upgrade any time to restore full access.',
            'cta'           => 'Upgrade again',
        ],

        'payment_approved' => [
            'subject'       => 'Your AuctionBall :plan plan is now active',
            'heading'       => 'Payment verified ✓',
            'body_intro'    => 'Hi :org,',
            'body_main'     => 'We verified your bKash payment and activated your **:plan** plan.',
            'amount'        => 'Amount paid',
            'trx_id'        => 'bKash TrxID',
            'activated_at'  => 'Activated at',
            'unlock_note'   => 'All features included in your plan are unlocked — players, teams, exports, watermark settings — everything reflects the new plan immediately.',
            'cta'           => 'Open billing',
        ],

        'payment_rejected' => [
            'subject'       => "We couldn't verify your AuctionBall payment",
            'heading'       => 'Payment not verified',
            'body_intro'    => 'Hi :org,',
            'body_main'     => "We couldn't verify the bKash payment you submitted for the **:plan** plan.",
            'amount'        => 'Amount submitted',
            'trx_id'        => 'bKash TrxID',
            'reason'        => 'Reason',
            'help'          => 'No charge was applied to your account. If you believe this is an error, reply to this email with your bKash payment screenshot — we will review it manually.',
            'cta'           => 'Open billing',
        ],

        'reminder' => [
            'subject_auto'    => 'Heads-up: your AuctionBall plan renews in :days days',
            'subject_manual'  => 'Action needed: your AuctionBall plan ends in :days days',
            'heading_auto'    => 'Renewal coming up',
            'heading_manual'  => 'Action needed: renew your plan',
            'body_intro'      => 'Hi :org,',
            'auto_body'       => 'Your **:plan** plan will auto-renew on **:date** (:days from now) via :provider for **:amount**. No action needed — we just wanted to let you know.',
            'auto_followup'   => "If you'd like to switch plans or cancel auto-renewal, the billing page has the controls.",
            'manual_body'     => 'Your **:plan** plan ends on **:date** (:days from now). Auto-renew is OFF, so we won\'t charge you — but your plan will move to Free after that date unless you renew manually.',
            'cta'             => 'Open billing',
        ],
    ],

    /* ---------- PDF exports ---------- */
    'pdf' => [
        'players_title'       => 'Players export',
        'players_subtitle'    => ':count rows · generated :datetime',
        'teams_title'         => 'Teams · :season',
        'teams_subtitle'      => ':count teams · generated :datetime',
        'season_subtitle'     => 'Season summary · generated :datetime',
        'col_index'           => '#',
        'col_name'            => 'Name',
        'col_category'        => 'Category',
        'col_type'            => 'Type',
        'col_base'            => 'Base',
        'col_status'          => 'Status',
        'col_sold'            => 'Sold',
        'col_team'            => 'Team',
        'col_batting'         => 'Batting',
        'col_bowling'         => 'Bowling',
        'col_short'           => 'Short',
        'col_owner'           => 'Owner',
        'col_players_bought'  => 'Players bought',
        'col_initial'         => 'Initial',
        'col_spent'           => 'Spent',
        'col_remaining'       => 'Remaining',
        'col_players'         => 'Players',
        'kpi_total_spent'     => 'Total spent',
        'kpi_players_sold'    => 'Players sold',
        'kpi_unsold'          => 'Unsold',
        'kpi_total_bids'      => 'Total bids',
        'h_team_leaderboard'  => 'Team leaderboard',
        'h_top_sold'          => 'Top sold players',
        'page'                => 'Page',
    ],

];
