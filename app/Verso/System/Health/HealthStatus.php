<?php

namespace App\Verso\System\Health;

// How serious a server check result is.
enum HealthStatus: string
{
    case Ok = 'ok';
    case Info = 'info';
    case Warning = 'warning';
    case Danger = 'danger';
}
