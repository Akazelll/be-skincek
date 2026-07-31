<?php

return [
    'api_path' => 'api/v1',
    'api_domain' => null,
    'export_path' => 'api.json',

    'cache' => [
        'key' => 'scramble.openapi',
        'store' => 'file',
    ],

    'info' => [
        'version' => '1.0.0',
        'description' => 'REST API untuk autentikasi, profil, dan analisis kulit SkinCek.',
    ],

    'ui' => [
        'title' => 'SkinCek API v1',
    ],

    'renderer' => 'elements',

    'renderers' => [
        'elements' => [
            'view' => 'scramble::docs',
            'theme' => 'light',
            'hideTryIt' => false,
            'hideSchemas' => false,
            'logo' => '',
            'tryItCredentialsPolicy' => 'include',
            'layout' => 'responsive',
            'router' => 'hash',
        ],
    ],

    'servers' => null,
    'middleware' => ['web'],
    'extensions' => [],
];
