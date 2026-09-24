<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class SourceRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function active(): array
    {
        $statement = $this->db->query("SELECT * FROM sources WHERE active = 1 AND trust_status = 'verified' ORDER BY organization");
        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $statement = $this->db->query('SELECT * FROM sources ORDER BY organization');
        return $statement->fetchAll();
    }
}
