<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EstateSwitchController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\GateController;
use App\Http\Controllers\HouseholdController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\Manage;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\Platform;
use App\Http\Controllers\Power;
use App\Http\Controllers\PublicPassController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\VisitorController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : view('welcome'))->name('home');

// Shareable visitor pass (the link itself is the credential; no host data beyond what the visitor needs).
Route::get('/p/{token}', [PublicPassController::class, 'show'])->middleware('throttle:public-pass')->name('passes.public');

// Payment provider webhooks: authenticated by signature where supported, always re-verified server-side.
Route::middleware('throttle:webhooks')->group(function () {
    Route::post('/webhooks/paystack', [WebhookController::class, 'paystack'])->name('webhooks.paystack');
    Route::post('/webhooks/payaza/{secret?}', [WebhookController::class, 'payaza'])->name('webhooks.payaza');
});

Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');

Route::middleware(['auth', 'verified', 'estate'])->group(function () {
    Route::post('/invitations/{token}', [InvitationController::class, 'accept'])->name('invitations.accept');
    Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding');
    Route::post('/onboarding', [OnboardingController::class, 'requestAccess'])->name('onboarding.request');
    Route::post('/estates/{estate}/switch', EstateSwitchController::class)->name('estates.switch');

    Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('/account/notifications', [AccountController::class, 'updateNotifications'])->name('account.notifications');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');

    Route::prefix('platform')->name('platform.')->middleware('platform')->group(function () {
        Route::get('/', [Platform\PlatformController::class, 'dashboard'])->name('dashboard');
        Route::get('/estates/create', [Platform\EstateController::class, 'create'])->name('estates.create');
        Route::post('/estates', [Platform\EstateController::class, 'store'])->name('estates.store');
        Route::get('/estates/{estate}', [Platform\EstateController::class, 'show'])->name('estates.show');
        Route::put('/estates/{estate}/status', [Platform\EstateController::class, 'status'])->name('estates.status');
        Route::post('/estates/{estate}/invite', [Platform\EstateController::class, 'invite'])->name('estates.invite');
    });
});

