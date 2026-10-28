<?php
// Run every 10 minutes from cPanel Cron Jobs: php /home/USER/temp-mail-site/bin/cron.php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$done = Tms\Cleanup::run();
Tms\Db::setSetting('cron_last_run', (string) time());
if (array_sum($done) > 0) {
    Tms\Log::write('cron', json_encode($done));
}
