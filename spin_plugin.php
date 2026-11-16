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

    public $name = "WooCommerce 2";

	public $description = "Spin for Woocommerce for cash payments";
	public $plugin_name = "Spin for Woocommerce";
	public $plugin_URI = "https://wordpress.org/plugins/digitalfemsa-payment-gateway/";
	public $author = "DigitalFemsa.io";
	public $author_URI = "https://www.digitalfemsa.io";

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
        $spin_order = $event['data']['object'];
        if ($spin_order["metadata"]["payment_method"] !==  $payment_method){
            header('Content-Type: application/json');
            echo wp_json_encode(['message' => 'OK', 'payment_method'=> $spin_order["metadata"]["payment_method"]]);
            exit;
        }
    }
    /**
     * @throws ApiException
     */
    public static function handleOrderPaid(OrdersApi $ordersApi, array $event)
    {
        $spin_order = $event['data']['object'];
        if (!self::validate_reference_id($spin_order)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo wp_json_encode(['error' => 'Invalid order id']);
            exit;
        }
        $order_id = $spin_order['metadata']['reference_id'];

        if (!self::check_order_status($ordersApi, $spin_order['id'], array('paid'))) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo wp_json_encode(['error' => 'Invalid order status', 'order_id' => $order_id]);
            exit;
        }

        $order = new WC_Order($order_id);
        $charge = $spin_order['charges']['data'][0];
        $paid_at = gmdate("Y-m-d", $charge['paid_at']);
        update_post_meta($order->get_id(), 'spin-paid-at', $paid_at);
        $order->payment_complete();
        $order->add_order_note("Payment completed in Spin and notification of payment received");

        header('Content-Type: application/json');
        echo wp_json_encode(['message' => 'OK', 'order_id' => $order_id]);
        exit;
    }
    /**
     * @throws ApiException
     */
    public static function handleOrderExpiredOrCanceled(OrdersApi $ordersApi, $event)
    {
        $spin_order = $event['data']['object'];
        if (!self::validate_reference_id($spin_order)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo wp_json_encode(['error' => 'Invalid order id']);
            exit;
        }
        $order_id = $spin_order['metadata']['reference_id'];
        if (!self::check_order_status($ordersApi, $spin_order['id'], array('expired', 'canceled'))) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo wp_json_encode(['error' => 'Invalid order status', 'order_id' => $order_id]);
            exit;
        }
        $order = new WC_Order($order_id);
        $order->update_status('cancelled', 'Order expired/cancelled in DigitalFemsa.');
        header('Content-Type: application/json');
        echo wp_json_encode(['cancelled' => 'OK', 'order_id' => $order_id]);
        exit;
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
