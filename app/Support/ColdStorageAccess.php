<?php

namespace App\Support;

use App\Exceptions\ColdStorageException;
use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\PermissionModule;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

class ColdStorageAccess
{
    public static function user(): Merchant|User|null
    {
        $user = Filament::auth()->user();

        return $user instanceof Merchant || $user instanceof User ? $user : null;
    }

    public static function merchantId(?object $user = null): ?string
    {
        $user ??= self::user();

        return match (true) {
            $user instanceof Merchant => $user->id,
            $user instanceof User => $user->merchant_id,
            default => null,
        };
    }

    public static function actorId(?object $user = null): ?string
    {
        $user ??= self::user();

        return $user instanceof User ? $user->id : null;
    }

    public static function can(string $action, ?object $user = null): bool
    {
        $user ??= self::user();

        if (! $user instanceof Merchant && ! $user instanceof User) {
            return false;
        }

        if (! PermissionModule::isEnabledForCurrentMerchant('cold_storage') && $user instanceof User) {
            return false;
        }

        if ($user instanceof Merchant && ! $user->permissionModules()->where('module', 'cold_storage')->exists()) {
            return false;
        }

        $guard = Filament::getCurrentPanel()?->getAuthGuard() ?? 'merchant';

        return $user->hasPermissionTo('cold_storage.'.$action, $guard);
    }

    public static function scope(Builder $query, Merchant|User|null $user = null): Builder
    {
        $user ??= self::user();
        $merchantId = self::merchantId($user);

        if ($merchantId === null) {
            return $query->whereRaw('1 = 0');
        }

        $query->where($query->getModel()->getTable().'.merchant_id', $merchantId);

        if (! $user instanceof User) {
            return $query;
        }

        return $query
            ->whereHas('business.users', fn (Builder $businessQuery) => $businessQuery->where('users.id', $user->id))
            ->whereHas('branch.users', fn (Builder $branchQuery) => $branchQuery->where('users.id', $user->id));
    }

    public static function assertActorCanUseBranch(?string $actorId, string $branchId, string $businessId): void
    {
        if ($actorId === null) {
            return;
        }

        $user = User::query()->find($actorId);

        if (! $user) {
            return;
        }

        $assignedToBranch = $user->branches()->where('branches.id', $branchId)->exists();
        $assignedToBusiness = $user->businesses()->where('businesses.id', $businessId)->exists();

        if (! $assignedToBranch || ! $assignedToBusiness) {
            throw ColdStorageException::make('This branch is outside your assignment.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function stampDocument(array $data): array
    {
        $user = self::user();
        $data['merchant_id'] = self::merchantId($user);
        $data['created_by'] = self::actorId($user);
        $data['status'] = $data['status'] ?? 'draft';

        if (filled($data['branch_id'] ?? null)) {
            $data['business_id'] = Branch::query()->whereKey($data['branch_id'])->value('business_id');
        }

        return $data;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function branchOptions(): array
    {
        $user = self::user();

        $query = Branch::query()
            ->withoutTrashed()
            ->with('business')
            ->where('is_active', true);

        if ($user instanceof Merchant) {
            $query->where('merchant_id', $user->id);
        }

        if ($user instanceof User) {
            $query->whereIn('branches.id', $user->branches()->pluck('branches.id'));
        }

        if (! $user instanceof Merchant && ! $user instanceof User) {
            return [];
        }

        return $query
            ->orderBy('business_id')
            ->orderBy('branches.name')
            ->get()
            ->groupBy(fn (Branch $branch) => $branch->business?->name ?? 'Other')
            ->map(fn ($group) => $group->pluck('name', 'id')->all())
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function customerOptions(): array
    {
        return CustomerResource::scopeVisibleCustomers(
            Customer::query(),
            self::user(),
        )
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
