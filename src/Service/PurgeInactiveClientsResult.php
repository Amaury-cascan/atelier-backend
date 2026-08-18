<?php

namespace App\Service;

final class PurgeInactiveClientsResult
{
    public function __construct(
        public readonly int $candidates = 0,
        public readonly int $purged = 0,
        public readonly bool $fallbackUserExists = false,
        public readonly bool $dryRun = false,
    ) {
    }
}
