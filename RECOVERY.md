Recovery branch: recovery/v1.0.15-png

Source of all runtime files: exact tkila-oxxo-pay-v1.0.15-staging.zip.
Baseline SHA-256: f632a04acaed6d5abba1d7fc907152b570fa24c076ffb5c1cd8d10f15e60b47c.
The fixture is retained for repeatable comparison of all 313 original PHP files.
Only templates/confirmation.php and the exact Version: 1.0.15 -> 1.0.20 metadata
replacement in spin_checkout.php are authorized to differ among those PHP files.

Runtime changes against ZIP v1.0.15:
- templates/confirmation.php: v1.0.16 status presentation and Google Maps button markup;
  independent download button, separate notice and deferred export script.
- assets/css/oxxo-confirmation.css: v1.0.16 visual alignment and download button styles.
- assets/js/oxxo-ticket.js: new click-only canvas PNG generation. Uses rendered data;
  barcode CORS/image/canvas failure yields a separate notice. No order/API mutations.
- VERSION: 1.0.20, read by the original version.php.

spin_checkout.php differs only in its Version: 1.0.20 header. WordPress plugin
metadata, the runtime constant and the archive version now agree on 1.0.20.
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

PNG repair in 1.0.20:
- Previous exporter performed fetch(barcode.src, {mode: 'cors'}) on click. A displayed
  cross-origin img can load without allowing CORS fetch. Its rejection aborted export.
- Real Chrome reproduced this exact sequence using two local HTTP origins and the
  unchanged 1.0.19 exporter from commit 005698e. Console evidence is retained in
  tests/artifacts/cors-reproduction.txt. This is a local reproduction, not a capture
  of the Horsements browser error or its response headers.
- The exporter now makes zero network requests. It probes the already-loaded img
  in an isolated canvas. An origin-clean image is included; a tainted, missing or
  blocked image falls back to the original numeric reference and an explicit notice
  that the downloaded PNG omits the barcode. No barcode is invented or reconstructed.
- No SVG, external fonts, logo or external service is required by the export.
- Page CSS is identical to 1.0.19; template changes only add ?ver=1.0.20 to the export
  script URL to invalidate the previous cached JS. Canvas typography/layout is retained
  except the guidance text in the unavailable-barcode region.

Validation for real browser tests requires Playwright and local Chrome:
NODE_PATH=/path/to/node_modules SPIN_TEST_SOURCES=/private/tmp/tkila-hook-sources python3 scripts/package-recovery.py
CHROME_BIN may override the default macOS Google Chrome executable.
The browser test serves the actual PHP template, styles and exporter over localhost.
Its barcode image is synthetic test data; no actual FEMSA reference is requested.
It reproduces the old CORS failure and tests four real downloaded PNGs: tainted
cross-origin image, blocked image/font resources, local image, and mobile viewport.
Every file is checked for PNG signature, dimensions (1200x1000), successful browser
image decoding and painted text pixels; requests during export must remain zero.
Reference, barcode DOM, amount, expiry and order markup must remain identical.
The browser test also enforces unchanged page CSS and template markup against 1.0.19.
Mock tests separately cover no canvas, null toBlob, missing/blocked/tainted/safe image.
Seven PHP suites, syntax and all 313 PHP SHA-256 comparisons passed; only the exact
plugin header replacement and the previously authorized visual template are permitted.
The blocked-resource PNG was visually inspected. Generated sample PNGs and CORS
console evidence are stored in tests/artifacts and are not shipped in the plugin ZIP.
No live Horsements checkout or post-fix download has been tested by this session.
