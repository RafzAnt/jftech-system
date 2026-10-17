<?php

/*
| The employee app can run in a web browser (for example `flutter run -d chrome`), and a browser only hands the reply
| to the app when the server allows it. The app sends its sign-in token in the Authorization header, not in a cookie,
| so every origin may call the app API; the admin pages stay closed because only the app API paths are listed here.
*/
return [
    'paths' => ['backend/api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 600,

    'supports_credentials' => false,
];