Route::prefix('app')->middleware(['auth', 'verified', 'estate:required'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    // Household
    Route::get('/home', [HouseholdController::class, 'index'])->name('household.index');
    Route::post('/home/members', [HouseholdController::class, 'invite'])->name('household.invite');
    Route::delete('/home/members/{occupancy}', [HouseholdController::class, 'remove'])->name('household.remove');
    Route::post('/home/move-requests', [HouseholdController::class, 'moveRequest'])->name('household.move');

    // Visitors (residents)
    Route::middleware('can:visitors.invite')->group(function () {
        Route::get('/visitors', [VisitorController::class, 'index'])->name('visitors.index');
        Route::get('/visitors/new', [VisitorController::class, 'create'])->name('visitors.create');
        Route::post('/visitors', [VisitorController::class, 'store'])->name('visitors.store');
        Route::get('/visitors/walk-ins/{visit}', [VisitorController::class, 'walkIn'])->name('visitors.walk-ins.show');
        Route::post('/visitors/walk-ins/{visit}', [VisitorController::class, 'respond'])->name('visitors.walk-ins.respond');
    });
    Route::get('/visitors/{pass}', [VisitorController::class, 'show'])->name('visitors.show');
    Route::get('/visitors/{pass}/qr.svg', [VisitorController::class, 'qr'])->name('visitors.qr');
    Route::post('/visitors/{pass}/revoke', [VisitorController::class, 'revoke'])->name('visitors.revoke');

    // Gate console (security)
    Route::middleware('can:gate.operate')->prefix('gate')->name('gate.')->group(function () {
        Route::get('/', [GateController::class, 'index'])->name('index');
        Route::get('/expected', [GateController::class, 'expected'])->name('expected');
        Route::get('/inside', [GateController::class, 'inside'])->name('inside');
        Route::get('/walk-in', [GateController::class, 'createWalkIn'])->name('walk-in');
        Route::post('/walk-in', [GateController::class, 'storeWalkIn'])->middleware('throttle:gate-verify')->name('walk-in.store');
        Route::post('/visits/{visit}/check-out', [GateController::class, 'checkOut'])->name('check-out');
        Route::post('/visits/{visit}/override', [GateController::class, 'override'])->name('override');
        Route::post('/shift', [GateController::class, 'shift'])->name('shift');
    });

    // Electricity
    Route::middleware('can:power.purchase')->prefix('power')->name('power.')->group(function () {
        Route::get('/', [Power\PowerController::class, 'index'])->name('index');
        Route::post('/meters', [Power\MeterController::class, 'store'])->middleware('throttle:meter-lookup')->name('meters.store');
        Route::post('/meters/{meter}/favourite', [Power\MeterController::class, 'favourite'])->name('meters.favourite');
        Route::get('/buy', [Power\PurchaseController::class, 'create'])->name('buy');
        Route::post('/purchases', [Power\PurchaseController::class, 'store'])->middleware('throttle:power-purchase')->name('purchases.store');
        Route::get('/history', [Power\PurchaseController::class, 'index'])->name('history');
    });
    Route::prefix('power/purchases/{purchase}')->name('power.purchases.')->group(function () {
        Route::get('/', [Power\PurchaseController::class, 'show'])->name('show');
        Route::get('/pay', [Power\PurchaseController::class, 'pay'])->name('pay');
        Route::get('/return', [Power\PurchaseController::class, 'return'])->name('return');
        Route::post('/verify', [Power\PurchaseController::class, 'verify'])->middleware('throttle:power-purchase')->name('verify');
        Route::post('/resend', [Power\PurchaseController::class, 'resend'])->middleware('throttle:power-purchase')->name('resend');
        Route::post('/support', [Power\PurchaseController::class, 'support'])->name('support');
    });

    // Bills
    Route::middleware('can:billing.view_own')->group(function () {
        Route::get('/bills', [BillController::class, 'index'])->name('bills.index');
    });
    Route::get('/bills/{invoice}', [BillController::class, 'show'])->name('bills.show');
    Route::post('/bills/{invoice}/pay', [BillController::class, 'pay'])->middleware('throttle:power-purchase')->name('bills.pay');
    Route::get('/payments/{payment}/return', [BillController::class, 'paymentReturn'])->name('bills.payments.return');
    Route::post('/payments/{payment}/verify', [BillController::class, 'verifyPayment'])->middleware('throttle:power-purchase')->name('bills.payments.verify');
    Route::get('/payments/{payment}/receipt', [BillController::class, 'receipt'])->name('bills.receipt');

    // Maintenance
    Route::get('/maintenance', [MaintenanceController::class, 'index'])->name('maintenance.index');
    Route::get('/maintenance/new', [MaintenanceController::class, 'create'])->name('maintenance.create');
    Route::post('/maintenance', [MaintenanceController::class, 'store'])->name('maintenance.store');
    Route::get('/maintenance/{maintenance}', [MaintenanceController::class, 'show'])->name('maintenance.show');
    Route::post('/maintenance/{maintenance}/comment', [MaintenanceController::class, 'comment'])->name('maintenance.comment');
    Route::post('/maintenance/{maintenance}/assign', [MaintenanceController::class, 'assign'])->name('maintenance.assign');
    Route::post('/maintenance/{maintenance}/status', [MaintenanceController::class, 'status'])->name('maintenance.status');
    Route::post('/maintenance/{maintenance}/costs', [MaintenanceController::class, 'costs'])->name('maintenance.costs');
    Route::post('/maintenance/{maintenance}/confirm', [MaintenanceController::class, 'confirm'])->name('maintenance.confirm');

    // Facilities
    Route::middleware('can:facilities.book')->group(function () {
        Route::get('/facilities', [FacilityController::class, 'index'])->name('facilities.index');
        Route::get('/facilities/bookings', [FacilityController::class, 'bookings'])->name('facilities.bookings.index');
        Route::get('/facilities/{facility}', [FacilityController::class, 'show'])->name('facilities.show');
        Route::post('/facilities/{facility}/book', [FacilityController::class, 'book'])->name('facilities.book');
    });
    Route::post('/bookings/{booking}/cancel', [FacilityController::class, 'cancel'])->name('bookings.cancel');

    // Announcements
    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::get('/announcements/new', [AnnouncementController::class, 'create'])->middleware('can:announcements.publish')->name('announcements.create');
    Route::post('/announcements', [AnnouncementController::class, 'store'])->middleware('can:announcements.publish')->name('announcements.store');
    Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show'])->name('announcements.show');

    // Incidents
    Route::get('/incidents', [IncidentController::class, 'index'])->name('incidents.index');
    Route::get('/incidents/new', [IncidentController::class, 'create'])->middleware('can:incidents.report')->name('incidents.create');
    Route::post('/incidents', [IncidentController::class, 'store'])->middleware('can:incidents.report')->name('incidents.store');
    Route::get('/incidents/{incident}', [IncidentController::class, 'show'])->name('incidents.show');
    Route::post('/incidents/{incident}/assign', [IncidentController::class, 'assign'])->name('incidents.assign');
    Route::post('/incidents/{incident}/status', [IncidentController::class, 'status'])->name('incidents.status');
    Route::post('/incidents/{incident}/note', [IncidentController::class, 'note'])->name('incidents.note');

    // Staff tasks
    Route::middleware('can:tasks.work')->group(function () {
        Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
        Route::post('/tasks', [TaskController::class, 'store'])->middleware('can:staff.manage')->name('tasks.store');
        Route::post('/tasks/{task}/status', [TaskController::class, 'status'])->name('tasks.status');
    });

    // Estate management
    Route::prefix('manage')->name('manage.')->group(function () {
        Route::middleware('can:properties.view')->group(function () {
            Route::get('/properties', [Manage\PropertyController::class, 'index'])->name('properties.index');
            Route::get('/properties/new', [Manage\PropertyController::class, 'create'])->middleware('can:properties.manage')->name('properties.create');
            Route::post('/properties', [Manage\PropertyController::class, 'store'])->middleware('can:properties.manage')->name('properties.store');
            Route::post('/buildings', [Manage\PropertyController::class, 'storeBuilding'])->middleware('can:properties.manage')->name('buildings.store');
            Route::get('/properties/{property}', [Manage\PropertyController::class, 'show'])->name('properties.show');
            Route::put('/properties/{property}', [Manage\PropertyController::class, 'update'])->middleware('can:properties.manage')->name('properties.update');
            Route::post('/properties/{property}/occupants', [Manage\PropertyController::class, 'addOccupant'])->middleware('can:residents.manage')->name('properties.occupants.store');
            Route::delete('/occupancies/{occupancy}', [Manage\PropertyController::class, 'endOccupancy'])->middleware('can:residents.manage')->name('occupancies.end');
            Route::post('/move-requests/{moveRequest}', [Manage\PropertyController::class, 'reviewMove'])->middleware('can:residents.manage')->name('move-requests.review');
        });

        Route::get('/residents', [Manage\ResidentController::class, 'index'])->middleware('can:residents.view')->name('residents.index');

        Route::middleware('can:members.manage')->group(function () {
            Route::get('/members', [Manage\MemberController::class, 'index'])->name('members.index');
            Route::post('/members/invite', [Manage\MemberController::class, 'invite'])->name('members.invite');
            Route::post('/members/{membership}/status', [Manage\MemberController::class, 'status'])->name('members.status');
            Route::post('/invitations/{invitation}/revoke', [Manage\MemberController::class, 'revokeInvitation'])->name('invitations.revoke');
            Route::post('/requests/{membershipRequest}', [Manage\MemberController::class, 'review'])->name('requests.review');
        });

        Route::get('/visits', [Manage\VisitLogController::class, 'index'])->middleware('can:visitors.view_all')->name('visits.index');

        Route::middleware('can:power.view_all')->group(function () {
            Route::get('/power', [Manage\PowerOperationsController::class, 'index'])->name('power.index');
            Route::get('/power/{purchase}', [Manage\PowerOperationsController::class, 'show'])->name('power.show');
            Route::post('/power/{purchase}/reverify', [Manage\PowerOperationsController::class, 'reverify'])->name('power.reverify');
            Route::post('/power/{purchase}/retry', [Manage\PowerOperationsController::class, 'retry'])->name('power.retry');
            Route::post('/power/{purchase}/token', [Manage\PowerOperationsController::class, 'recordToken'])->name('power.token');
            Route::post('/power/{purchase}/confirm-payment', [Manage\PowerOperationsController::class, 'confirmPayment'])->name('power.confirm-payment');
            Route::post('/power/{purchase}/refund', [Manage\PowerOperationsController::class, 'refund'])->name('power.refund');
        });

        Route::middleware('can:billing.manage')->prefix('billing')->name('billing.')->group(function () {
            Route::get('/', [Manage\BillingController::class, 'index'])->name('index');
            Route::get('/invoices', [Manage\BillingController::class, 'invoices'])->name('invoices');
            Route::get('/invoices/new', [Manage\BillingController::class, 'create'])->name('invoices.create');
            Route::post('/invoices', [Manage\BillingController::class, 'store'])->name('invoices.store');
            Route::post('/invoices/{invoice}/payment', [Manage\BillingController::class, 'recordPayment'])->name('invoices.payment');
            Route::post('/invoices/{invoice}/adjustment', [Manage\BillingController::class, 'requestAdjustment'])->name('invoices.adjustment');
            Route::post('/invoices/{invoice}/void', [Manage\BillingController::class, 'void'])->name('invoices.void');
            Route::post('/invoices/{invoice}/remind', [Manage\BillingController::class, 'remind'])->name('invoices.remind');
            Route::post('/adjustments/{adjustment}', [Manage\BillingController::class, 'reviewAdjustment'])->middleware('can:billing.approve')->name('adjustments.review');
            Route::post('/charge-types', [Manage\BillingController::class, 'storeChargeType'])->name('charge-types.store');
            Route::post('/charge-types/{chargeType}/run', [Manage\BillingController::class, 'runCharge'])->name('charge-types.run');
        });

        Route::middleware('can:expenses.manage')->group(function () {
            Route::get('/expenses', [Manage\ExpenseController::class, 'index'])->name('expenses.index');
            Route::post('/expenses', [Manage\ExpenseController::class, 'store'])->name('expenses.store');
            Route::post('/expenses/{expense}/review', [Manage\ExpenseController::class, 'review'])->middleware('can:expenses.approve')->name('expenses.review');
            Route::post('/expenses/{expense}/paid', [Manage\ExpenseController::class, 'markPaid'])->middleware('can:expenses.approve')->name('expenses.paid');
            Route::post('/vendors', [Manage\ExpenseController::class, 'storeVendor'])->name('vendors.store');
        });

        Route::middleware('can:facilities.manage')->group(function () {
            Route::get('/bookings', [Manage\BookingController::class, 'index'])->name('bookings.index');
            Route::post('/bookings/{booking}/review', [Manage\BookingController::class, 'review'])->name('bookings.review');
            Route::post('/facilities', [Manage\BookingController::class, 'storeFacility'])->name('facilities.store');
        });

        Route::middleware('can:maintenance.manage')->group(function () {
            Route::get('/assets', [Manage\AssetController::class, 'index'])->name('assets.index');
            Route::post('/assets', [Manage\AssetController::class, 'store'])->name('assets.store');
            Route::post('/assets/{asset}/service', [Manage\AssetController::class, 'schedule'])->name('assets.service');
        });

        Route::middleware('can:support.manage')->group(function () {
            Route::get('/support', [Manage\SupportController::class, 'index'])->name('support.index');
            Route::post('/support/{ticket}/reply', [Manage\SupportController::class, 'reply'])->name('support.reply');
        });

        Route::get('/reports', [Manage\ReportController::class, 'index'])->middleware('can:reports.view')->name('reports.index');
        Route::get('/audit', [Manage\AuditController::class, 'index'])->middleware('can:audit.view')->name('audit.index');

        Route::middleware('can:estate.settings')->group(function () {
            Route::get('/settings', [Manage\SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('/settings', [Manage\SettingsController::class, 'update'])->name('settings.update');
            Route::post('/settings/gates', [Manage\SettingsController::class, 'storeGate'])->name('settings.gates.store');
            Route::post('/settings/contacts', [Manage\SettingsController::class, 'storeContact'])->name('settings.contacts.store');
        });
    });
});
