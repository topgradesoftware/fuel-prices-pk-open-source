<?php

return [
    'project_id' => env('FIREBASE_PROJECT_ID'),
    'client_email' => env('FIREBASE_CLIENT_EMAIL'),
    'private_key' => env('FIREBASE_PRIVATE_KEY')
        ? str_replace('\\n', "\n", (string) env('FIREBASE_PRIVATE_KEY'))
        : null,
    'credentials' => env('FIREBASE_CREDENTIALS'),
    'fcm_enabled' => (bool) env('FCM_ENABLED', false),
];
