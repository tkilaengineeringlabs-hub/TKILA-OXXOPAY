# Amount validation (phase 1)

## Preparing checkout guard tests

From the plugin root, with PHP 8.1+ and curl installed:

```sh
sh tests/fetch-hook-sources.sh
php tests/amounts.php
php tests/checkout-guard.php
```

Only the preparation command needs Internet access. It downloads public upstream sources over HTTPS, with no credentials. Dependencies default to `sys_get_temp_dir()/spin-oxxopay-test-sources`, outside the plugin tree, so they are neither distributed with the plugin nor tracked by its Git repository. No additional Git exclusion is necessary. Temporary files may be cleaned by the OS; rerun preparation if missing. The downloader rejects destinations inside the plugin tree.

Both scripts accept a custom directory as their first argument, or use `SPIN_TEST_SOURCES`. For example:

```sh
sh tests/fetch-hook-sources.sh /tmp/cannaland-hook-sources
php tests/checkout-guard.php /tmp/cannaland-hook-sources
```

Pinned dependencies (all fetched from `raw.githubusercontent.com`):

| Upstream revision | Upstream files | Local names |
| --- | --- | --- |
| `woocommerce/woocommerce`, tag `10.3.5` | `plugins/woocommerce/includes/class-wc-checkout.php`, `class-wc-form-handler.php` | Same basenames |
| Same WooCommerce tag | `plugins/woocommerce/src/StoreApi/Routes/V1/Checkout.php`, `CheckoutOrder.php`, `AbstractCartRoute.php` | Same basenames |
| Same WooCommerce tag | `plugins/woocommerce/src/StoreApi/Utilities/CartController.php` | Same basename |
| `WordPress/WordPress`, tag `6.8.3` | `wp-includes/class-wp-hook.php`, `plugin.php`, `rest-api/class-wp-rest-server.php` | `class-wp-hook.php`, `wp-plugin.php`, `wp-rest-server.php` |
| `malsubrata/woo-wallet`, commit `cd1f36e4fdd0c5e0777e548975eb03fda1b4e942` | `includes/class-woo-wallet.php`, `includes/class-woo-wallet-wallet.php` | `wallet.php`, `wallet-ops.php` |

The test verifies all 11 SHA-256 hashes before executing downloaded PHP. Preparation also verifies these hashes, without running the behavioral tests. There is no fallback to an unverified file or moving branch. WordPress `plugin.php` loads its sibling `class-wp-hook.php` itself; that filename must be preserved to avoid missing-file or duplicate-class errors.

The guard suite checks hook ordering in the pinned upstream source and executes our callbacks using the actual WordPress hook dispatcher. Checkout objects, lifecycle and the debit callback are test doubles. Passing proves the inspected source ordering and guard behavior in that harness; it is not an end-to-end WooCommerce/TeraWallet checkout, a real ledger debit test, or proof for other versions/plugins. `CheckoutOrder.php` and `AbstractCartRoute.php` are hash-verified dependencies but their complete execution is not exercised by this harness.

Run `php tests/amounts.php` from the plugin directory. Requires PHP 8.1+ and the bundled vendor directory. No WordPress installation, credentials or network calls are used. The suite uses small WooCommerce doubles and the real bundled SDK serializer/models. Rejected cases also execute `process_payment()` and assert that no API instance is requested and no order mutation occurs. Accepted cases execute the full gateway method through the real SDK with Guzzle MockHandler and an example.invalid host: the serialized request total must equal the pending order total. These responses are simulated, not FEMSA acceptance tests.

The suite also retries blocked wallet orders three times, including coupon, zero balance, sub-cent adjustments and taxed wallet with shipping, fees and quantity rounding. It checks that totals, fees, status and an existing reference are not changed. It does not establish idempotency for successful submissions or concurrent checkout requests, and does not cancel an existing remote reference. See [the TeraWallet investigation](../docs/terawallet-compatibility.md).

## Representation and scope

Digital FEMSA documents `discount_lines` on [Create order](https://developers.digitalfemsa.io/reference/createorder). The bundled SDK 1.1.0 `OrderDiscountLinesRequest` documents nonnegative integer cents with `code` and `type` (`loyalty`, `campaign`, `coupon`, `sign`). `Product`, `ShippingRequest` and `OrderTaxRequest` reject negative amounts. Ordinary WooCommerce coupons remain `discount_lines` of type `coupon`.

Neither this contract nor the available FEMSA documentation establishes that externally funded TeraWallet payments may be reported as discounts, or defines `sign` as an external wallet payment. A supported field name alone does not establish financial, accounting or fiscal equivalence. Therefore this phase deliberately rejects negative fees, including partial wallet fees, rather than relabeling wallet money as a coupon, loyalty reward or positive charge. It does not claim production TeraWallet compatibility. Confirm the supported external-payment representation with FEMSA before enabling that path; do not infer it from another provider's API.

The pending amount is the persisted unpaid WooCommerce order total, including any already-applied wallet fee. No session wallet balance is subtracted a second time. Reductions applied only to the order total cause reconciliation to fail. A zero/negative remaining balance is rejected without marking the order paid. Fees with negative tax components that cannot be expressed using nonnegative API tax lines are also rejected.

Monetary inputs are rounded explicitly half-up to integer cents, within the SDK int32 product-price range. Product subtotals are distributed over at most two price groups, preserving integer quantities and the exact rounded line subtotal. Fractional quantities are unsupported and rejected. Every generated component must be nonnegative, and the exact integer sum must equal the order total. No balancing fee or invented discount is added when it does not match.

## Remaining integration checks

- Real WooCommerce versions, classic checkout and Blocks, taxes inclusive/exclusive, coupon types, currencies and shipping rounding settings.
- Installed TeraWallet version, fee persistence, wallet tax mode, debit timing, reservations, retries and refunds. A correct total does not prove that wallet funds have been reserved or debited.
- FEMSA sandbox acceptance of split price lines, zero-price items, payment limits and any future agreed wallet representation. No provider API has been called by this suite.
- Existing webhook/status behavior is outside this phase. The tests do not assert real stock, email or wallet lifecycle behavior.


## Webhook ownership and state hardening (Tkila)

Run after pulling the latest code:

```sh
php -l spin_plugin.php
php -l spin_block_gateway.php
php tests/webhook-ownership.php
php tests/amounts.php
php tests/checkout-guard.php
```

The isolated `webhook-ownership.php` test checks that an incoming event cannot select a WooCommerce order by metadata alone. It requires the matching provider order ID saved on that order, the matching local gateway (`spin`), and the expected integration marker. Invalid IDs, mismatched references, other payment methods and missing metadata must be rejected. It requires neither credentials nor a provider connection.

The gateway now rejects invalid JSON, checks the provider status before state changes, ignores already-paid orders on duplicate paid events, and does not cancel already-paid/refunded/cancelled orders on stale closure events. Metadata is read/written via WooCommerce order CRUD to support HPOS.

**Limits:** These tests do not execute real webhook HTTP requests, verify any provider signature, or simulate concurrent delivery. Provider lookup is used as the remote status authority; sandbox tests of paid, expired, cancelled, duplicate and out-of-order events are still required before production. Existing reference creation is not generally idempotent under concurrent checkout.
