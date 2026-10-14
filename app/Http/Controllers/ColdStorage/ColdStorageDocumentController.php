<?php

namespace App\Http\Controllers\ColdStorage;

use App\Models\ColdStorageBill;
use App\Models\ColdStorageDispatch;
use App\Models\ColdStorageReceipt;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\PermissionModule;
use App\Models\User;
use App\Services\ColdStorage\CustomerLedgerService;
use App\Services\ColdStorage\RentCollectionRegisterService;
use App\Services\ColdStorage\StockRegisterService;
use App\Support\ColdStorageAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ColdStorageDocumentController
{
    public function show(string $type, string $id): View
    {
        $record = match ($type) {
            'receipt' => ColdStorageReceipt::query()->with(['merchant.logo', 'customer', 'branch', 'business', 'items.product', 'items.allocations.chamber', 'items.allocations.location'])->findOrFail($id),
            'dispatch' => ColdStorageDispatch::query()->with(['merchant.logo', 'customer', 'branch', 'business', 'lines.receiptItem.product', 'lines.chamber', 'lines.location'])->findOrFail($id),
            'bill' => ColdStorageBill::query()->with(['merchant.logo', 'customer', 'branch', 'business', 'lines', 'services'])->findOrFail($id),
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

    public function ledger(Customer $customer, Request $request): View
    {
        $this->authorizeMerchantScoped($customer);

        $statement = app(CustomerLedgerService::class)->statement(
            $customer,
            ['from' => $request->query('from'), 'to' => $request->query('to')],
            ColdStorageAccess::user(),
        );

        return view('cold-storage.ledger-document', [
            'customer' => $customer,
            'statement' => $statement,
            'merchant' => $customer->merchant,
            'currency' => config('cold-storage.currency'),
        ]);
    }

    public function stockRegister(Request $request): View
    {
        $user = $this->authorizedUser();
        $merchantId = $user instanceof Merchant ? $user->id : $user->merchant_id;

        $register = app(StockRegisterService::class)->register([
            'merchant_id' => $merchantId,
            'branch_id' => $request->query('branch_id'),
            'customer_id' => $request->query('customer_id'),
            'lot_number' => $request->query('lot_number'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ], $user);

        return view('cold-storage.stock-register-document', [
            'groups' => $register['groups'],
            'merchant' => $user instanceof Merchant ? $user : Merchant::query()->find($merchantId),
        ]);
    }

    public function rentRegister(Request $request): View
    {
        $user = $this->authorizedUser();
        $merchantId = $user instanceof Merchant ? $user->id : $user->merchant_id;

        $register = app(RentCollectionRegisterService::class)->register([
            'merchant_id' => $merchantId,
            'branch_id' => $request->query('branch_id'),
            'customer_id' => $request->query('customer_id'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ], $user);

        return view('cold-storage.rent-register-document', [
            'rows' => $register['rows'],
            'totals' => $register['totals'],
            'currency' => config('cold-storage.currency'),
            'merchant' => $user instanceof Merchant ? $user : Merchant::query()->find($merchantId),
        ]);
    }

    private function authorizedUser(): Merchant|User
    {
        $user = auth('merchant')->user() ?? auth('staff')->user();
        abort_unless($user instanceof Merchant || $user instanceof User, 403);
        abort_unless(PermissionModule::isEnabledForMerchant('cold_storage', (string) ($user instanceof Merchant ? $user->id : $user->merchant_id)), 403);

        if ($user instanceof User) {
            abort_unless($user->hasPermissionTo('cold_storage.view'), 403);
        }

        return $user;
    }

    private function authorizeMerchantScoped(Customer $customer): void
    {
        $user = $this->authorizedUser();
        $merchantId = $user instanceof Merchant ? $user->id : $user->merchant_id;
        abort_unless($customer->merchant_id === $merchantId, 403);
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
