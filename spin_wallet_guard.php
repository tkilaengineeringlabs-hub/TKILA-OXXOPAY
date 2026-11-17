<?php

if ( ! defined( 'ABSPATH' ) ) exit;
require_once(__DIR__ . '/spin_wallet_split.php');

final class Spin_Wallet_Guard
{
    private static array $requests = array();

    public static function register(): void
    {
        add_action('woocommerce_after_checkout_validation', array(__CLASS__, 'validate_classic'), 10, 2);
        add_filter('rest_request_before_callbacks', array(__CLASS__, 'before_rest'), 10, 3);
        add_filter('rest_dispatch_request', array(__CLASS__, 'dispatch_rest'), 10, 4);
        add_filter('rest_request_after_callbacks', array(__CLASS__, 'after_rest'), 10, 3);
        add_action('woocommerce_store_api_cart_errors', array(__CLASS__, 'validate_blocks_cart'), 10, 2);
        add_action('woocommerce_before_pay_action', array(__CLASS__, 'validate_order_pay'), -1000);
        add_action('woocommerce_checkout_order_processed', array(__CLASS__, 'validate_processed'), -1000, 3);
        add_action('woocommerce_store_api_checkout_order_processed', array(__CLASS__, 'validate_processed'), -1000);
    }

    public static function message(): string
    {
        return __('OXXO PAY todavía no admite pagos parciales con billetera ni ajustes negativos. Desactiva el pago parcial o elige otro método de pago.', 'Spin');
    }

    private static function staging_split_allowed(): bool
    {
        if (!Tkila_Oxxo_Wallet_Split::enabled() || !function_exists('woo_wallet')) {
            return false;
        }
        $wallet = woo_wallet();
        if (!isset($wallet->settings_api)) {
            return false;
        }
        // In staging, require a debit at order placement. TeraWallet owns
        // cancellation refunds; the gateway never directly moves wallet funds.
        return $wallet->settings_api->get_option(
            'partial_payment_debit_on', '_wallet_settings_general', 'order_created'
        ) === 'order_created';
    }

    private static function wallet_active(): bool
    {
        return function_exists('woo_wallet') || class_exists('Woo_Wallet');
    }

    private static function order_blocked($order): bool
    {
        if (self::staging_split_allowed()) {
            try {
                Tkila_Oxxo_Wallet_Split::plan($order);
                return false;
            } catch (DomainException $exception) {
                return true;
            }
        }
        foreach ($order->get_fees() as $fee) {
            $amount = $fee->get_total();
            if (!is_numeric($amount) || (float) $amount < 0 || (float) $fee->get_total_tax() < 0) {
                return true;
            }
        }
        return (bool) $order->get_meta('_partial_pay_through_wallet_compleate', true);
    }

    private static function cart_blocked($cart): bool
    {
        if (!$cart) {
            return self::wallet_active();
        }
        if (self::staging_split_allowed()) {
            $negative = 0;
            foreach ($cart->get_fees() as $fee) {
                if (!isset($fee->amount) || !is_numeric($fee->amount)) {
                    return true;
                }
                if ((float) $fee->amount < 0) {
                    if (($fee->id ?? '') !== '_via_wallet_partial_payment' || ++$negative > 1) {
                        return true;
                    }
                }
            }
            return false;
        }
        foreach ($cart->get_fees() as $fee) {
            if (!isset($fee->amount) || !is_numeric($fee->amount) || (float) $fee->amount < 0) {
                return true;
            }
        }
        if (!self::wallet_active()) {
            return false;
        }
        if (!WC()->session) {
            return true;
        }
        $requested = WC()->session->get('partial_payment_amount', 0);
        if (is_numeric($requested) && (float) $requested > 0) {
            return true;
        }
        if (!function_exists('is_enable_wallet_partial_payment')) {
            return true;
        }
        return (bool) is_enable_wallet_partial_payment();
    }

    public static function validate_classic($data, $errors): void
    {
        if (($data['payment_method'] ?? '') === 'spin' && self::cart_blocked(WC()->cart)) {
            $errors->add('spin_wallet_combination_unavailable', self::message());
        }
    }

    private static function checkout_route($request): bool
    {
        return (bool) preg_match('#^/wc/store/v[0-9]+/checkout(?:/[0-9]+)?/?$#D', $request->get_route());
    }

    private static function request_method($request): string
    {
        $method = $request->get_param('payment_method');
        if ($method !== null) {
            return is_string($method) ? $method : '';
        }
        return WC()->session ? (string) WC()->session->get('chosen_payment_method', '') : '';
    }

    public static function before_rest($response, $handler, $request)
    {
        self::$requests[] = $request;
        return $response;
    }

    public static function dispatch_rest($response, $request, $route, $handler)
    {
        if ($response !== null || !self::checkout_route($request)) {
            return $response;
        }
        if (preg_match('#/checkout/([0-9]+)/?$#D', $request->get_route(), $matches)) {
            $order = wc_get_order((int) $matches[1]);
            $method = $request->get_param('payment_method') ?? ($order ? $order->get_payment_method() : '');
            if ($method === 'spin' && $order && self::order_blocked($order)) {
                return new WP_Error('spin_wallet_combination_unavailable', self::message(), array('status' => 400));
            }
        } elseif (self::request_method($request) === 'spin' && self::wallet_active() &&
            (!defined('WC_VERSION') || version_compare(WC_VERSION, '7.2', '<') ||
                !str_starts_with($request->get_route(), '/wc/store/v1/') ||
                $request->get_method() !== 'POST')) {
            return new WP_Error('spin_wallet_combination_unavailable', self::message(), array('status' => 400));
        }
        return $response;
    }

    public static function after_rest($response, $handler, $request)
    {
        self::$requests = array_values(array_filter(self::$requests, static function ($active) use ($request) {
            return $active !== $request;
        }));
        return $response;
    }

    public static function validate_blocks_cart($errors, $cart): void
    {
        $request = end(self::$requests);
        if ($request && self::checkout_route($request) && self::request_method($request) === 'spin' && self::cart_blocked($cart)) {
            $errors->add('spin_wallet_combination_unavailable', self::message());
        }
    }

    public static function validate_order_pay($order): void
    {
        $method = isset($_POST['payment_method']) ? wp_unslash($_POST['payment_method']) : $order->get_payment_method();
        if ($method === 'spin' && self::order_blocked($order)) {
            wp_die(esc_html(self::message()), esc_html__('Pago no disponible', 'Spin'), array('response' => 400));
        }
    }

    public static function validate_processed($order, $data = array(), $created_order = null): void
    {
        $order = $created_order ?: (is_object($order) ? $order : wc_get_order($order));
        if ($order && ($data['payment_method'] ?? $order->get_payment_method()) === 'spin' && self::order_blocked($order)) {
            throw new Exception(self::message());
        }
    }
}

Spin_Wallet_Guard::register();
