# cms-shop-delivery-cdek

## Automatic recalculation

Requires the delivery calculation contract in `skeeks/cms-shop >= 3.2.7.26`.
In tariff mode the callback stores the selected `tariff_code`. The server
resolves the pickup point's city through CDEK, builds parcels from the current
order and requests `calculator/tarifflist`; the browser's amount is not trusted.
The widget and server share `CdekDeliveryHandler::getOrderPackages()` (grams,
dimensions converted from mm to cm; fractional units round up to whole parcels).

Successful quotes are reused for up to 300 seconds only while point, tariff,
origin, account, currency and packages match. Failures clear the old price and
are retried; checkout rejects them. A missing/unavailable tariff requires the
buyer to select one again. Old selections without a tariff code require one
reselection. Automatic CDEK calculations currently support RUB.

The shop owns mutation/open-cart/checkout triggers. The adapter implements
`supportsAutomaticCalculation()` and `refreshDeliveryPrice($force)` rather than
subscribing independently to every item/controller event. Completed orders keep
their agreed delivery price. Fixed-price mode is unchanged.

The checkout invalidates its map iframe when the server's delivery input hash
changes. An open map rebuilds with current parcels. A hidden map stays unloaded:
selecting a point removes its iframe, and a saved selection does not create one
on cart load. Opening the map loads it once after the selection update returns,
using the current basket. Fixed-price mode does not invalidate on cart changes.

Run `php tests/delivery-recalculation.php <vendor/autoload.php>` for isolated
SQLite tests of item callbacks, totals, cache freshness, server-owned metadata,
failure/unavailable/zero quotes, fixed price and completed orders.

## Widget v3 checkout contract

Use recipient mode (`sender: false`) for a buyer choosing a delivery point.
`isChooseTariff` defaults to `0`, including for old configurations that only
contain `isCalculatePrice`. That legacy key remains loadable but no longer
controls the checkout. The administration form exposes the new setting instead.

With `isChooseTariff=0`, pass `from: null` and empty `goods` to the widget:
it selects a pickup point without requesting or presenting tariffs. Do not
render the checkout price input, and always use the delivery method's fixed
price, even when the order contains an old calculated price.

With `isChooseTariff=1`, pass the configured origin and order parcels and
render the checkout price input. The buyer selects a pickup point and tariff.
The map's `onChoose(type, tariff, address)` callback passes the selected tariff
and pickup point to the checkout. The delivery amount is
`tariff.delivery_sum`, not a top-level `price`. When calculation is enabled,
the checkout stores that amount as a string in `CdekCheckoutModel[price]` and
submits the delivery form so the order totals are refreshed. A missing tariff
must clear the previous amount rather than retain a price from another point.

After changing this integration, verify selection of a pickup point, the
delivery line and total, and persistence after reloading the cart. Also verify
that disabling calculation leaves the price input absent. Selecting a pickup
point does not require placing an order.
