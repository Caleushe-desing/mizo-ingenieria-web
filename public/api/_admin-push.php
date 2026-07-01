<?php
declare(strict_types=1);

require_once __DIR__ . '/_catalog-db.php';

function mizo_admin_push_storage_dir(): string
{
    return __DIR__ . '/../mizo-data';
}

function mizo_admin_push_devices_file(): string
{
    return mizo_admin_push_storage_dir() . '/admin-devices.json';
}

function mizo_admin_push_state_file(): string
{
    return mizo_admin_push_storage_dir() . '/admin-notify-state.json';
}

function mizo_admin_push_ensure_storage(): void
{
    $dir = mizo_admin_push_storage_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    if (is_dir($dir) && !is_file($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
    }
    if (!is_file(mizo_admin_push_devices_file())) {
        @file_put_contents(mizo_admin_push_devices_file(), "[]\n", LOCK_EX);
    }
    if (!is_file(mizo_admin_push_state_file())) {
        @file_put_contents(mizo_admin_push_state_file(), json_encode([
            'sequence' => 0,
            'latestAt' => null,
            'title' => '',
            'body' => '',
            'url' => '/admin/#leads',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL, LOCK_EX);
    }
}

function mizo_admin_push_read_json(string $file): array
{
    mizo_admin_push_ensure_storage();
    $items = json_decode((string) @file_get_contents($file), true);
    return is_array($items) ? $items : [];
}

function mizo_admin_push_write_json(string $file, $payload): bool
{
    mizo_admin_push_ensure_storage();
    return @file_put_contents(
        $file,
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        LOCK_EX
    ) !== false;
}

function mizo_admin_push_vapid_keys(): array
{
    $public = trim((string) (mizo_env(['VAPID_PUBLIC_KEY', 'MIZO_VAPID_PUBLIC_KEY'], '') ?? ''));
    $private = trim((string) (mizo_env(['VAPID_PRIVATE_KEY', 'MIZO_VAPID_PRIVATE_KEY'], '') ?? ''));
    $subject = trim((string) (mizo_env(['VAPID_SUBJECT', 'MIZO_VAPID_SUBJECT'], 'mailto:ventas@mizo.cl') ?? 'mailto:ventas@mizo.cl'));

    return [
        'public' => $public,
        'private' => $private,
        'subject' => $subject !== '' ? $subject : 'mailto:ventas@mizo.cl',
        'configured' => $public !== '' && $private !== '',
    ];
}

function mizo_admin_push_new_token(): string
{
    return 'dev_' . bin2hex(random_bytes(18));
}

function mizo_admin_push_register_device(array $payload): array
{
    $devices = mizo_admin_push_read_json(mizo_admin_push_devices_file());
    $token = mizo_admin_push_new_token();
    $subscription = $payload['subscription'] ?? null;

    $device = [
        'token' => $token,
        'createdAt' => gmdate('c'),
        'lastSeenAt' => gmdate('c'),
        'lastAckSequence' => (int) (mizo_admin_push_read_json(mizo_admin_push_state_file())['sequence'] ?? 0),
        'userAgent' => substr((string) ($payload['userAgent'] ?? ''), 0, 240),
        'pushSubscription' => is_array($subscription) ? $subscription : null,
    ];

    array_unshift($devices, $device);
    $devices = array_slice($devices, 0, 25);
    mizo_admin_push_write_json(mizo_admin_push_devices_file(), $devices);

    return $device;
}

function mizo_admin_push_update_subscription(string $token, ?array $subscription): bool
{
    if ($token === '' || !is_array($subscription)) {
        return false;
    }

    $devices = mizo_admin_push_read_json(mizo_admin_push_devices_file());
    $updated = false;
    foreach ($devices as &$device) {
        if (($device['token'] ?? '') !== $token) {
            continue;
        }
        $device['pushSubscription'] = $subscription;
        $device['lastSeenAt'] = gmdate('c');
        $updated = true;
        break;
    }
    unset($device);

    return $updated ? mizo_admin_push_write_json(mizo_admin_push_devices_file(), $devices) : false;
}

function mizo_admin_push_find_device(string $token): ?array
{
    foreach (mizo_admin_push_read_json(mizo_admin_push_devices_file()) as $device) {
        if (($device['token'] ?? '') === $token) {
            return $device;
        }
    }
    return null;
}

function mizo_admin_push_ack_device(string $token, int $sequence): void
{
    $devices = mizo_admin_push_read_json(mizo_admin_push_devices_file());
    foreach ($devices as &$device) {
        if (($device['token'] ?? '') !== $token) {
            continue;
        }
        $device['lastAckSequence'] = $sequence;
        $device['lastSeenAt'] = gmdate('c');
        break;
    }
    unset($device);
    mizo_admin_push_write_json(mizo_admin_push_devices_file(), $devices);
}

function mizo_admin_push_check_device(string $token): array
{
    $device = mizo_admin_push_find_device($token);
    if (!$device) {
        return ['ok' => false, 'error' => 'Dispositivo no registrado.'];
    }

    $state = mizo_admin_push_read_json(mizo_admin_push_state_file());
    $sequence = (int) ($state['sequence'] ?? 0);
    $lastAck = (int) ($device['lastAckSequence'] ?? 0);

    if ($sequence <= $lastAck) {
        mizo_admin_push_ack_device($token, $lastAck);
        return ['ok' => true, 'notify' => false, 'sequence' => $sequence];
    }

    mizo_admin_push_ack_device($token, $sequence);

    return [
        'ok' => true,
        'notify' => true,
        'sequence' => $sequence,
        'title' => (string) ($state['title'] ?? 'Nuevo formulario Mizo'),
        'body' => (string) ($state['body'] ?? 'Hay una nueva solicitud en el panel.'),
        'url' => (string) ($state['url'] ?? '/admin/#leads'),
    ];
}

function mizo_admin_notify_event(string $title, string $body, string $url = '/admin/#leads'): void
{
    mizo_admin_push_ensure_storage();
    $state = mizo_admin_push_read_json(mizo_admin_push_state_file());
    $sequence = (int) ($state['sequence'] ?? 0) + 1;

    mizo_admin_push_write_json(mizo_admin_push_state_file(), [
        'sequence' => $sequence,
        'latestAt' => gmdate('c'),
        'title' => $title,
        'body' => $body,
        'url' => $url,
    ]);

    mizo_admin_push_send_web_push($title, $body, $url);
}

function mizo_admin_push_send_web_push(string $title, string $body, string $url): void
{
    $vapid = mizo_admin_push_vapid_keys();
    if (!$vapid['configured']) {
        return;
    }

    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        return;
    }

    require_once $autoload;

    if (!class_exists('Minishlink\\WebPush\\WebPush')) {
        return;
    }

    try {
        $auth = [
            'VAPID' => [
                'subject' => $vapid['subject'],
                'publicKey' => $vapid['public'],
                'privateKey' => $vapid['private'],
            ],
        ];
        $webPush = new Minishlink\WebPush\WebPush($auth);
        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        foreach (mizo_admin_push_read_json(mizo_admin_push_devices_file()) as $device) {
            $subscription = $device['pushSubscription'] ?? null;
            if (!is_array($subscription)) {
                continue;
            }
            $webPush->queueNotification(
                Minishlink\WebPush\Subscription::create($subscription),
                $payload ?: '{}'
            );
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                continue;
            }
        }
    } catch (Throwable $error) {
        return;
    }
}
