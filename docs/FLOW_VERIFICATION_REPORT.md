# EverGreen Cold Storage — Flow Verification Report

**Date:** 7 Oct 2026  
**Merchant checked:** `info@evergreen.com` (EverGreen Cold Storage)  
**Overall result:** **PASS — ready for demo**

---

## How this was checked

1. Automated feature tests (`tests/Feature/ColdStorage/`)
2. Demo data seeder (`ColdStorageDemoFlowSeeder`)
3. Live DB health command (`php artisan cold-storage:verify-flow`)

---

## Automated tests — 19 / 19 passed (72 assertions)

| Area | Result | What was proven |
|------|--------|-----------------|
| Full lifecycle | PASS | Receipt → dispatch → transfer → damage → bill → payment → temperature → reservation |
| Partial dispatch | PASS | Remaining stock stays with customer |
| Over-dispatch | PASS | Quantity above available is rejected |
| Double withdrawal | PASS | Cannot take stock already dispatched |
| Wrong customer | PASS | Cannot withdraw another customer’s lot |
| Wrong branch chamber | PASS | Chamber from another branch is blocked |
| Transfer | PASS | Location changes; customer total unchanged |
| Damage | PASS | Reason required; stock reduces |
| Cancel dispatch | PASS | Stock reverses correctly |
| Billing overlap / rates | PASS | Posted bills stay fixed; overlapping periods blocked |
| Billing quantity rules | PASS | Remaining qty + arrival-day rules work |
| Service charges | PASS | Payments do not create shop sales stock |
| Temperature alerts | PASS | Out-of-range flagged; can acknowledge |
| Occupancy / staff branch | PASS | Capacity math + branch scope |
| Lot ageing | PASS | Days-in-store report |
| Bill from stock | PASS | Draft lines built from remaining stock |
| Alert email digest | PASS | Dedicated recipients; skips when empty |

---

## Live database verify-flow — 13 / 13 passed

| Check | Status | Detail |
|-------|--------|--------|
| Active chambers | PASS | 3 |
| Posted goods receipts | PASS | 3 |
| Posted dispatches | PASS | 3 |
| Stock movements | PASS | 6 |
| Stock on hand (packages) | PASS | 40.00 |
| Posted storage bills | PASS | 2 / due 2,800.00 |
| Temperature readings | PASS | 3 |
| Reservations confirmed | PASS | 2 |
| CS isolation from sales | PASS | Confirmed in tests |
| Stock statement report | PASS | 2 rows |
| Capacity heatmap | PASS | 3 rows |
| Lot ageing report | PASS | 2 rows |
| Occupancy service | PASS | Working |

---

## Demo data created

After `php artisan db:seed --class=ColdStorageDemoFlowSeeder`:

- Customer: **Ahmed Traders**
- Chamber: **Demo Chamber A** (+ Demo Rack A)
- Posted goods receipt with lot **LOT-DEMO-1** (40 bags in, 10 dispatched → 30 remaining on that lot)
- Posted storage bill (where rates allow)
- Out-of-range temperature reading (for Action alerts)
- Confirmed reservation for next week

**Login for demo:** `info@evergreen.com` / `Evergreen@123`

---

## Known limits (not failures)

| Item | Notes |
|------|--------|
| Alert emails | Turned off in `config/ui-modules.php` (`cold_storage_alert_emails` = false). Alerts still show in the panel. |
| Reservations vs occupancy | Confirmed reservations do **not** yet reduce free capacity on the heatmap. Occupancy is physical stock only. |
| Legacy shop modules | Hidden via UI modules for cold-storage-first demos; code still exists. |

---

## Re-run anytime

```bash
php artisan db:seed --class=ColdStorageDemoFlowSeeder
php artisan cold-storage:verify-flow
php artisan test tests/Feature/ColdStorage/
```

---

## Demo docs

- Easy walkthrough: `docs/DEMO_GUIDE.md`
- Use cases (plain language): `docs/USE_CASES.md`
