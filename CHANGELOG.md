CHANGELOG
==============

2.1.3 — 2026-10-01
-----------------
  * Add opt-in tariff selection; keep pickup-point-only checkout and fixed delivery pricing by default.
  * Save widget v3 tariff delivery_sum in the checkout price field.
  * Recalculate the selected tariff on the server through the shared shop lifecycle when parcels change; verify before checkout and clear stale amounts on failure.
  * Update calculation errors from cart AJAX responses, including recovery when a tariff becomes available again.
  * Invalidate maps when delivery inputs change so displayed tariffs use the current basket.
  * Load maps only while choosing a pickup point; unload selected/hidden maps and defer their refresh until reopening.

1.0.0
-----------------
  * Fixed

