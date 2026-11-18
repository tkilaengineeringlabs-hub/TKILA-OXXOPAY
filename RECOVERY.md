Recovery branch: recovery/v1.0.15-png

Source of all runtime files: exact tkila-oxxo-pay-v1.0.15-staging.zip.
Baseline SHA-256: f632a04acaed6d5abba1d7fc907152b570fa24c076ffb5c1cd8d10f15e60b47c.
The fixture is retained for repeatable comparison of all 313 original PHP files.
Only templates/confirmation.php is authorized to differ among those PHP files.

Runtime changes against ZIP v1.0.15:
- templates/confirmation.php: v1.0.16 status presentation and Google Maps button markup;
  independent download button, separate notice and deferred export script.
- assets/css/oxxo-confirmation.css: v1.0.16 visual alignment and download button styles.
- assets/js/oxxo-ticket.js: new click-only canvas PNG generation. Uses rendered data;
  barcode CORS/image/canvas failure yields a separate notice. No order/API mutations.
- VERSION: 1.0.19, read by the original version.php.

spin_checkout.php remains byte-for-byte original, including its Version: 1.0.15 header.
Consequently WordPress plugin listings still display 1.0.15, while the runtime constant
and archive version are 1.0.19. This is intentional to meet the immutable PHP requirement.
No copied v1.0.18 recovery or diagnostics changes are used.

Validation:
SPIN_TEST_SOURCES=/private/tmp/tkila-hook-sources python3 scripts/package-recovery.py
Prepare missing pinned upstream sources with:
sh tests/fetch-hook-sources.sh /private/tmp/tkila-hook-sources
The packager refuses an existing output; it never modifies the plugin header.
It runs all seven PHP suites, PNG mock browser tests, integrity and syntax checks,
then validates the archive inventory and every unmodified original file after reopening it.
Manual mutation of spin_plugin.php was rejected by the integrity test and restored.

All tests passed locally. PNG tests use browser API doubles, including success,
canvas, CORS, HTTP, decode and null-blob failures. Real browser rendering, device
compatibility and Horsements checkout/confirmation remain unverified. No credentials
or provider requests were used. A real Horsements test is required before claiming
integration success. External barcode CORS restrictions can prevent PNG download;
the visible confirmation and reference remain usable.
