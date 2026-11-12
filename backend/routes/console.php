<?php

use Illuminate\Support\Facades\Schedule;

// Zamanlanmış görevler. Hosting (cPanel) cron'u en sık 5 dakikada bir çalıştırabiliyor
// ("Once Per Five Minutes"), komut:
//   cd $HOME/kumbare-backend && /opt/alt/php82/usr/bin/php artisan schedule:run >> /dev/null 2>&1
// Bu yüzden tüm görev saatleri 5'in katı olan dakikalara denk gelmelidir.
// Aylık müşteri sayacı için zamanlanmış görev gerekmez (dönem tarihi ile çalışır).

Schedule::command('queue:work --stop-when-empty --max-time=50')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('kumbare:process-campaigns')->everyFifteenMinutes();
Schedule::command('kumbare:process-subscriptions')->dailyAt('00:05');
Schedule::command('kumbare:process-sponsorships')->dailyAt('00:10');
Schedule::command('kumbare:update-popularity')->dailyAt('03:00');
