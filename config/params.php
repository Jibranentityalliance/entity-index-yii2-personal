<?php

return [
    'adminEmail' => env('ADMIN_EMAIL', 'admin@entityindex.com'),
    'senderEmail' => env('ADMIN_EMAIL', 'admin@entityindex.com'),
    'senderName' => 'EntityIndex',
    'siteUrl' => rtrim((string) env('SITE_URL', 'https://entityindex.com'), '/'),
    'siteMode' => env('SITE_MODE', 'live'), // live | coming_soon
    'sitemapPageSize' => 50000,

    // Proposed prices — change here; the pricing page and home page read from this list.
    'tiers' => [
        [
            'id' => 'free', 'name' => 'Free', 'price' => '$0', 'per' => '', 'for' => 'Anyone looking up a company',
            'features' => ['Company pages: name, state, type, filing date, status, registered agent', 'Basic search', 'Last 7 days of new filings', '10 full lookups per day'],
            'cta' => 'Start searching', 'url' => ['/search/index'],
        ],
        [
            'id' => 'pro', 'name' => 'Pro', 'price' => '$49', 'per' => '/mo', 'for' => 'Agents, accountants, small sales teams', 'featured' => true,
            'features' => ['Full records incl. officers + addresses', 'Unlimited search, all history', 'Saved-search email alerts every morning', 'CSV export up to 5,000 rows/month'],
            'cta' => 'Join the waitlist', 'url' => ['/pricing/index', 'plan' => 'pro', '#' => 'waitlist'],
        ],
        [
            'id' => 'business', 'name' => 'Business / API', 'price' => '$299', 'per' => '/mo', 'for' => 'Agencies, SaaS, lenders',
            'features' => ['REST API — 25,000 calls/month', 'Webhooks for new filings', '50,000 export rows/month', 'All historical data', 'Self-serve API key in 2 minutes'],
            'cta' => 'Join the waitlist', 'url' => ['/pricing/index', 'plan' => 'business', '#' => 'waitlist'],
        ],
        [
            'id' => 'bulk', 'name' => 'Data license', 'price' => '$1,500+', 'per' => '/mo', 'for' => 'Data platforms, AI companies, list brokers',
            'features' => ['Daily or weekly full-feed files', 'CSV or Parquet via S3/SFTP', 'Per-state or national', 'Redistribution license available'],
            'cta' => 'Talk to us', 'url' => ['/pricing/index', 'plan' => 'bulk', '#' => 'waitlist'],
        ],
    ],
];
