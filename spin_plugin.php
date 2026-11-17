<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/*
* Title   : Spin for WooCommerce
* Author  : DigitalFemsa.io
* Url     : https://developers.digitalfemsa.io/docs/woocommerce-1
*/

use DigitalFemsa\Api\WebhooksApi;
use DigitalFemsa\Configuration;
use DigitalFemsa\Model\WebhookRequest;
use DigitalFemsa\Api\OrdersApi;
use DigitalFemsa\ApiException;

class Spin_Plugin extends WC_Payment_Gateway
{
	public $version  = "1.0.12";

    public $name = "Tkila OXXO PAY";

	public $description = "Pasarela OXXO PAY para WooCommerce, personalizada por Tkila Intelligence Labs";
	public $plugin_name = "Tkila OXXO PAY";
	public $plugin_URI = "https://github.com/tkilaengineeringlabs-hub/TKILA-OXXOPAY";
	public $author = "Tkila Intelligence Labs";
	public $author_URI = "https://github.com/tkilaengineeringlabs-hub";

	public function spin_get_version()
	{
		return $this->version;
	}

    /**
     * Plugin url.
     *
     * @return string
     */
    public static function plugin_url() {
        return untrailingslashit( plugins_url( '/', __FILE__ ) );
    }

    /**
     * Plugin url.
     *
     * @return string
     */
    public static function plugin_abspath() {
        return trailingslashit( plugin_dir_path( __FILE__ ) );
    }

    public static function get_spin_instance($apikey)
    {
        return Configuration::getDefaultConfiguration()
            ->setAccessToken($apikey);
    }

    /**
     * @throws Exception
     */
    public static function create_webhook(?string $apikey, ?string $webhook_url): bool
    {
        if (is_null($webhook_url)){
            return false;
        }
        try {
            $api = new WebhooksApi(null, self::get_spin_instance($apikey));
            $webhooks = $api->getWebhooks("es", null,3,null, $webhook_url);
            if (empty($webhooks->getData())) {
                $webhook = new WebhookRequest();
                $webhook->setUrl($webhook_url);
                $api->createWebhook($webhook);
            }
            return true;
        }
        catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }
    public static function handleWebhookPing()
    {
        header('Content-Type: application/json');
        echo wp_json_encode(['message' => 'OK']);
        exit;
    }
    public static function check_if_payment_payment_method_webhook(string $payment_method, array $event){
        $method = $event['data']['object']['metadata']['payment_method'] ?? null;
        if (!is_string($method) || $method === '') {
            http_response_code(400);
            header('Content-Type: application/json');
            echo wp_json_encode(array('error' => 'Missing payment method'));
            exit;
        }
        if ($method !== $payment_method) {
            header('Content-Type: application/json');
            echo wp_json_encode(array('message' => 'Ignored payment method'));
            exit;
        }
    }
    /**
     * Resolve the order only when the event is linked to a reference that was
     * actually created for this WooCommerce order and payment gateway.
     * Never trust metadata.reference_id alone to mutate an order.
     *
     * @return WC_Order|false
     */
    public static function resolve_webhook_order(array $spin_order)
    {
        $remote_id = $spin_order['id'] ?? null;
        $reference = $spin_order['metadata']['reference_id'] ?? null;
        $method = $spin_order['metadata']['payment_method'] ?? null;

        if (!is_string($remote_id) || $remote_id === '' ||
            (!is_string($reference) && !is_int($reference)) ||
            !preg_match('/^[1-9][0-9]*$/D', (string) $reference) ||
            (string) (int) $reference !== (string) $reference ||
            $method !== 'Spin_Block_Gateway') {
            return false;
        }

        $order = wc_get_order((int) $reference);
        if (!$order || $order->get_payment_method() !== 'spin') {
            return false;
        }

        $stored_id = $order->get_meta('spin-order-id', true);
        if (!is_scalar($stored_id) || (string) $stored_id === '' ||
            !hash_equals((string) $stored_id, $remote_id)) {
            return false;
        }

        return $order;
    }

