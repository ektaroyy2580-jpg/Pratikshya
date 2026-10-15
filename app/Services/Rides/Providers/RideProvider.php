<?php

namespace App\Services\Rides\Providers;

use App\Models\RideRequest;

/**
 * Adapter contract for ride-hailing providers. A real adapter may only claim a booking
 * when the provider's authorised API confirms it (supportsLiveBooking() === true).
 */
interface RideProvider
{
    public function key(): string;

    /**
     * Fare quotes for a trip. Each quote carries 'source' => 'estimate'|'live'.
     *
     * @param  array{road_km: float, minutes: array<string,int>}  $trip
     * @return array<int, array<string, mixed>>
     */
    public function quote(array $trip): array;

    /** Book the ride. Throws RideProviderException when the provider cannot book from this app. */
    public function book(RideRequest $ride): RideRequest;

    /** Current status payload for the ride (never invents a provider confirmation). */
    public function status(RideRequest $ride): array;

    public function supportsLiveBooking(): bool;
}
