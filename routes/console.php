<?php

use App\Console\Commands\SyncNavidromeLibrary;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled work
|--------------------------------------------------------------------------
|
| The index page reads the library from Navidrome on every request and the cache
| expires on its own, so this schedule is an optimisation rather than a
| requirement: it refreshes the lists on a timer instead of leaving the first
| visitor of each period to wait for the server.
|
| DiagnoseNavidrome is deliberately left out. It exists to be run by hand.
|
*/

Schedule::command(SyncNavidromeLibrary::class)
    ->everyFiveMinutes()
    ->withoutOverlapping();
