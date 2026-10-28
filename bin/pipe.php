#!/usr/local/bin/php -q
<?php
// cPanel "Default Address -> Pipe to a Program" target. Reads one email on stdin.
// It must print nothing and exit 0, otherwise the sender gets a bounce.
declare(strict_types=1);

ob_start();
try {
    require dirname(__DIR__) . '/app/bootstrap.php';
    $raw = stream_get_contents(STDIN);
    $rcpt = [];
    foreach (['RECIPIENT', 'ORIGINAL_RECIPIENT'] as $var) {
        if (getenv($var)) {
            $rcpt[] = (string) getenv($var);
        }
    }
    if (getenv('LOCAL_PART') && getenv('DOMAIN')) {
        $rcpt[] = getenv('LOCAL_PART') . '@' . getenv('DOMAIN');
    }
    if ($raw !== false && $raw !== '') {
        Tms\Message::ingest($raw, $rcpt);
    }
} catch (Throwable $e) {
    if (class_exists('Tms\Log', false)) {
        Tms\Log::write('error', 'pipe: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    }
}
ob_end_clean();
exit(0);
