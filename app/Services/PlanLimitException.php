<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class PlanLimitException extends RuntimeException
{
    /** @param array<string, int|string> $usage */
    public function __construct(private array $usage)
    {
        parent::__construct('De maandelijkse Business-limiet is bereikt. Neem contact op met ScamSpotter voor een groter plan.');
    }

    /** @return array<string, int|string> */
    public function usage(): array
    {
        return $this->usage;
    }
}
