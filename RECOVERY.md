Recovery branch: recovery/v1.0.15-png

Source of all runtime files: exact tkila-oxxo-pay-v1.0.15-staging.zip.
Baseline SHA-256: f632a04acaed6d5abba1d7fc907152b570fa24c076ffb5c1cd8d10f15e60b47c.
The fixture is retained for repeatable comparison of all 313 original PHP files.
Only templates/confirmation.php and the exact Version: 1.0.15 -> 1.0.21 metadata
replacement in spin_checkout.php are authorized to differ among those PHP files.

Runtime changes against ZIP v1.0.15:
- templates/confirmation.php: v1.0.16 status presentation and Google Maps button markup;
  independent download button, separate notice and deferred export script.
- assets/css/oxxo-confirmation.css: v1.0.16 visual alignment and download button styles.
- assets/js/oxxo-ticket.js: new click-only canvas PNG generation. Uses rendered data;
  barcode CORS/image/canvas failure yields a separate notice. No order/API mutations.
- VERSION: 1.0.21, read by the original version.php.

spin_checkout.php differs only in its Version: 1.0.21 header. WordPress plugin
metadata, the runtime constant and the archive version now agree on 1.0.21.
All payment logic remains byte-for-byte original.
No copied v1.0.18 recovery or diagnostics changes are used.

Validation:
SPIN_TEST_SOURCES=/private/tmp/tkila-hook-sources python3 scripts/package-recovery.py
Prepare missing pinned upstream sources with:
sh tests/fetch-hook-sources.sh /private/tmp/tkila-hook-sources
The packager replaces an existing output only after all checks pass; it validates
the exact authorized plugin header change without modifying PHP.
It runs all seven PHP suites, PNG mock browser tests, integrity and syntax checks,
then validates the archive inventory and every unmodified original file after reopening it.
Manual mutation of spin_plugin.php was rejected by the integrity test and restored.

Definitive PNG export (1.0.21)

The export uses a detached copy of the existing card and the identical plugin CSS.
The confirmation page CSS/markup remain unchanged except the export script cache
key. Buttons, links, notices and countdown are removed only from the export copy.
The copy lives in a separate iframe with a fixed desktop width; mobile exports
retain the same two-column layout at scale 3 (3444 px wide) and full content height.
The logo files already in images/ are embedded byte-for-byte in the local bundle.
No CDN, fonts, FEMSA API, external image download or runtime network is needed.

Verified symbology:
https://developers.digitalfemsa.io/docs/cargo-recurrente-efectivo-direct-api
Section 4.2 links the provider-issued example reference/image 84000045432316.
The original public PNG is frozen under tests/fixtures/barcode with SHA-256 and
source URL. An independent decoder (ZXing) with no format restriction identifies
CODE_128 and recovers that exact reference. The generated local symbol is also
compared bar-for-bar against that provider sample. This is primary sample evidence,
not a guess based on generic OXXO documentation or an inspected Horsements order.

JsBarcode generates standard Code 128 from the DOM reference string, including
leading zeros. No payment digits, prefix, amount or FNC1 are appended. Standard
Code 128 checksum/start/stop/quiet zones are supplied by the encoder. ZXing
independently decodes the generated barcode canvas before capture, then decodes
the actual encoded PNG after rasterization. Only an exact reference match permits
download. Missing/invalid barcode, canvas, logo, PNG, or failed/mismatched decoding
shows an error and produces no download. No reference-only fallback remains.

Build dependencies (pinned with package-lock):
JsBarcode 3.12.3, html2canvas 1.4.1, @zxing/library 0.21.3, esbuild 0.25.12.
Source: resources/js/ticket/export.js. Build: scripts/build-ticket.cjs.
Third-party licenses are shipped in assets/js/oxxo-ticket.LICENSE.txt.
The release pipeline rebuilds in memory and requires exact bundle equality.
Tests additionally require Playwright, Sharp, and a local Chrome executable.

Repeat full validation/package:
NODE_PATH=/path/to/ticket/dependencies/node_modules:/path/to/test/node_modules SPIN_TEST_SOURCES=/private/tmp/tkila-hook-sources python3 scripts/package-recovery.py
CHROME_BIN can override the default macOS Google Chrome path.

Real browser verification:
- Serve the actual PHP template, CSS and exporter with a local HTTP server.
- Download, decode PNG raster bytes, require exact CODE_128 reference match:
  desktop, touch/mobile emulation, blocked image/font resources, leading zeros,
  and 20-digit references. No network requests during export are permitted.
- Require embedded official local logos, all payment data and instructions, no
  buttons/links/countdown, unchanged original confirmation nodes, and cleaned iframes.
- Reject invalid reference, unavailable canvas, null PNG, blank/corrupted final PNG,
  and a local-logo decode failure; require zero downloads for every rejection.
- Independently decode the provider sample and compare local encoding bar-for-bar.
- Visually inspect the actual full-resolution exported desktop/mobile PNG files.
- Seven original PHP suites, SHA-256 of 313 PHP files (only exact version metadata
  exception), syntax, reproducible bundle and ZIP content validation must pass.

Artifacts: tests/artifacts/ticket-final-*.png and verification logs. Test fixtures,
source build files and test artifacts are not shipped in the plugin ZIP.
No payment code, wallet, webhook, credential or provider query has changed.
No real post-change Horsements download or physical OXXO scanner has been tested
by this session; local software decoding does not establish cashier acceptance.
