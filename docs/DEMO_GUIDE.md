# EverGreen Cold Storage — Demo Guide

Easy walkthrough for showing the product to a customer.

## Login

- **URL:** `/merchant/login`
- **Email:** `info@evergreen.com`
- **Password:** `Evergreen@123`

## What this software is

EverGreen Cold Storage is a **merchant panel for cold stores**.

Customers keep their own goods with you (bags, crates, weight). You:

1. Receive goods into a chamber  
2. Track where they sit  
3. Return (hand back) when the customer asks  
4. Charge storage rent and collect payment  

It is **not** a normal shop POS. Goods stay customer-owned until they leave.

---

## Demo story (10–15 minutes)

### 1) Home dashboard
Open **Dashboard**.

Show:

- Receiving / Return packages  
- Stock on hand  
- Billing due  
- Chamber occupancy  
- Action alerts / lot ageing shortcuts  

Tip: set **Date From / Date To** to cover the demo bill period if Billing shows zero.

### 2) Setup (if asked “how do we start?”)
Under **Cold Storage** / setup menus:

- Business + Branch  
- Chamber (capacity + temperature limits)  
- Optional rack/bin locations  
- Customer (goods owner)  
- Product (e.g. Potatoes)  
- Storage rate card (daily bag rate for that customer)

### 3) Goods receipt
**Goods receipts → Create**

- Branch + customer + date  
- Add lot lines (product, packages, weight)  
- Allocate into chamber / rack  
- **Save**, then open and **Post**

After post, stock appears on the dashboard.

### 4) Temperature check
**Temperature readings → Create**

Enter a value outside chamber min/max.  
It shows as out of range → appears on **Action alerts** → **Acknowledge** after checking.

### 5) Return (customer takes goods)
**Return → Create**

- Same customer  
- Pick lots still in stock  
- Quantity cannot exceed available  
- **Post**

Stock on hand drops. No sale invoice is created.

### 6) Damage / adjustment (optional)
**Damage and adjustments**

Record torn bags with a reason and negative quantity. Post it.

### 7) Storage bill
**Storage bills**

- Use **Bill from stock** (fast), or Create manually  
- Choose customer, period, charge basis  
- Open bill → **Calculation preview** → **Post**  
- Record payment against due amount  

Dashboard **Billing** card updates for that period.

### 8) Extra modules to flash
- **Lot ageing** — oldest lots still in store  
- **Capacity heatmap** — how full each chamber is  
- **Reservations** — book space before goods arrive (Confirm / Fulfill / Cancel)

---

## Reset / refresh demo data

```bash
php artisan db:seed --class=ColdStorageDemoProductsSeeder
php artisan db:seed --class=ColdStorageDemoFlowSeeder
php artisan cold-storage:verify-flow
```

---

## What not to demo as “sold stock”

Cold storage movements never create classic **Sales** / **Purchases** product stock.  
If someone asks: “Is this my shop inventory?” → **No — this is customer-owned storage.**
