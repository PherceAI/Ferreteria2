<?php

declare(strict_types=1);

return [
    'client_id' => env('GMAIL_CLIENT_ID', ''),
    'client_secret' => env('GMAIL_CLIENT_SECRET', ''),
    'redirect_uri' => env('GMAIL_REDIRECT_URI', ''),
    'scopes' => [
        'https://www.googleapis.com/auth/gmail.modify',
    ],
    'poll_query' => env('GMAIL_POLL_QUERY', 'is:unread has:attachment filename:xml'),
    'branch_id' => env('GMAIL_INBOX_BRANCH_ID'),
    'token_endpoint' => 'https://oauth2.googleapis.com/token',
    'auth_endpoint' => 'https://accounts.google.com/o/oauth2/v2/auth',
    'api_base' => 'https://gmail.googleapis.com/gmail/v1/users/me',
];
