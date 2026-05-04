<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

$auth = [
    'VAPID' => [
        'subject' => config('webpush.vapid.subject'),
        'publicKey' => config('webpush.vapid.public_key'),
        'privateKey' => config('webpush.vapid.private_key'),
    ],
];

$payload = json_encode([
    'title' => '🟢 DEBUG payload',
    'body' => 'Si ves esto en tu dispositivo, el push SÍ llega. Si no, FCM lo acepta pero el browser no puede descifrar.',
    'icon' => '/icons/icon-192.png',
    'data' => ['url' => '/dashboard', 'severity' => 'critical'],
]);

echo 'Payload size: '.strlen($payload).' bytes'.PHP_EOL.PHP_EOL;

$users = User::whereHas('pushSubscriptions')->get();
$webPush = new WebPush($auth);

foreach ($users as $u) {
    foreach ($u->pushSubscriptions as $s) {
        echo "→ sub #{$s->id} user={$u->email}".PHP_EOL;
        $sub = Subscription::create([
            'endpoint' => $s->endpoint,
            'publicKey' => $s->public_key,
            'authToken' => $s->auth_token,
            'contentEncoding' => $s->content_encoding,
        ]);
        $webPush->queueNotification($sub, $payload);
    }
}

echo PHP_EOL.'=== Respuestas FCM (detalladas) ==='.PHP_EOL;
foreach ($webPush->flush() as $report) {
    $endpoint = $report->getEndpoint();
    $host = parse_url($endpoint, PHP_URL_HOST);
    $id = substr(basename(parse_url($endpoint, PHP_URL_PATH)), 0, 20);
    $ok = $report->isSuccess() ? '✓ OK' : '✗ FAIL';
    $status = $report->getResponse() ? $report->getResponse()->getStatusCode() : 'no-response';
    $reason = $report->getReason();
    echo "{$ok}  http={$status}  id={$id}  reason={$reason}".PHP_EOL;

    if (! $report->isSuccess()) {
        echo '    body: '.($report->getResponse() ? (string) $report->getResponse()->getBody() : '—').PHP_EOL;
    }

    if ($report->isSubscriptionExpired()) {
        echo '    ⚠️ SUBSCRIPTION EXPIRED — hay que eliminarla'.PHP_EOL;
    }
}
