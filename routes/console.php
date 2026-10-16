<?php

use Illuminate\Support\Facades\Schedule;

/*
| Tasques programades. Cal una entrada de cron que executi `php artisan schedule:run` cada minut
| (vegeu deploy/cron/gestpartits).
*/

// Imatges de versions antigues de la programació.
Schedule::command('app:images:prune')->dailyAt('03:30')->withoutOverlapping();

// Enllaços de restabliment de contrasenya caducats.
Schedule::command('auth:clear-resets')->dailyAt('03:40');

// Treballs fallits de més de 30 dies.
Schedule::command('queue:prune-failed --hours=720')->weekly();
