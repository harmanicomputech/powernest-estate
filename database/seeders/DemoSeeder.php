<?php

namespace Database\Seeders;

use App\Enums\AnnouncementCategory;
use App\Enums\BookingStatus;
use App\Enums\EstateRole;
use App\Enums\IncidentSeverity;
use App\Enums\MaintenanceStatus;
use App\Enums\OccupancyRelationship;
use App\Enums\PowerPurchaseStatus;
use App\Enums\Priority;
use App\Enums\PropertyType;
use App\Enums\TaskStatus;
use App\Enums\VisitStatus;
use App\Models\Announcement;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\Building;
use App\Models\ChargeType;
use App\Models\EmergencyContact;
use App\Models\Estate;
use App\Models\Expense;
use App\Models\Facility;
use App\Models\Gate;
use App\Models\Invoice;
use App\Models\Meter;
use App\Models\Occupancy;
use App\Models\PowerPurchase;
use App\Models\PowerPurchaseEvent;
use App\Models\PowerToken;
use App\Models\Property;
use App\Models\StaffTask;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Visit;
use App\Services\Billing\BillingService;
use App\Services\Estate\OccupancyService;
use App\Services\Operations\IncidentService;
use App\Services\Operations\MaintenanceService;
use App\Services\Visitors\GateService;
use App\Services\Visitors\PassService;
use App\Support\Reference;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * DEMO DATA FOR LOCAL DEVELOPMENT ONLY.
 * Every account uses the password "password". The estate is flagged is_demo.
 * Tokens and payment references below are fictitious and marked as demo seed data.
 */
class DemoSeeder extends Seeder
{
    private Estate $estate;

