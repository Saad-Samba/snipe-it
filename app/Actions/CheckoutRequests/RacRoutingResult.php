<?php

namespace App\Actions\CheckoutRequests;

use Illuminate\Support\Collection;

class RacRoutingResult
{
    public function __construct(
        public readonly Collection $coordinatorMatches,
        public readonly string $status,
        public readonly array $unroutedScopes = [],
        public readonly bool $shouldAlert = false,
    ) {
    }
}
