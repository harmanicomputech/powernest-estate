<?php

namespace App\Livewire;

use App\Enums\PowerPurchaseStatus;
use App\Models\PowerPurchase;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Live view of a purchase's server-side state. Polls the database only while the purchase is
 * moving on its own (paid / vending); it never decides status itself.
 */
class PurchaseStatus extends Component
{
    #[Locked]
    public int $purchaseId;

    public function mount(PowerPurchase $purchase): void
    {
        Gate::authorize('view', $purchase);
        $this->purchaseId = $purchase->id;
    }

    public function render(): View
    {
        $purchase = PowerPurchase::with('token')->findOrFail($this->purchaseId);
        Gate::authorize('view', $purchase);

        return view('livewire.purchase-status', [
            'purchase' => $purchase,
            'polling' => in_array($purchase->status, [PowerPurchaseStatus::Paid, PowerPurchaseStatus::Vending], true),
        ]);
    }
}
