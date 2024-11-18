<?php
// Database connection information.
$settings['database'] = [
    'host' => 'localhost',
    'user' => 'username',
    'password' => 'password1',
    'database' => 'rss2bluesky',
    'charset' => 'utf8mb4',
    'port' => 3306,
];

// Server settings.
$settings['server'] = [
    'temp_dir' => '/tmp',
];

// Bluesky settings.
$settings['bluesky'] = [
    'app_password' => 'aaaa-bbbb-cccc-dddd',
    'username' => 'name.bsky.social',
];

// An array of RSS feed URLs.
$settings['feeds'] = [
    'https://example.com/feed/rss',
    'https://www.example2.com/feed/',
    'https://example3.com/feed/',
];
