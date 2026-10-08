<?php

namespace App\Services\ColdStorage;

use App\Models\ColdStorageBill;
use App\Models\ColdStorageReservation;
use App\Models\ColdStorageTemperatureReading;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ActionAlertService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function openCount(array $filters): int
    {
        return $this->unacknowledgedTemperatureCount($filters)
            + $this->overdueBillCount($filters)
            + $this->upcomingReservationCount($filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function unacknowledgedTemperatureCount(array $filters): int
    {
        return $this->temperatureQuery($filters)
            ->where('is_out_of_range', true)
            ->whereNull('acknowledged_at')
            ->count();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function overdueBillCount(array $filters): int
    {
        return $this->billQuery($filters)
            ->where('status', 'posted')
            ->where('due_amount', '>', 0)
            ->count();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function upcomingReservationCount(array $filters): int
    {
        $today = Carbon::today()->toDateString();
        $horizon = Carbon::today()->addDays(7)->toDateString();

        return $this->reservationQuery($filters)
            ->where('status', 'confirmed')
            ->whereDate('reserved_from', '>=', $today)
            ->whereDate('reserved_from', '<=', $horizon)
            ->count();
    }

    public function acknowledgeTemperature(ColdStorageTemperatureReading $reading, ?string $actorId): ColdStorageTemperatureReading
    {
        $reading->update([
            'acknowledged_at' => now(),
            'acknowledged_by' => $actorId,
        ]);

        return $reading->refresh();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<ColdStorageTemperatureReading>
     */
    public function temperatureQuery(array $filters): Builder
    {
        $query = ColdStorageTemperatureReading::query()
            ->where('merchant_id', $filters['merchant_id']);

        $this->applyBranchFilters($query, $filters);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<ColdStorageBill>
     */
    public function billQuery(array $filters): Builder
    {
        $query = ColdStorageBill::query()
            ->where('merchant_id', $filters['merchant_id']);

        $this->applyBranchFilters($query, $filters);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<ColdStorageReservation>
     */
    public function reservationQuery(array $filters): Builder
    {
        $query = ColdStorageReservation::query()
            ->where('merchant_id', $filters['merchant_id']);

        $this->applyBranchFilters($query, $filters);

        return $query;
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyBranchFilters(Builder $query, array $filters): void
    {
        $table = $query->getModel()->getTable();

        if (filled($filters['business_id'] ?? null)) {
            $query->where($table.'.business_id', $filters['business_id']);
        }

        if (filled($filters['branch_id'] ?? null)) {
            $query->where($table.'.branch_id', $filters['branch_id']);
        }

        if (($filters['restrict_branches'] ?? false) === true) {
            $branchIds = $filters['branch_ids'] ?? [];
            $query->whereIn($table.'.branch_id', $branchIds === [] ? ['00000000-0000-0000-0000-000000000000'] : $branchIds);
        }
    }
}
