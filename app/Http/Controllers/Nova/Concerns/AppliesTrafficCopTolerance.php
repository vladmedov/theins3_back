<?php

namespace App\Http\Controllers\Nova\Concerns;

use Illuminate\Support\Carbon;

trait AppliesTrafficCopTolerance
{
    /**
     * Nova compares server updated_at to client _retrieved_at.
     * Positive seconds: add slack (fewer false 409s). Negative: shift threshold back (easier real 409 / testing).
     */
    protected function trafficCopThresholdFromRetrievedAt(Carbon $retrievedAt): Carbon
    {
        $seconds = (int) config('nova.traffic_cop_tolerance_seconds', 0);

        if ($seconds === 0) {
            return $retrievedAt;
        }

        return $retrievedAt->copy()->addSeconds($seconds);
    }
}
