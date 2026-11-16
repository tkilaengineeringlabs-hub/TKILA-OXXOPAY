#!/bin/sh
set -eu
script_directory=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd -P)
plugin_directory=$(dirname -- "$script_directory")
destination=${1:-${SPIN_TEST_SOURCES:-$(php -r 'echo sys_get_temp_dir() . "/spin-oxxopay-test-sources";')}}
mkdir -p "$destination"
destination=$(CDPATH= cd -- "$destination" && pwd -P)
case "$destination/" in
    "$plugin_directory/"*)
        echo "Test dependencies must be outside the plugin directory." >&2
        exit 1
        ;;
esac
wc_source=https://raw.githubusercontent.com/woocommerce/woocommerce/10.3.5/plugins/woocommerce
wp_source=https://raw.githubusercontent.com/WordPress/WordPress/6.8.3/wp-includes
wallet_source=https://raw.githubusercontent.com/malsubrata/woo-wallet/cd1f36e4fdd0c5e0777e548975eb03fda1b4e942/includes
curl -fsSL "$wc_source/includes/class-wc-checkout.php" -o "$destination/class-wc-checkout.php"
curl -fsSL "$wc_source/includes/class-wc-form-handler.php" -o "$destination/class-wc-form-handler.php"
curl -fsSL "$wc_source/src/StoreApi/Routes/V1/Checkout.php" -o "$destination/Checkout.php"
curl -fsSL "$wc_source/src/StoreApi/Routes/V1/CheckoutOrder.php" -o "$destination/CheckoutOrder.php"
curl -fsSL "$wc_source/src/StoreApi/Routes/V1/AbstractCartRoute.php" -o "$destination/AbstractCartRoute.php"
curl -fsSL "$wc_source/src/StoreApi/Utilities/CartController.php" -o "$destination/CartController.php"
curl -fsSL "$wp_source/class-wp-hook.php" -o "$destination/class-wp-hook.php"
curl -fsSL "$wp_source/plugin.php" -o "$destination/wp-plugin.php"
curl -fsSL "$wp_source/rest-api/class-wp-rest-server.php" -o "$destination/wp-rest-server.php"
curl -fsSL "$wallet_source/class-woo-wallet.php" -o "$destination/wallet.php"
curl -fsSL "$wallet_source/class-woo-wallet-wallet.php" -o "$destination/wallet-ops.php"
php "$script_directory/checkout-guard.php" "$destination" --verify-sources-only
printf 'Prepared verified test sources in %s\n' "$destination"
