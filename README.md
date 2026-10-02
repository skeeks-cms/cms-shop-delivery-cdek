# cms-shop-delivery-cdek

## Courier delivery and sender handoff

Each delivery method has independent `recipientMode` (`office` by default,
or `door`) and `senderMode` (`all` by default, `office`, or `door`). Old sites
retain pickup selection and their previous tariff list. Create/configure two
delivery methods for separate cart buttons. Sender `office` means the merchant
hands parcels to CDEK; sender `door` means CDEK collects from the sender.
Filter both the pickup widget response and server-side quote by these modes.

Courier mode renders an address form without loading Yandex/provider maps.
Cities autocomplete after two characters with a 400 ms debounce using CDEK
`location/suggest/cities` with `name`. `location/cities` with `city` matches
complete names and is unsuitable for prefixes. Suggestions provide `code`
and `full_name`; show region/country to distinguish duplicate names.
The server resolves the chosen CDEK city code; do not trust a posted city name.
Street and house are mandatory; apartment/office, entrance, floor and courier
comment are optional. Save them in handler data and standard order delivery
fields. Fixed-price courier mode still requires and validates the address.

Calculated courier tariffs use the current cart and verified city/address.
Only matching sender-to-door tariffs are offered. Retain tariff selection on
quantity changes, invalidate options on address edits, and force verification
at checkout through the common delivery lifecycle. Include delivery mode and
address in the quote fingerprint. Use a hidden canonical tariff input so
disabling the visible select during loading does not remove its code from a
concurrent form save. Apply async responses only to the matching saved address,
active delivery tab and latest request. Unload inactive pickup map iframes.
Load tariffs automatically after address fields are saved; show the retry
button only after a failed tariff request.

This package calculates delivery and saves checkout details. It does not create
a CDEK waybill or book a courier collection automatically.

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

## Widget v4 checkout contract

The optional `defaultLatitude` and `defaultLongitude` fields set the initial
map centre without Yandex geocoding. Validate them together (latitude -90..90,
longitude -180..180); comma decimals are normalized. The widget receives
`[longitude, latitude]`. Explicit coordinates take priority; otherwise known
`defaultCity` names use the saved approximate city-centre presets, and other
names retain geocoding. Empty city defaults to Moscow. Admin preset buttons
fill the city and both coordinates in the current form without submitting it.
Coordinates affect only the initial camera, never the quote's origin or
destination. Yandex Maps itself still requires a valid key; text search still
uses the geocoder.

The map uses the pinned official widget 4.0.0. It requests pickup points for
the visible rectangle (`action=byCoordinate`), forwarded to
`deliverypoints/byPolygons`. Panning/zooming loads the new area after the
widget's 500 ms debounce and cancels obsolete browser requests. Do not supply
`offices`/`officesRaw`: that opts out of loading by bounds. Tariffs remain
calculated for the selected destination, independently of loading markers.
Update the map view and `CdekService` together: v4 checks the major version in
`X-Service-Version`. The legacy `offices` action remains for older integrations.

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
