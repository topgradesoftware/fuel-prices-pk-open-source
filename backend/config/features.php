<?php

return [
    'google_login' => (bool) env('FEATURE_GOOGLE_LOGIN', true),
    'push_notifications' => (bool) env('FEATURE_PUSH_NOTIFICATIONS', true),
    'crashlytics' => (bool) env('FEATURE_CRASHLYTICS', true),
    'analytics' => (bool) env('FEATURE_ANALYTICS', true),
    'favorites' => (bool) env('FEATURE_FAVORITES', true),
    'guides' => (bool) env('FEATURE_GUIDES', true),
    'ai_prediction' => (bool) env('FEATURE_AI_PREDICTION', false),
    'nearby_stations' => (bool) env('FEATURE_NEARBY_STATIONS', true) && filled(env('GOOGLE_MAPS_API_KEY')),
];
