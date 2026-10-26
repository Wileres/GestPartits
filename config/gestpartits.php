<?php

return [

    /*
    | Zona horària en què es desen i es mostren les dates i hores dels partits
    | (DATE + TIME locals, sense conversió). Les marques de temps de l'aplicació
    | (created_at, auditoria...) es desen en UTC.
    */
    'timezone' => env('CLUB_TIMEZONE', 'Europe/Madrid'),

    /*
    | IP dels proxys inversos de confiança (separades per comes, o «*»). Buit si Nginx/Apache
    | serveixen directament l'aplicació.
    */
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    /*
    | Usuari inicial creat pel seeder. La contrasenya només s'accepta des de
    | l'entorn: mai no s'escriu al repositori.
    */
    'initial_user' => [
        'username' => env('INITIAL_USER_USERNAME', 'Gestor'),
        'name' => env('INITIAL_USER_NAME', 'Gestor'),
        'email' => env('INITIAL_USER_EMAIL'),
        'password' => env('INITIAL_USER_PASSWORD'),
    ],

    'auth' => [
        // Intents d'inici de sessió permesos per usuari i IP abans del bloqueig temporal.
        'max_login_attempts' => 5,
        // Segons de bloqueig després d'esgotar els intents.
        'lockout_seconds' => 60,
        // Intents d'inici de sessió per IP i minut (independentment de l'usuari).
        'max_login_attempts_per_ip' => 20,
        'password_min_length' => 12,
    ],

    'uploads' => [
        'logo_max_kilobytes' => 2048,
        'logo_mimes' => ['image/png', 'image/jpeg', 'image/webp'],
        'logo_max_dimension' => 2000,
    ],

    'whatsapp' => [
        'default_provider' => env('WHATSAPP_DEFAULT_PROVIDER', 'manual'),
        // Simulació (proveïdor «log»): només en entorns que no són de producció.
        'log_enabled' => (bool) env('WHATSAPP_LOG_ENABLED', env('APP_ENV') !== 'production'),
        'gateway' => [
            'enabled' => (bool) env('WHATSAPP_GATEWAY_ENABLED', false),
            'url' => env('WHATSAPP_GATEWAY_URL'),
            'token' => env('WHATSAPP_GATEWAY_TOKEN'),
        ],
        'meta' => [
            'enabled' => (bool) env('WHATSAPP_META_ENABLED', false),
            'phone_number_id' => env('WHATSAPP_META_PHONE_NUMBER_ID'),
            'token' => env('WHATSAPP_META_TOKEN'),
        ],
        'twilio' => [
            'enabled' => (bool) env('WHATSAPP_TWILIO_ENABLED', false),
            'account_sid' => env('TWILIO_ACCOUNT_SID'),
            'auth_token' => env('TWILIO_AUTH_TOKEN'),
            'from' => env('TWILIO_WHATSAPP_FROM'),
        ],
    ],

    'images' => [
        // chromium (recomanat), gd (servidors sense Chromium) o fake (proves).
        'renderer' => env('IMAGE_RENDERER', 'chromium'),
        'chromium_binary' => env('CHROMIUM_BINARY', '/usr/bin/chromium'),
        'gd_font_regular' => env('GD_FONT_REGULAR', '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'),
        'gd_font_bold' => env('GD_FONT_BOLD', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'),
        'width' => 1080,
        'height' => 1920,
    ],

];
