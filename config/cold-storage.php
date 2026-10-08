<?php

return [

    /*
    | Currency matches the existing sales and expense screens.
    */
    'currency' => 'PKR',

    'date_format' => 'd/m/Y',

    /*
    | Defaults copied onto a new rate card. Each card keeps its own copy, so
    | later changes to this file do not rewrite historical bills.
    |
    | Assumptions until commercial rules are confirmed:
    | - The arrival day is billed. The departure day is not.
    | - A daily rate charges quantity-days: the sum of each day's billable balance.
    | - A monthly rate prorates those quantity-days by the number of days in each calendar month.
    | - A seasonal rate prorates quantity-days by season_length_days.
    | - Partial withdrawal uses the remaining end-of-day balance. Goods that leave are
    |   included in that day's quantity only when the departure day is billed.
    | - Bag and pallet capacity and rates count packages. Kilogram and tonne capacity
    |   and rates count net weight. Packages are not converted into weight.
    | - Transfers stay inside a single branch.
    | - The same lot can be billed again for the same or overlapping dates (e.g. after a partial return).
    | - Minimum charge applies per billed segment when the calculated amount is above zero
    |   and below the minimum. Service charges are extra and are not folded into that minimum.
    | - Storage invoices are customer charges. They do not create sales, purchases, or product stock.
    | - Temperature readings store the chamber limits from the moment of entry. Later limit
    |   edits do not rewrite old exception flags.
    */
    'defaults' => [
        'bill_arrival_day' => true,
        'bill_departure_day' => false,
        'minimum_charge' => 0,
        'rounding_mode' => 'nearest',
        'season_length_days' => 90,
        'temperature_unit' => 'C',
    ],

    /*
    | Action-alert digest emails use a dedicated recipient list
    | (Cold Storage → Alert emails), not notification templates.
    */
    'action_alerts' => [
        'mail_daily_at' => '08:00',
    ],

];