    private static function webhook_response(int $status, array $body): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo wp_json_encode($body);
        exit;
    }

    /**
     * Only a verified paid status from the provider may complete the order.
     */
    public static function handleOrderPaid(OrdersApi $ordersApi, array $event)
    {
        $spin_order = $event['data']['object'] ?? array();
        $order = is_array($spin_order) ? self::resolve_webhook_order($spin_order) : false;
        if (!$order) {
            self::webhook_response(400, array('error' => 'Order/reference mismatch'));
        }

        try {
            $paid = self::check_order_status($ordersApi, $spin_order['id'], array('paid'));
        } catch (Exception $exception) {
            self::webhook_response(503, array('error' => 'Payment status unavailable'));
        }
        if (!$paid) {
            self::webhook_response(409, array('error' => 'Payment not confirmed'));
        }

        if ($order->get_meta('spin-payment-mode', true) === 'wallet_remainder_collection') {
            $expected_cents = $order->get_meta('spin-split-pending-cents', true);
            $charge = $spin_order['charges']['data'][0] ?? array();
            $collected_cents = $charge['amount'] ?? null;
            if (!$order->get_meta('_partial_pay_through_wallet_compleate', true) ||
                !is_numeric($expected_cents) || !is_numeric($collected_cents) ||
                (int) $expected_cents <= 0 ||
                (int) $expected_cents !== (int) $collected_cents) {
                self::webhook_response(409, array('error' => 'Wallet/remote amount reconciliation failed'));
            }
        }

        if ($order->is_paid()) {
            self::webhook_response(200, array('message' => 'Already paid', 'order_id' => $order->get_id()));
        }

        // A cancelled/refunded order needs manual reconciliation, not a silent revival.
        if (in_array($order->get_status(), array('cancelled', 'refunded'), true)) {
            self::webhook_response(409, array('error' => 'Order requires reconciliation'));
        }

        $paid_at = $spin_order['charges']['data'][0]['paid_at'] ?? null;
        if (is_numeric($paid_at) && (int) $paid_at > 0) {
            $order->update_meta_data('spin-paid-at', gmdate('Y-m-d', (int) $paid_at));
        }
        $order->payment_complete();
        $order->add_order_note('Pago confirmado por OXXO PAY.');
        $order->save();
        self::webhook_response(200, array('message' => 'OK', 'order_id' => $order->get_id()));
    }

    /**
     * Ignore stale expiration/cancellation after an order is already paid.
     */
    public static function handleOrderExpiredOrCanceled(OrdersApi $ordersApi, $event)
    {
        $spin_order = $event['data']['object'] ?? array();
        $order = is_array($spin_order) ? self::resolve_webhook_order($spin_order) : false;
        if (!$order) {
            self::webhook_response(400, array('error' => 'Order/reference mismatch'));
        }

        try {
            $closed = self::check_order_status($ordersApi, $spin_order['id'], array('expired', 'canceled'));
        } catch (Exception $exception) {
            self::webhook_response(503, array('error' => 'Payment status unavailable'));
        }
        if (!$closed) {
            self::webhook_response(409, array('error' => 'Provider status mismatch'));
        }
        if ($order->is_paid() || in_array($order->get_status(), array('cancelled', 'refunded'), true)) {
            self::webhook_response(200, array('message' => 'No change', 'order_id' => $order->get_id()));
        }

        $order->update_status('cancelled', 'Referencia OXXO PAY vencida o cancelada.');
        self::webhook_response(200, array('message' => 'Cancelled', 'order_id' => $order->get_id()));
    }
    /**
     * @throws ApiException
     */
    public static function check_order_status(OrdersApi $ordersApi, string $spin_order_id, array $statuses): bool
    {
        $spin_order_api = $ordersApi->getOrderById($spin_order_id);

        return in_array($spin_order_api->getPaymentStatus(), $statuses);
    }
    public static function validate_reference_id(array $spin_order): bool
    {
        return isset($spin_order['metadata']) && array_key_exists('reference_id', $spin_order['metadata']);
    }
}
