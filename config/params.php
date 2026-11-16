<?php

$exportLimits = [
    'pro' => ['monthlyRows' => 5000, 'perFile' => 5000],
    'business' => ['monthlyRows' => 50000, 'perFile' => 50000],
    'bulk' => ['monthlyRows' => 1000000, 'perFile' => 100000],
    'admin' => ['monthlyRows' => null, 'perFile' => 100000],
];

$freeLimits = ['historyDays' => 7, 'lookupsPerDay' => 10];

return [
    'adminEmail' => env('ADMIN_EMAIL', 'admin@localhost'),
    'senderEmail' => env('ADMIN_EMAIL', 'admin@localhost'),
    'senderName' => 'EntityIndex',
    'siteUrl' => rtrim((string) env('SITE_URL'), '/'), // required: checked at boot by envAssertRequired()
    'siteMode' => env('SITE_MODE', 'live'), // live | coming_soon
    // Tell search engines not to index anything (meta tag + X-Robots-Tag header). Set SITE_NOINDEX=false at launch.
    'noindex' => envBool('SITE_NOINDEX', true), // fail-safe: stays ON unless explicitly set to false
    // Database (same server) holding the replicated portal table manual_companies, and the time zone date_added is read in
    // (a TIMESTAMP column comes back in this server's session time zone, UTC on the Lightsail servers).
    'mirrorDb' => env('MIRROR_DB', 'ein_skip_tracing'),
    'mirrorTz' => env('MIRROR_TZ', 'UTC'),
    // Calendar days on the home page ("added per day", "today") are counted in this time zone.
    'reportTz' => env('REPORT_TZ', 'America/New_York'),

    // API limits per plan; admins are unlimited. Not final: change the numbers here and nothing else.
    //   monthlyCalls  requests per calendar month (UTC), all of the account's keys together
    //   monthlyRows   companies returned per calendar month; a request is cut short at the cap, then 429
    //   perMinute     requests per minute
    // The portal API pages, the API headers and the docs page all read these values.
    'apiLimits' => [
        'business' => ['monthlyCalls' => 25000, 'monthlyRows' => 50000, 'perMinute' => 120],
        'bulk' => ['monthlyCalls' => 500000, 'monthlyRows' => 5000000, 'perMinute' => 600],
    ],

    // A company from the mirrored feed goes public at publish_at (entities.publish_at); every portal, API and export read hides it before then.
    //   qualified = 0     published as soon as it arrives
    //   qualified = 1     held for this many hours after the row was added
    //   qualified NULL    held until the source sets it
    // Not final: change the number here and nothing else (rows already synced keep their publish_at until they are synced again).
    'publishHoldHours' => 48,

    // Free plan limits. Not final: change the numbers here and nothing else.
    //   historyDays    company pages only open for filings from the last N days, counted back from the latest filing day (search and filters cover everything)
    //   lookupsPerDay  different company pages a free account can open a day (reportTz calendar day); reopening one the same day is free
    // Paid plans and admins have neither limit. The pricing text below, the Companies page and the tests read these values.
    'freeLimits' => $freeLimits,

    // CSV export limits per plan (the Companies page "Export CSV" button). Not final: change the numbers here and nothing else.
    //   monthlyRows   rows exported per calendar month (UTC), counted separately from the API row cap; null is unlimited
    //   perFile       most rows in a single download
    // Free accounts cannot export. The pricing text below, the Companies page and the tests read these values.
    'exportLimits' => $exportLimits,

    'bulk' => [
        'dir' => env('BULK_DIR', '@runtime/bulk'),
        'sendfile' => env('BULK_SENDFILE', 'php'),
        'keepDays' => 7,
        'downloadsPerDay' => 20,
    ],

    // Proposed prices — change here; the pricing page and home page read from this list.
    'tiers' => [
        [
            'id' => 'free', 'name' => 'Free', 'price' => '$0', 'per' => '', 'for' => 'Anyone looking up a company',
            'features' => ['Company pages: name, state, type, filing date, status, registered agent', 'Basic search', 'Last ' . $freeLimits['historyDays'] . ' days of new filings', $freeLimits['lookupsPerDay'] . ' full lookups per day'],
            'cta' => 'Create free account', 'url' => ['/portal/auth/register'],
        ],
        [
            'id' => 'pro', 'name' => 'Pro', 'price' => '$49', 'per' => '/mo', 'for' => 'Agents, accountants, small sales teams', 'featured' => true,
            'features' => ['Full records incl. officers + addresses', 'Unlimited search, all history', 'Saved-search email alerts every morning', 'CSV export up to ' . number_format($exportLimits['pro']['monthlyRows']) . ' rows/month'],
            'cta' => 'Join the waitlist', 'url' => ['/pricing/index', 'plan' => 'pro', '#' => 'waitlist'],
        ],
        [
            'id' => 'business', 'name' => 'Business / API', 'price' => '$299', 'per' => '/mo', 'for' => 'Agencies, SaaS, lenders',
            'features' => ['REST API — 25,000 calls/month', 'Webhooks for new filings', number_format($exportLimits['business']['monthlyRows']) . ' export rows/month', 'All historical data', 'Self-serve API key in 2 minutes'],
            'cta' => 'Join the waitlist', 'url' => ['/pricing/index', 'plan' => 'business', '#' => 'waitlist'],
        ],
        [
            'id' => 'bulk', 'name' => 'Data license', 'price' => '$1,500+', 'per' => '/mo', 'for' => 'Data platforms, AI companies, list brokers',
            'features' => ['Daily or weekly full-feed files', 'CSV or Parquet via S3/SFTP', 'Per-state or national', 'Redistribution license available'],
            'cta' => 'Talk to us', 'url' => ['/pricing/index', 'plan' => 'bulk', '#' => 'waitlist'],
        ],
    ],
];
