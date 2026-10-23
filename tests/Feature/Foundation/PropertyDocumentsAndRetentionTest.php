<?php

namespace Tests\Feature\Foundation;

use App\Enums\EstateRole;
use App\Models\Document;
use App\Models\GatewayWebhookEvent;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsEstates;
use Tests\TestCase;

class PropertyDocumentsAndRetentionTest extends TestCase
{
    use BuildsEstates, RefreshDatabase;

    public function test_shared_documents_reach_occupants_and_internal_ones_do_not(): void
    {
        Storage::fake('local');
        $estate = $this->estate();
        $manager = $this->staff($estate, EstateRole::EstateManager);
        [$tenant, $property] = $this->resident($estate);
        [$neighbour] = $this->resident($estate);

        $this->inEstate($manager, $estate)->post(route('manage.properties.documents.store', $property), ['document' => UploadedFile::fake()->create('lease.pdf', 120, 'application/pdf'), 'shared' => '1'])->assertRedirect();
        $this->inEstate($manager, $estate)->post(route('manage.properties.documents.store', $property), ['document' => UploadedFile::fake()->create('deed.pdf', 120, 'application/pdf')])->assertRedirect();
        $this->inEstate($tenant, $estate)->post(route('manage.properties.documents.store', $property), ['document' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertForbidden();

        $lease = Document::where('original_name', 'lease.pdf')->firstOrFail();
        $deed = Document::where('original_name', 'deed.pdf')->firstOrFail();

        $this->inEstate($tenant, $estate)->get(route('household.index'))->assertSee('lease.pdf')->assertDontSee('deed.pdf');
        $this->inEstate($tenant, $estate)->get(route('documents.show', $lease))->assertOk();
        $this->inEstate($tenant, $estate)->get(route('documents.show', $deed))->assertForbidden();
        $this->inEstate($neighbour, $estate)->get(route('documents.show', $lease))->assertForbidden();
        $this->inEstate($manager, $estate)->get(route('documents.show', $deed))->assertOk();

        $this->inEstate($manager, $estate)->delete(route('manage.documents.destroy', $deed))->assertRedirect();
        Storage::disk('local')->assertMissing($deed->path);
        $this->assertDatabaseMissing('documents', ['id' => $deed->id]);
    }

    public function test_old_operational_records_are_pruned_by_retention(): void
    {
        $estate = $this->estate();
        [, $property] = $this->resident($estate);
        $base = ['estate_id' => $estate->id, 'property_id' => $property->id, 'source' => 'walk_in', 'visitor_name' => 'X', 'category' => 'guest'];

        $old = Visit::create($base + ['status' => 'exited']);
        $old->forceFill(['created_at' => now()->subMonths(13)])->save();
        $stillInside = Visit::create($base + ['status' => 'inside']);
        $stillInside->forceFill(['created_at' => now()->subMonths(13)])->save();
        $recent = Visit::create($base + ['status' => 'exited']);
        GatewayWebhookEvent::create(['gateway' => 'paystack', 'fingerprint' => str_repeat('a', 64), 'payload' => []])->forceFill(['created_at' => now()->subYears(2)])->save();

        $this->artisan('model:prune', ['--model' => [Visit::class, GatewayWebhookEvent::class]])->assertSuccessful();

        $this->assertDatabaseMissing('visits', ['id' => $old->id]);
        $this->assertDatabaseHas('visits', ['id' => $stillInside->id]);
        $this->assertDatabaseHas('visits', ['id' => $recent->id]);
        $this->assertDatabaseCount('gateway_webhook_events', 0);
    }
}
