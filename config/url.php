<?php

return [
    'enablePrettyUrl' => true,
    'showScriptName' => false,
    'enableStrictParsing' => false,
    'rules' => [
        '' => 'site/index',
        'features' => 'site/features',
        'faq' => 'site/faq',
        'about' => 'site/about',
        'contact' => 'site/contact',
        'coming-soon' => 'site/coming-soon',
        'pricing' => 'pricing/index',
        'opt-out' => 'opt-out/index',
        'legal/privacy' => 'legal/privacy',
        'legal/terms' => 'legal/terms',
        'api/waitlist' => 'forms/waitlist',
        'api/opt-out' => 'forms/opt-out',
        'api/contact' => 'forms/contact',
        'sitemap.xml' => 'sitemap/index',

        'api/v1' => 'apiv1/default/index',
        'api/v1/me' => 'apiv1/me/index',
        'api/v1/entities' => 'apiv1/entities/index',
        'api/v1/entities/<id:\d+>' => 'apiv1/entities/view',
        'api/v1/entities/<state:[A-Za-z]{2}>/<filing:[A-Za-z0-9-]+>' => 'apiv1/entities/filing',
        'api/v1/<path:.*>' => 'apiv1/default/not-found',

        'portal' => 'portal/default/index',
        'portal/company/<state:[A-Za-z0-9-]+>/<slug:[A-Za-z0-9-]+>' => 'portal/company/view',
        'portal/<controller:[\w-]+>' => 'portal/<controller>/index',
        'portal/<controller:[\w-]+>/<action:[\w-]+>' => 'portal/<controller>/<action>',

        'admin' => 'admin/default/index',
        'admin/<controller:[\w-]+>' => 'admin/<controller>/index',
        'admin/<controller:[\w-]+>/<action:[\w-]+>' => 'admin/<controller>/<action>',
    ],
];
