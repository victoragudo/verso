<?php

namespace App\Verso\Install\Console;

use RuntimeException;

// The command cannot reach the database and cannot ask for other settings.
class DatabaseNotReady extends RuntimeException {}