    /** @var array<string, User> */
    private array $staff = [];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The demo seeder must never run in production.');
        }

        config(['mail.default' => 'log', 'queue.default' => 'sync']);
        mt_srand(2026);

        $this->estate = Estate::create([
            'name' => 'Greenfield Court',
            'slug' => 'greenfield-court-demo',
            'code' => 'GFC01',
            'address' => '14 Admiralty Way, Lekki Phase 1',
            'city' => 'Lagos',
            'state' => 'Lagos',
            'timezone' => 'Africa/Lagos',
            'status' => 'active',
            'contact_email' => 'office@greenfield.example',
            'contact_phone' => '0700 000 0000',
            'is_demo' => true,
            'settings' => ['power' => ['gateways' => ['payaza', 'paystack']]],
        ]);

        User::create(['name' => 'Platform Admin', 'email' => 'platform@powernest.test', 'password' => 'password'])
            ->forceFill(['is_platform_admin' => true, 'email_verified_at' => now()])->save();

        $this->createStaff();
        $properties = $this->createProperties();
        $residents = $this->createResidents($properties);

        Gate::create(['estate_id' => $this->estate->id, 'name' => 'Main Gate']);
        Gate::create(['estate_id' => $this->estate->id, 'name' => 'Service Gate']);
        foreach ([['Estate Security Control', 'Security', '0803 111 2222'], ['Facility Emergency Line', 'Facilities', '0803 333 4444'], ['Lekki Fire Service', 'Fire', '112']] as $i => [$name, $role, $phone]) {
            EmergencyContact::create(['estate_id' => $this->estate->id, 'name' => $name, 'role' => $role, 'phone' => $phone, 'sort_order' => $i]);
        }

        $this->seedVisitors($residents);
        $this->seedPower($residents);
        $this->seedBilling();
        $this->seedOperations($residents);
    }

    private function user(string $name, string $email): User
    {
        $user = User::create(['name' => $name, 'email' => $email, 'phone' => '080'.str_pad((string) mt_rand(10000000, 99999999), 8, '0'), 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function createStaff(): void
    {
        $occupancies = app(OccupancyService::class);
        $people = [
            'owner' => ['Babatunde Adewale', 'owner@powernest.test', EstateRole::EstateOwner, 'Chairman'],
            'manager' => ['Chiamaka Okafor', 'manager@powernest.test', EstateRole::EstateManager, 'Estate Manager'],
            'admin' => ['Ibrahim Sule', 'admin@powernest.test', EstateRole::EstateAdmin, 'Estate Administrator'],
            'finance' => ['Tunde Bakare', 'finance@powernest.test', EstateRole::FinanceOfficer, 'Accountant'],
            'supervisor' => ['Musa Ibrahim', 'supervisor@powernest.test', EstateRole::SecuritySupervisor, 'Chief Security Officer'],
            'guard' => ['Emeka Nwosu', 'guard@powernest.test', EstateRole::SecurityOfficer, 'Gate Officer'],
            'facility' => ['Kemi Adeyemi', 'facility@powernest.test', EstateRole::FacilityManager, 'Facility Manager'],
            'tech' => ['Samuel Udo', 'tech@powernest.test', EstateRole::Technician, 'Electrician'],
        ];

        foreach ($people as $key => [$name, $email, $role, $title]) {
            $user = $this->user($name, $email);
            $occupancies->ensureMembership($this->estate->id, $user, $role)->update(['title' => $title]);
            $user->forceFill(['current_estate_id' => $this->estate->id])->save();
            $this->staff[$key] = $user;
        }
    }

    /**
     * @return list<Property>
     */
    private function createProperties(): array
    {
        $properties = [];
        $blocks = [['Block A', 'A', 'block', 4], ['Block B', 'B', 'block', 4], ['Palm Close', 'PC', 'street', null], ['Iroko Crescent', 'IC', 'street', null]];

        foreach ($blocks as [$name, $code, $type, $floors]) {
            $building = Building::create(['estate_id' => $this->estate->id, 'name' => $name, 'code' => $code, 'type' => $type, 'floors' => $floors]);
            $count = $type === 'block' ? 8 : 7;

            for ($i = 1; $i <= $count; $i++) {
                $properties[] = Property::create([
                    'estate_id' => $this->estate->id,
                    'building_id' => $building->id,
                    'identifier' => $type === 'block' ? "{$code}-".(intdiv($i - 1, 2) + 1).str_pad((string) ((($i - 1) % 2) + 1), 2, '0', STR_PAD_LEFT) : "{$code}-".($i * 2),
                    'type' => $type === 'block' ? PropertyType::Apartment : ($i % 3 === 0 ? PropertyType::Terrace : PropertyType::Duplex),
                    'floor' => $type === 'block' ? (string) (intdiv($i - 1, 2) + 1) : null,
                    'bedrooms' => $type === 'block' ? 3 : 4,
                    'address_line' => $type === 'street' ? ($i * 2).' '.$name : null,
                ]);
            }
        }

        return $properties;
    }

    /**
     * @param  list<Property>  $properties
     * @return list<User>
     */
    private function createResidents(array $properties): array
    {
        $occupancies = app(OccupancyService::class);
        $manager = $this->staff['manager'];
        $names = ['Adaeze Obi', 'Olumide Johnson', 'Fatima Bello', 'Chinedu Eze', 'Yetunde Afolabi', 'Ifeanyi Okonkwo', 'Halima Yusuf', 'Segun Ogunleye', 'Ngozi Nnamdi', 'Kunle Adeyinka', 'Aisha Mohammed', 'Tobi Oladipo', 'Uche Ibe', 'Bisi Akande', 'Femi Coker', 'Zainab Lawal', 'Dapo Ilesanmi', 'Ebere Uzo', 'Gbenga Salami', 'Mariam Odu'];

        $residents = [];
        foreach ($names as $i => $name) {
            $email = $i === 0 ? 'resident@powernest.test' : ($i === 1 ? 'landlord@powernest.test' : strtolower(str_replace(' ', '.', $name)).'@example.com');
            $user = $this->user($name, $email);
            $property = $properties[$i];
            $relationship = match (true) {
                $i === 1 => OccupancyRelationship::Landlord,
                $i % 3 === 0 => OccupancyRelationship::Owner,
                default => OccupancyRelationship::Tenant,
            };
            $occupancies->addOccupant($property, $user, $relationship, $manager, null, now()->subMonths(mt_rand(2, 30))->toDateString());
            $user->forceFill(['current_estate_id' => $this->estate->id])->save();
            $residents[] = $user;
        }

        // The landlord's tenant, and a household member for the main demo resident.
        $tenant = $this->user('Rotimi Alabi', 'tenant@powernest.test');
        $occupancies->addOccupant($properties[1], $tenant, OccupancyRelationship::Tenant, $manager);
        $tenant->forceFill(['current_estate_id' => $this->estate->id])->save();

        $member = $this->user('Chidi Obi', 'household@powernest.test');
        $primary = Occupancy::where('user_id', $residents[0]->id)->first();
        $occupancies->addOccupant($properties[0], $member, OccupancyRelationship::HouseholdMember, $residents[0], $primary);
        $member->forceFill(['current_estate_id' => $this->estate->id])->save();

        return $residents;
    }

    /**
     * @param  list<User>  $residents
     */
    private function seedVisitors(array $residents): void
    {
        $passes = app(PassService::class);
        $gate = app(GateService::class);
        $guard = $this->staff['guard'];
        $mainGate = Gate::where('estate_id', $this->estate->id)->first();
        $tz = 'Africa/Lagos';
        $visitors = ['Kola Bankole', 'Ngozi Ezeh', 'DHL Courier', 'Jumia Delivery', 'Tayo Bello', 'Mrs. Adeleke', 'Bolt Driver', 'Plumber — Mr Sunday', 'Ada Okeke', 'Peter Okon', 'Glovo Rider', 'Dr. Ibekwe'];

        foreach ($residents as $i => $resident) {
            $property = $resident->authorizedProperties($this->estate)->first();
            $count = $i === 0 ? 4 : mt_rand(1, 2);

            for ($n = 0; $n < $count; $n++) {
                $start = now($tz)->startOfDay()->addHours(mt_rand(7, 18));
                $pass = $passes->issue($resident, $this->estate, [
                    'property_id' => $property->id,
                    'visitor_name' => $visitors[($i + $n) % count($visitors)],
                    'visitor_phone' => '081'.mt_rand(10000000, 99999999),
                    'category' => ['guest', 'delivery', 'guest', 'contractor'][$n % 4],
                    'pass_type' => 'one_time',
                    'valid_from' => $start->copy()->subHours(4)->format('Y-m-d H:i'),
                    'valid_until' => now($tz)->endOfDay()->addDays($n === 0 ? 0 : 2)->format('Y-m-d H:i'),
                ]);

                // Admit roughly half of today's visitors so the gate has history.
                if ($n % 2 === 1 || $i % 4 === 0) {
                    $this->travel($start->copy()->subHours(mt_rand(0, 3))->utc(), fn () => $gate->admit($pass, $guard, $mainGate));
                }
            }
        }

        // A few completed visits on previous days, one denial and one walk-in awaiting approval.
        Visit::where('estate_id', $this->estate->id)->where('status', VisitStatus::Inside->value)->inRandomOrder()->limit(5)->get()
            ->each(fn (Visit $v) => $v->update(['status' => VisitStatus::Exited, 'checked_out_at' => $v->checked_in_at->copy()->addHours(2), 'checked_out_by' => $guard->id]));
        $gate->deny($this->estate, $guard, 'No valid pass and host unreachable', null, ['visitor_name' => 'Unknown man (no ID)', 'category' => 'guest']);
        $gate->registerWalkIn($this->estate, $guard, ['property_id' => $residents[0]->authorizedProperties($this->estate)->first()->id, 'visitor_name' => 'Emmanuel Ade', 'visitor_phone' => '08022223333', 'category' => 'guest', 'purpose' => 'Says he is a cousin'], $mainGate);
    }

    /**
     * @param  list<User>  $residents
     */
    private function seedPower(array $residents): void
    {
        foreach (array_slice($residents, 0, 14) as $i => $resident) {
            $property = $resident->authorizedProperties($this->estate)->first();
            $meter = Meter::create([
                'estate_id' => $this->estate->id,
                'property_id' => $property->id,
                'meter_number' => '0'.(4721000000 + $i * 7919),
                'label' => $i === 0 ? 'Main house' : $property->identifier,
                'meter_type' => 'Single phase prepaid',
                'vendor_customer_name' => $resident->name,
                'validated_at' => now()->subMonths(2),
                'created_by' => $resident->id,
            ]);
            $resident->favouriteMeters()->attach($meter->id);

            $purchases = $i === 0 ? 5 : mt_rand(1, 4);
            for ($p = 0; $p < $purchases; $p++) {
                $amount = [500000, 1000000, 2000000, 1500000, 300000][($i + $p) % 5];
                $when = now()->subDays(mt_rand(0, 13))->subHours(mt_rand(0, 10));
                $status = ($i === 3 && $p === 0) ? PowerPurchaseStatus::ReconciliationRequired : PowerPurchaseStatus::TokenIssued;

                $purchase = PowerPurchase::create([
                    'reference' => Reference::make('PNE', 12),
                    'estate_id' => $this->estate->id,
                    'user_id' => $resident->id,
                    'meter_id' => $meter->id,
                    'property_id' => $property->id,
                    'meter_number' => $meter->meter_number,
                    'gateway' => $p % 2 ? 'paystack' : 'payaza',
                    'vend_amount' => $amount,
                    'service_charge' => 25000,
                    'total_amount' => $amount + 25000,
                    'amount_paid' => $amount + 25000,
                    'status' => $status,
                    'payment_confirmed_via' => 'demo_seed',
                    'paid_at' => $when,
                    'vend_attempts' => 1,
                    'last_vend_attempt_at' => $when,
                    'token_issued_at' => $status === PowerPurchaseStatus::TokenIssued ? $when->copy()->addSeconds(8) : null,
                    'failure_code' => $status === PowerPurchaseStatus::ReconciliationRequired ? 'vend_unknown' : null,
                    'failure_reason' => $status === PowerPurchaseStatus::ReconciliationRequired ? 'The vending service did not confirm the result. Check the Stron portal before retrying.' : null,
                    'created_at' => $when,
                    'updated_at' => $when,
                ]);

                PowerPurchaseEvent::create(['power_purchase_id' => $purchase->id, 'from_status' => null, 'to_status' => PowerPurchaseStatus::PendingPayment, 'note' => 'Demo seed', 'created_at' => $when]);
                PowerPurchaseEvent::create(['power_purchase_id' => $purchase->id, 'from_status' => PowerPurchaseStatus::Vending, 'to_status' => $status, 'note' => 'Demo seed', 'created_at' => $when]);

                if ($status === PowerPurchaseStatus::TokenIssued) {
                    PowerToken::create([
                        'power_purchase_id' => $purchase->id,
                        'token' => implode('', array_map(fn () => mt_rand(0, 9), range(1, 20))),
                        'units' => number_format($amount / 100 / 225, 2, '.', ''),
                        'meter_number' => $meter->meter_number,
                        'source' => 'demo_seed',
                        'meta' => ['note' => 'Fictitious demo token'],
                        'issued_at' => $purchase->token_issued_at,
                    ]);
                }
            }
        }
    }

    private function seedBilling(): void
    {
        $billing = app(BillingService::class);
        $finance = $this->staff['finance'];
        $tz = 'Africa/Lagos';

        $serviceCharge = ChargeType::create(['estate_id' => $this->estate->id, 'name' => 'Service charge', 'code' => 'SVC', 'amount' => 4500000, 'frequency' => 'monthly']);
        ChargeType::create(['estate_id' => $this->estate->id, 'name' => 'Waste management', 'code' => 'WASTE', 'amount' => 500000, 'frequency' => 'monthly']);

        foreach ([2, 1, 0] as $monthsAgo) {
            $start = now($tz)->startOfMonth()->subMonths($monthsAgo);
            $this->travel($start->copy()->addDay()->setTime(9, 0)->utc(), fn () => $billing->runCharge($serviceCharge, $start, $start->copy()->endOfMonth(), $start->copy()->addDays(14), $finance));
        }

        // Most residents paid older invoices; some partial; current month mostly open.
        $invoices = Invoice::where('estate_id', $this->estate->id)->orderBy('issue_date')->get();
        foreach ($invoices as $i => $invoice) {
            $isCurrent = $invoice->issue_date->gte(now($tz)->startOfMonth());
            $roll = mt_rand(1, 10);
            if ((! $isCurrent && $roll <= 8) || ($isCurrent && $roll <= 3)) {
                $paidAt = $invoice->issue_date->copy()->addDays(mt_rand(1, 12))->setTime(11, 0);
                $paidAt = $paidAt->isFuture() ? now()->subHour() : $paidAt;
                $amount = $roll === 2 ? intdiv($invoice->total, 2) : $invoice->total;
                $this->travel($paidAt, fn () => $billing->recordManualPayment($invoice->fresh(), $amount, 'bank_transfer', 'DEMO-TRF-'.str_pad((string) $i, 5, '0', STR_PAD_LEFT), $paidAt, $finance));
            }
        }

        $diesel = Vendor::create(['estate_id' => $this->estate->id, 'name' => 'Ikeja Diesel Supplies', 'service_category' => 'utilities', 'phone' => '08055556666']);
        $guards = Vendor::create(['estate_id' => $this->estate->id, 'name' => 'Shield Guard Services Ltd', 'service_category' => 'security']);
        $clean = Vendor::create(['estate_id' => $this->estate->id, 'name' => 'Sparkle Cleaning Co.', 'service_category' => 'cleaning']);

        foreach ([[$diesel, 'utilities', 'Diesel for estate generator (2,000L)', 220000000], [$guards, 'security', 'Monthly guard services', 150000000], [$clean, 'cleaning', 'Common area cleaning', 35000000]] as $j => [$vendor, $category, $description, $amount]) {
            foreach ([1, 0] as $monthsAgo) {
                Expense::create([
                    'estate_id' => $this->estate->id, 'vendor_id' => $vendor->id, 'category' => $category, 'description' => $description,
                    'amount' => $amount, 'incurred_on' => now($tz)->startOfMonth()->subMonths($monthsAgo)->addDays(3 + $j)->toDateString(),
                    'status' => $monthsAgo ? 'paid' : ($j === 0 ? 'pending_approval' : 'approved'),
                    'submitted_by' => $this->staff['facility']->id, 'reviewed_by' => $monthsAgo || $j ? $finance->id : null, 'reviewed_at' => $monthsAgo || $j ? now() : null,
                    'paid_at' => $monthsAgo ? now()->subMonth() : null, 'payment_reference' => $monthsAgo ? 'DEMO-EXP-'.$j : null,
                ]);
            }
        }
    }

    /**
     * @param  list<User>  $residents
     */
    private function seedOperations(array $residents): void
    {
        $maintenance = app(MaintenanceService::class);
        $incidents = app(IncidentService::class);
        $facility = $this->staff['facility'];
        $tech = $this->staff['tech'];

        $requests = [
            [0, 'plumbing', 'Kitchen sink leaking under cabinet', 'Water has been dripping steadily since yesterday evening and the cabinet base is soaked.', Priority::High, MaintenanceStatus::InProgress],
            [2, 'electrical', 'Corridor light flickering on 2nd floor', 'The landing light outside my flat flickers constantly at night.', Priority::Normal, MaintenanceStatus::Assigned],
            [4, 'drainage', 'Blocked drain in front of house', 'The gutter in front of the house is blocked and water collects after rain.', Priority::Normal, MaintenanceStatus::Submitted],
            [5, 'hvac', 'AC not cooling in master bedroom', 'Split unit runs but blows warm air.', Priority::Low, MaintenanceStatus::Completed],
            [7, 'electrical', 'No power on one phase', 'Half the sockets in the house have no power since this morning.', Priority::Urgent, MaintenanceStatus::Submitted],
            [0, 'carpentry', 'Wardrobe door hinge broken', 'The left door of the wardrobe has come off its hinge.', Priority::Low, MaintenanceStatus::Closed],
        ];

        foreach ($requests as [$r, $category, $title, $description, $priority, $status]) {
            $resident = $residents[$r];
            $request = $maintenance->submit($resident, $this->estate, ['property_id' => $resident->authorizedProperties($this->estate)->first()->id, 'category' => $category, 'title' => $title, 'description' => $description, 'priority' => $priority->value]);

            if ($status !== MaintenanceStatus::Submitted) {
                $maintenance->assign($request, $facility, $tech, null, $priority, now()->addDays(2), null);
            }
            if (in_array($status, [MaintenanceStatus::InProgress, MaintenanceStatus::Completed, MaintenanceStatus::Closed], true)) {
                $maintenance->transition($request->fresh(), MaintenanceStatus::InProgress, $tech, 'On site now.');
            }
            if (in_array($status, [MaintenanceStatus::Completed, MaintenanceStatus::Closed], true)) {
                $maintenance->transition($request->fresh(), MaintenanceStatus::Completed, $tech, 'Work done. Please confirm.');
            }
            if ($status === MaintenanceStatus::Closed) {
                $maintenance->residentResponse($request->fresh(), $resident, true, null);
            }
        }

        $incidents->report($this->staff['guard'], $this->estate, ['category' => 'suspicious', 'severity' => IncidentSeverity::Medium->value, 'title' => 'Unregistered vehicle loitering near Palm Close', 'description' => 'Grey Corolla parked for over 40 minutes with driver inside; no host could be identified.', 'location' => 'Palm Close junction', 'occurred_at' => now()->subHours(5)]);
        $incidents->report($residents[3], $this->estate, ['category' => 'noise', 'severity' => IncidentSeverity::Low->value, 'title' => 'Loud generator after 11pm', 'description' => 'Neighbouring generator running past midnight for three nights.', 'location' => 'Iroko Crescent', 'occurred_at' => now()->subDays(2)]);

        foreach ([
            ['Scheduled power maintenance — Saturday', "Our electricity provider will carry out maintenance on the estate transformer this Saturday from 9am to 2pm. The estate generator will run during this period.\n\nPlease buy electricity tokens in advance if your meter is low.", AnnouncementCategory::UtilityInterruption, true],
            ['Quarterly residents’ meeting', 'The quarterly residents’ meeting holds at the clubhouse on the last Sunday of the month at 4pm. Agenda: security upgrades, service charge review and the new visitor pass system.', AnnouncementCategory::Event, false],
            ['New digital visitor passes', 'From this month all visitors must present a PowerNest Estate pass (QR or 6-digit code) at the gate. Create passes from the Visitors tab in the app.', AnnouncementCategory::Security, false],
        ] as $n => [$title, $body, $category, $pinned]) {
            Announcement::create(['estate_id' => $this->estate->id, 'author_id' => $this->staff['manager']->id, 'title' => $title, 'body' => $body, 'category' => $category, 'audience' => 'estate', 'is_pinned' => $pinned, 'published_at' => now()->subDays($n * 3 + 1)]);
        }

        $clubhouse = Facility::create(['estate_id' => $this->estate->id, 'name' => 'Clubhouse hall', 'description' => 'Air-conditioned hall for private events, seats up to 80.', 'capacity' => 80, 'requires_approval' => true, 'fee' => 2500000, 'opens_at' => '08:00', 'closes_at' => '22:00', 'max_duration_minutes' => 360, 'rules' => 'No loud music after 10pm. Clean up after use.']);
        Facility::create(['estate_id' => $this->estate->id, 'name' => 'Tennis court', 'description' => 'Floodlit hard court.', 'capacity' => 4, 'requires_approval' => false, 'opens_at' => '06:00', 'closes_at' => '21:00', 'max_duration_minutes' => 90]);
        Facility::create(['estate_id' => $this->estate->id, 'name' => 'Swimming pool', 'description' => 'Residents and accompanied guests only.', 'capacity' => 20, 'requires_approval' => false, 'opens_at' => '07:00', 'closes_at' => '19:00', 'max_duration_minutes' => 120]);

        $resident = $residents[0];
        Booking::create(['estate_id' => $this->estate->id, 'facility_id' => $clubhouse->id, 'user_id' => $resident->id, 'property_id' => $resident->authorizedProperties($this->estate)->first()->id, 'starts_at' => now('Africa/Lagos')->addDays(6)->setTime(14, 0)->utc(), 'ends_at' => now('Africa/Lagos')->addDays(6)->setTime(18, 0)->utc(), 'guests' => 40, 'purpose' => "Daughter's birthday party", 'status' => BookingStatus::Pending, 'fee' => 2500000]);

        foreach ([['Estate generator (500kVA)', 'generator', 'Power house', 30, 26], ['Borehole pump', 'borehole', 'Water works', 90, 80], ['Main gate barrier', 'gate', 'Main Gate', 60, 10], ['Transformer T1', 'transformer', 'Substation', 180, 175]] as [$name, $category, $location, $interval, $ago]) {
            Asset::create(['estate_id' => $this->estate->id, 'name' => $name, 'category' => $category, 'location' => $location, 'service_interval_days' => $interval, 'last_serviced_on' => now()->subDays($ago)->toDateString(), 'next_service_due_on' => now()->subDays($ago)->addDays($interval)->toDateString()]);
        }

        StaffTask::create(['estate_id' => $this->estate->id, 'title' => 'Inspect perimeter fence lighting', 'assigned_to' => $tech->id, 'created_by' => $facility->id, 'priority' => Priority::Normal, 'status' => TaskStatus::Open, 'due_at' => now()->addDay()]);
        StaffTask::create(['estate_id' => $this->estate->id, 'title' => 'Night patrol log review', 'assigned_to' => $this->staff['guard']->id, 'created_by' => $this->staff['supervisor']->id, 'priority' => Priority::High, 'status' => TaskStatus::InProgress, 'due_at' => now()->addHours(6)]);
    }

    private function travel(Carbon $to, callable $callback): mixed
    {
        Carbon::setTestNow($to->isFuture() ? now() : $to);

        try {
            return $callback();
        } finally {
            Carbon::setTestNow();
        }
    }
}
