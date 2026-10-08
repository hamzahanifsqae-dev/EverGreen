<?php

namespace App\Services\ColdStorage;

use App\Mail\ColdStorageActionAlertsMailable;
use App\Models\ColdStorageAlertEmailSetting;
use App\Models\ColdStorageBill;
use App\Models\ColdStorageReservation;
use App\Models\ColdStorageTemperatureReading;
use App\Models\Merchant;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class ActionAlertMailService
{
    public function __construct(private ActionAlertService $alerts) {}

    public function settingsForMerchant(string $merchantId): ColdStorageAlertEmailSetting
    {
        return ColdStorageAlertEmailSetting::query()->firstOrCreate(
            ['merchant_id' => $merchantId],
            [
                'recipient_emails' => [],
                'is_enabled' => false,
                'include_temperature' => true,
                'include_bills' => true,
                'include_reservations' => true,
            ],
        );
    }

    /**
     * @return array{sent: int, skipped: int, failed: int, details: list<string>}
     */
    public function sendDueDigests(?string $merchantId = null): array
    {
        $result = ['sent' => 0, 'skipped' => 0, 'failed' => 0, 'details' => []];

        $query = ColdStorageAlertEmailSetting::query()->where('is_enabled', true);

        if ($merchantId !== null) {
            $query->where('merchant_id', $merchantId);
        }

        foreach ($query->with('merchant')->get() as $setting) {
            $line = $this->sendDigestForSetting($setting, force: false);

            if (str_starts_with($line, 'SENT')) {
                $result['sent']++;
            } elseif (str_starts_with($line, 'NOT SENT')) {
                $result['failed']++;
            } else {
                $result['skipped']++;
            }

            $result['details'][] = $line;
        }

        return $result;
    }

    public function sendDigestForMerchant(string $merchantId, bool $force = true): string
    {
        return $this->sendDigestForSetting($this->settingsForMerchant($merchantId), $force);
    }

    public function sendDigestForSetting(ColdStorageAlertEmailSetting $setting, bool $force = false): string
    {
        $merchant = $setting->merchant ?? Merchant::query()->find($setting->merchant_id);
        $label = $merchant?->name ?? $setting->merchant_id;

        if (! $force && ! $setting->is_enabled) {
            return "SKIPPED {$label}: alert emails are disabled.";
        }

        $emails = $setting->normalizedRecipientEmails();

        if ($emails === []) {
            return "SKIPPED {$label}: no valid recipient emails configured.";
        }

        $digest = $this->buildDigest($setting);

        if (($digest['open_count'] ?? 0) <= 0) {
            return "SKIPPED {$label}: no open action alerts.";
        }

        try {
            Mail::to($emails)->send(new ColdStorageActionAlertsMailable($merchant, $digest));

            $setting->update(['last_sent_at' => now()]);

            return 'SENT '.$label.' → '.implode(', ', $emails).' ('.$digest['open_count'].' open)';
        } catch (\Throwable $exception) {
            return 'NOT SENT '.$label.': '.$exception->getMessage();
        }
    }

    /**
     * @return array{
     *     temperature_count: int,
     *     bill_count: int,
     *     reservation_count: int,
     *     open_count: int,
     *     temperature: list<array<string, mixed>>,
     *     bills: list<array<string, mixed>>,
     *     reservations: list<array<string, mixed>>,
     *     currency: string,
     *     panel_url: ?string
     * }
     */
    public function buildDigest(ColdStorageAlertEmailSetting $setting): array
    {
        $filters = [
            'merchant_id' => $setting->merchant_id,
            'restrict_branches' => false,
            'branch_ids' => [],
        ];

        $temperature = [];
        $bills = [];
        $reservations = [];

        if ($setting->include_temperature) {
            $temperature = $this->alerts->temperatureQuery($filters)
                ->where('is_out_of_range', true)
                ->whereNull('acknowledged_at')
                ->with('chamber')
                ->orderByDesc('recorded_at')
                ->limit(15)
                ->get()
                ->map(fn (ColdStorageTemperatureReading $reading): array => [
                    'when' => $reading->recorded_at?->format(config('cold-storage.date_format').' H:i'),
                    'chamber' => $reading->chamber?->name,
                    'temperature' => $reading->temperature.' '.$reading->temperature_unit,
                ])
                ->all();
        }

        if ($setting->include_bills) {
            $bills = $this->alerts->billQuery($filters)
                ->where('status', 'posted')
                ->where('due_amount', '>', 0)
                ->with('customer')
                ->orderByDesc('due_amount')
                ->limit(15)
                ->get()
                ->map(fn (ColdStorageBill $bill): array => [
                    'bill_no' => $bill->bill_no,
                    'customer' => $bill->customer?->name,
                    'due_amount' => (float) $bill->due_amount,
                ])
                ->all();
        }

        if ($setting->include_reservations) {
            $today = Carbon::today()->toDateString();
            $horizon = Carbon::today()->addDays(7)->toDateString();

            $reservations = $this->alerts->reservationQuery($filters)
                ->where('status', 'confirmed')
                ->whereDate('reserved_from', '>=', $today)
                ->whereDate('reserved_from', '<=', $horizon)
                ->with('customer')
                ->orderBy('reserved_from')
                ->limit(15)
                ->get()
                ->map(fn (ColdStorageReservation $reservation): array => [
                    'reservation_no' => $reservation->reservation_no,
                    'customer' => $reservation->customer?->name,
                    'reserved_from' => $reservation->reserved_from?->format(config('cold-storage.date_format')),
                ])
                ->all();
        }

        $temperatureCount = count($temperature);
        $billCount = count($bills);
        $reservationCount = count($reservations);

        return [
            'temperature_count' => $temperatureCount,
            'bill_count' => $billCount,
            'reservation_count' => $reservationCount,
            'open_count' => $temperatureCount + $billCount + $reservationCount,
            'temperature' => $temperature,
            'bills' => $bills,
            'reservations' => $reservations,
            'currency' => config('cold-storage.currency', 'PKR'),
            'panel_url' => $this->actionAlertsUrl(),
        ];
    }

    private function actionAlertsUrl(): ?string
    {
        try {
            return URL::to('/merchant/cold-storage-action-alerts');
        } catch (\Throwable) {
            return null;
        }
    }
}
