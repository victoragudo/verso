<?php

namespace Tests\Support;

use App\Verso\Install\Database\PdoConnection;
use App\Verso\Install\Database\PdoConnector;
use Closure;
use PDO;
use PDOException;

// A PdoConnector for tests: each DSN answers with a PDO or throws a crafted error.
class FakePdoConnector extends PdoConnector
{
    /** @var list<PdoConnection> */
    public array $calls = [];

    /**
     * @param  Closure(PdoConnection): (PDO|PDOException)  $answer
     */
    public function __construct(protected Closure $answer) {}

    public function connect(PdoConnection $connection): PDO
    {
        $this->calls[] = $connection;
        $result = ($this->answer)($connection);

        if ($result instanceof PDOException) {
            throw $result;
        }

        return $result;
    }

    // A PDO whose queries answer from a map of "sql" => rows (first column only).
    public static function server(string $version, array $tables = [], array $extra = []): PDO
    {
        return new class($version, $tables, $extra) extends PDO
        {
            public function __construct(protected string $version, protected array $tables, protected array $extra)
            {
                parent::__construct('sqlite::memory:');
            }

            public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
            {
                $rows = match (true) {
                    str_contains($query, 'version') => [$this->version],
                    str_contains($query, 'show tables'), str_contains($query, 'pg_tables'), str_contains($query, 'sqlite_master') => $this->tables,
                    default => $this->extra[$query] ?? [],
                };

                $pdo = new PDO('sqlite::memory:');
                $pdo->exec('create table answer (value text)');
                $insert = $pdo->prepare('insert into answer values (?)');

                foreach ($rows as $row) {
                    $insert->execute([$row]);
                }

                return $pdo->query('select value from answer');
            }
        };
    }

    public static function error(string $message): PDOException
    {
        return new PDOException($message);
    }
}
