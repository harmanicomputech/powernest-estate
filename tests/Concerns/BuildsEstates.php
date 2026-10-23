<?php

namespace Tests\Concerns;

use App\Enums\EstateRole;
use App\Enums\OccupancyRelationship;
use App\Models\Estate;
use App\Models\Meter;
use App\Models\Property;
use App\Models\User;
use App\Services\Estate\OccupancyService;

trait BuildsEstates
{
    protected function estate(array $settings = []): Estate
    {
        return Estate::factory()->create(['settings' => $settings]);
    }

    protected function staff(Estate $estate, EstateRole $role): User
    {
        $user = User::factory()->create();
        app(OccupancyService::class)->ensureMembership($estate->id, $user, $role);

        return $user->fresh();
    }

    /**
     * @return array{0: User, 1: Property}
     */
    protected function resident(Estate $estate, OccupancyRelationship $relationship = OccupancyRelationship::Tenant): array
    {
        $user = User::factory()->create();
        $property = Property::factory()->create(['estate_id' => $estate->id]);
        app(OccupancyService::class)->addOccupant($property, $user, $relationship);

        return [$user->fresh(), $property];
    }

    protected function meterFor(Property $property): Meter
    {
        return Meter::factory()->create(['property_id' => $property->id, 'estate_id' => $property->estate_id]);
    }

    protected function enableIntegrations(): void
    {
        config([
            'powernest.integrations_live' => true,
            'services.paystack.enabled' => true,
            'services.paystack.secret_key' => 'sk_test_fake_secret',
            'services.payaza.enabled' => true,
            'services.payaza.public_key' => 'PZ78-PKTEST-FAKE',
            'services.payaza.environment' => 'test',
            'services.payaza.verify_url' => 'https://payaza.test/status?ref={reference}',
            'services.payaza.webhook_secret' => 'hook-secret',
            'services.stron.base_url' => 'https://stron.test/api',
            'services.stron.company' => 'TestCo',
            'services.stron.username' => 'tester',
            'services.stron.password' => 'secret-pass',
            'queue.default' => 'sync',
        ]);
    }

    /**
     * Act as the user inside the estate (the session carries the verified estate context).
     */
    protected function inEstate(User $user, Estate $estate): static
    {
        return $this->actingAs($user)->withSession(['estate_id' => $estate->id]);
    }
}
