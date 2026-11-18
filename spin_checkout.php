<?php

if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/version.php';

/*
Plugin Name: Tkila OXXO PAY
Plugin URI: https://github.com/tkilaengineeringlabs-hub/TKILA-OXXOPAY
Description: Pasarela OXXO PAY para WooCommerce, personalizada por Tkila Intelligence Labs.
Version: 1.0.19
Author: Tkila Intelligence Labs
Author URI: https://github.com/tkilaengineeringlabs-hub
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

/*
 * Title   : Spin for WooCommerce
 * Author  : DigitalFemsa.io
 * Url     : https://developers.digitalfemsa.io/docs/woocommerce-1
 */

function spin_checkout_init_your_gateway()
{
    if (class_exists('WC_Payment_Gateway'))
    {
        include_once('spin_gateway_helper.php');
        include_once('spin_wallet_split.php');
        include_once('spin_wallet_guard.php');
        include_once('spin_plugin.php');
        include_once('spin_block_gateway.php');
    }
}

add_action('plugins_loaded', 'spin_checkout_init_your_gateway', 0);

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'spin_action_links' );


function spin_action_links($links): array
{
    $plugin_links = [
        '<a href="admin.php?page=wc-settings&tab=checkout&section=spin">' . esc_html__( 'Settings', 'Spin_Block_Gateway') . '</a>',
    ];
    return array_merge( $plugin_links, $links );
}
