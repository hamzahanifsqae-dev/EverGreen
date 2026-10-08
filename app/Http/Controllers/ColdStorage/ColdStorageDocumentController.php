<?php

namespace App\Http\Controllers\ColdStorage;

use App\Models\ColdStorageBill;
use App\Models\ColdStorageDispatch;
use App\Models\ColdStorageReceipt;
use App\Models\Merchant;
use App\Models\PermissionModule;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\View\View;

class ColdStorageDocumentController
{
    public function show(string $type, string $id): View
    {
        $record = match ($type) {
            'receipt' => ColdStorageReceipt::query()->with(['merchant', 'customer', 'branch', 'business', 'items.product', 'items.allocations.chamber', 'items.allocations.location'])->findOrFail($id),
            'dispatch' => ColdStorageDispatch::query()->with(['merchant', 'customer', 'branch', 'business', 'lines.receiptItem.product', 'lines.chamber', 'lines.location'])->findOrFail($id),
            'bill' => ColdStorageBill::query()->with(['merchant', 'customer', 'branch', 'business', 'lines', 'services'])->findOrFail($id),
            default => abort(404),
        };

        $this->authorizeRecord($record);

        if ($type === 'bill') {
            abort_unless(($record->status ?? null) === 'posted', 403);
        }

        return view('cold-storage.document', [
            'type' => $type,
            'record' => $record,
            'currency' => config('cold-storage.currency'),
        ]);
    }

    private function authorizeRecord(Model $record): void
    {
        $user = auth('merchant')->user() ?? auth('staff')->user();
        abort_unless($user instanceof Merchant || $user instanceof User, 403);

        $merchantId = $user instanceof Merchant ? $user->id : $user->merchant_id;
        abort_unless($record->getAttribute('merchant_id') === $merchantId, 403);
        abort_unless(PermissionModule::isEnabledForMerchant('cold_storage', (string) $merchantId), 403);

        if ($user instanceof User) {
            abort_unless($user->hasPermissionTo('cold_storage.view'), 403);
            abort_unless($user->branches()->where('branches.id', $record->getAttribute('branch_id'))->exists(), 403);
        }
    }
}
