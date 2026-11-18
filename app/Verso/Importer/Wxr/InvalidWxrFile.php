<?php

namespace App\Verso\Importer\Wxr;

use RuntimeException;

// Thrown when a file is missing, broken or not a WordPress export.
class InvalidWxrFile extends RuntimeException {}
