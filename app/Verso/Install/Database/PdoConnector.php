<?php

namespace App\Verso\Install\Database;

use PDO;

// Opens raw PDO connections for the tester and the creator. Tests replace it with a fake.
class PdoConnector
{
    public function connect(PdoConnection $connection): PDO
    {
        return new PDO($connection->dsn, $connection->username ?: null, $connection->password ?: null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => $connection->timeout,
        ]);
    }
}
