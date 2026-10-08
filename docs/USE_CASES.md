# EverGreen Cold Storage — Use Cases

Plain-language situations the system is built for.

## UC-01 — New customer stores goods
**Actor:** Store operator  
**Goal:** Put customer bags into a chamber and know the lot number.

**Steps:** Create customer → create goods receipt → allocate chamber → post receipt.  
**Result:** Stock on hand increases; lot can be found later for return or billing.

## UC-02 — Customer takes part of their stock
**Actor:** Store operator  
**Goal:** Hand over only some bags from a lot.

**Steps:** Create return → choose same customer + lot → enter quantity ≤ available → post.  
**Result:** Remaining stock stays; over-withdrawal is blocked.

## UC-03 — Wrong branch or wrong customer blocked
**Actor:** System  
**Goal:** Stop mistakes that mix stores or owners.

**Examples:** Chamber from another branch on a receipt; return for a different customer than the lot owner.  
**Result:** Post fails with a clear error.

## UC-04 — Move bags inside the store
**Actor:** Store operator  
**Goal:** Shift stock from Rack A to Rack B without changing customer total.

**Steps:** Create transfer → from/to locations → post.  
**Result:** Location balances change; customer still owns the same total packages.

## UC-05 — Record damaged goods
**Actor:** Store operator  
**Goal:** Reduce stock when bags are torn or spoiled.

**Steps:** Damage adjustment with a written reason → negative quantity → post.  
**Result:** Stock drops; reason is kept for audit.

## UC-06 — Charge storage rent
**Actor:** Billing clerk  
**Goal:** Bill a customer for days goods stayed in store.

**Steps:** Ensure rate card exists → create bill (or Bill from stock) for a date period → preview → post → take payment.  
**Result:** Due amount shows on dashboard; payments reduce balance. Overlapping posted periods for the same lot are rejected.

## UC-07 — Watch chamber temperature
**Actor:** Operator / supervisor  
**Goal:** Know when a chamber is outside safe limits.

**Steps:** Record temperature → system compares to chamber min/max → Action alerts list open exceptions → acknowledge after checking.  
**Result:** Exceptions are visible until acknowledged.

## UC-08 — See how old stock is
**Actor:** Supervisor  
**Goal:** Find lots sitting too long.

**Steps:** Open Lot ageing.  
**Result:** Lots sorted by days in store.

## UC-09 — See chamber fullness
**Actor:** Supervisor  
**Goal:** Know which chambers are nearly full.

**Steps:** Open Capacity heatmap.  
**Result:** Fill % per chamber (low → critical).

## UC-10 — Book space before arrival
**Actor:** Operator  
**Goal:** Reserve chamber space for a future delivery.

**Steps:** Create reservation → Confirm → later Fulfill (link receipt) or Cancel with reason.  
**Result:** Upcoming bookings appear on Action alerts (next 7 days).

## UC-11 — Undo a wrong return
**Actor:** Supervisor  
**Goal:** Reverse a posted withdrawal.

**Steps:** Open posted return → Cancel with reason.  
**Result:** Stock is restored; document marked cancelled.

## UC-12 — Keep cold storage separate from shop sales
**Actor:** System  
**Goal:** Customer-owned goods must not look like shop inventory sold.

**Result:** Posting receipts/returns/bills does not create Sale or Purchase stock documents.
