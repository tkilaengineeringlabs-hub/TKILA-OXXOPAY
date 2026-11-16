<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/*
 * Title   : Spin for WooCommerce
 * Author  : Franklin Carrero
 * Url     : https://developers.digitalfemsa.io/docs/woocommerce-1
*/


require_once(__DIR__ . '/vendor/autoload.php');

use DigitalFemsa\Api\OrdersApi;
use DigitalFemsa\ApiException;
use DigitalFemsa\Model\OrderRequest;
use DigitalFemsa\Model\CustomerShippingContacts;

class Spin_Block_Gateway extends Spin_Plugin
{
    protected $GATEWAY_NAME = "Spin_Block_Gateway";
    protected $order = null;
    protected $currencies = array('MXN', 'USD');

    public $id;
    public $method_title;
    public $method_description;
    public $has_fields;
    public $title;
    public $description;
    public $api_key;
    public $webhook_url;

    /**
     * @throws ApiException|Exception
     */
    public function __construct()
    {
        $this->id = 'spin';
        $this->method_title = 'Tkila OXXO PAY';
        $this->method_description = 'Permite a tus clientes pagar compras en línea en efectivo en más de 25,000 tiendas OXXO, con confirmación de pago en tiempo real y actualización automática de las ordenes.';
        $this->has_fields = true;
        $this->spin_init_form_fields();
        $this->init_settings();
        $option_key = 'woocommerce_' . $this->id . '_settings';
        $stored_settings = get_option($option_key, array());
        $stored_settings = is_array($stored_settings) ? $stored_settings : array();
        $fixed_title = 'OXXO PAY';
        $fixed_description = 'Paga tus compras en más de 25,000 tiendas OXXO de México.';
        $changed = false;
        if (!isset($stored_settings['title']) || $stored_settings['title'] !== $fixed_title) {
            $stored_settings['title'] = $fixed_title;
            $changed = true;
        }
        if (!isset($stored_settings['description']) || $stored_settings['description'] !== $fixed_description) {
            $stored_settings['description'] = $fixed_description;
            $changed = true;
        }
        if ($changed) {
            update_option($option_key, $stored_settings);
        }

        $this->title = $fixed_title;
        $this->description = $fixed_description;
        $this->icon        = WP_PLUGIN_URL . "/" . plugin_basename( dirname(__FILE__)) . '/images/oxxopay_b2b.svg';
        $this->api_key = $this->settings['api_key'];
        $this->webhook_url = $this->settings['webhook_url'];

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'spin_thankyou_page'));
        add_action('woocommerce_api_wc_spin', [$this, 'check_for_webhook']);

        if (!$this->spin_validate_currency()) {
            $this->enabled = false;
        }

        if (empty($this->api_key)) {
            $this->enabled = false;
        }
        if ($this->enabled) {
            self::create_webhook( $this->api_key, $this->webhook_url);
        }

    }
    public function get_gateway_name(): string {
        return $this->GATEWAY_NAME;
    }
    public function get_icon() {
        if ( is_admin() ) {
            return parent::get_icon();
        }
        $icon_url = WP_PLUGIN_URL . "/" . plugin_basename( dirname(__FILE__)) . '/images/oxxopay_b2c.svg';
        $icon_html = '<img src="' . esc_url( $icon_url ) . '" alt="' . esc_attr( $this->title ) . '" />';
        return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
    }
    /**
     * @throws ApiException
     */
    public function check_for_webhook()
    {
        if (!isset($_SERVER['REQUEST_METHOD'])
            || ('POST' !== $_SERVER['REQUEST_METHOD'])
            || !isset($_GET['wc-api'])
            || ('wc_spin' !== $_GET['wc-api'])
        ) {
            header('Content-Type: application/json');
            echo wp_json_encode(['skipped' => 'OK', 'server' => false]);
            exit;
        }

        $event = json_decode(file_get_contents("php://input"), true);

        switch ($event['type']) {
            case 'webhook_ping':
                self::handleWebhookPing();
                exit;

            case 'order.paid':
                self::check_if_payment_payment_method_webhook($this->GATEWAY_NAME, $event);
                self::handleOrderPaid($this->get_api_instance(), $event);
                exit;

            case 'order.expired':
            case 'order.canceled':
                self::check_if_payment_payment_method_webhook($this->GATEWAY_NAME, $event);
                self::handleOrderExpiredOrCanceled($this->get_api_instance(),$event);
                exit;
            default:
                http_response_code(202);
                header('Content-Type: application/json');
                echo wp_json_encode(['unknown' => true, 'event' => $event['type']]);
                exit;
        }
    }


    public function get_api_instance(): OrdersApi
    {
        $integrationParams = [
            'integration_type' => 'plugin',
            'integration_name' => 'spin-woocommerce',
            'plugin_version' => $this->version,
            'platform_version' => WC()->version,
            'device_type' => wp_is_mobile() ? 'mobile' : 'desktop'
        ];

        return new OrdersApi(
            null,
            self::get_spin_instance($this->settings['api_key']),
            new class($integrationParams) extends \DigitalFemsa\HeaderSelector {
                private $integrationParams;
                public function __construct(array $integrationParams) { $this->integrationParams = $integrationParams; }
                public function getFemsaUserAgent(): array {
                    $headers = parent::getFemsaUserAgent();
                    $existing = isset($headers['Spin-Client-User-Agent']) ? (string)$headers['Spin-Client-User-Agent'] : '';

                    $parsed = [];
                    if ($existing !== '') {
                        foreach (explode(';', $existing) as $part) {
                            $part = trim($part);
                            if ($part === '') {
                                continue;
                            }
                            $kv = explode('=', $part, 2);
                            if (count($kv) === 2) {
                                $parsed[trim($kv[0])] = trim($kv[1]);
                            }
                        }
                    }

                    $merged = array_merge($parsed, $this->integrationParams);
                    $parts = [];
                    foreach ($merged as $k => $v) {
                        $parts[] = $k . '=' . $v;
                    }
                    $headers['Spin-Client-User-Agent'] = implode(';', $parts);
                    return $headers;
                }
            }
        );
    }
    /**
     * Output for the order received page.
     * @param string $order_id
     * @throws ApiException
     */
    function spin_thankyou_page($order_id)
    {
        $order = new WC_Order($order_id);
        $spin_order_id = get_post_meta($order->get_id(), 'spin-order-id', true);

        if (empty($spin_order_id)) {
            return;
        }
        $spin_order = $this->get_api_instance()->getOrderById($spin_order_id);

        foreach ($spin_order->getCharges()->getData() as $charge) {
            $payment_method = $charge->getPaymentMethod()->getObject();
            if ($payment_method == 'cash_payment') {
                $reference = $charge->getPaymentMethod()->getReference();
                $barcode_url = $charge->getPaymentMethod()->getBarcodeUrl();
                echo '<p style="font-size: 30px"><strong>' . esc_html__('Referencia', 'Spin') . ':</strong> ' . esc_html($reference) . '</p>';
                echo '<p><img src="' . esc_url($barcode_url) . '" alt="Código QR" style="border: 2px solid #000; margin: 10px; box-shadow: 3px 3px 10px rgba(0, 0, 0, 0.2);"></p>';
                echo '<p>Se cobrará una comisión adicional al momento de realizar el pago.</p>';
            }
        }
    }

    public function spin_init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'type' => 'checkbox',
                'title' => __('Habilitar/Deshabilitar', 'Spin'),
                'label' => __('Habilitar Tkila OXXO PAY', 'Spin'),
                'default' => 'yes'
            ),
            'title' => array(
                'type' => 'text',
                'title' => __('Título', 'Spin'),
                'description' => '',
                'default' => 'OXXO PAY',
                'required' => true,
                'custom_attributes' => array(
                    'readonly' => 'readonly',
                    'disabled' => 'disabled'
                )
            ),
            'description' => array(
                'type' => 'text',
                'title' => __('Descripción', 'Spin'),
                'description' => '',
                'default' => 'Paga tus compras en más de 25,000 tiendas OXXO de México.',
                'required' => true,
                'custom_attributes' => array(
                    'readonly' => 'readonly',
                    'disabled' => 'disabled'
                )
            ),
            'api_key' => array(
                'type' => 'password',
                'title' => 'Spin API key',
                'description' => __('API Key Producción (Tokens/Llaves Privadas)', 'Spin'),
                'required' => true
            ),
            'order_expiration' => array(
                'type' => 'number',
                'title' => __('Vencimiento de las órdenes de pago (Días)', 'Spin'),
                'description' => __('La cantidad de dīas configuradas en esta opción, corresponde al tiempo en el que la orden estará activa para ser pagada por el cliente desde el momento de su creación.', 'Spin'),
                'default' => 1,
                'custom_attributes' => array(
                    'min' => 1,
                    'max' => 30,
                    'step' => 1
                ),
            ),
            'webhook_url' => array(
                'type' => 'text',
                'title' => 'URL webhook',
                'description' => 'URL webhook',
                'default' => get_site_url() . '/?wc-api=wc_spin',
                'required' => true
            ),
        );

    }


    protected function spin_mark_as_failed_payment($order)
    {
        $order->add_order_note(
            sprintf(
                "%s digital femsa Payment Failed",
                $this->GATEWAY_NAME,
            )
        );
    }

    /**
     * @throws Exception
     */
    public function process_payment($order_id)
    {
        global $woocommerce;
        $order = new WC_Order($order_id);
        try {
            $data = spin_get_request_data($order);
            $items = $order->get_items();
            $line_items = spin_build_line_items($items, parent::spin_get_version());
            $discount_lines = spin_build_discount_lines($data);
            $shipping_lines = spin_build_shipping_lines($data);
            $shipping_contact = spin_build_shipping_contact($data);
            $fees = $order->get_fees();
            $fees_data = spin_build_get_fees($fees, parent::spin_get_version());
            $line_items = array_merge($line_items, $fees_data);
            $taxes = $order->get_taxes();
            $tax_lines = spin_build_tax_lines($taxes);
            spin_validate_breakdown($order, $line_items, $discount_lines, $tax_lines, $shipping_lines);
        } catch (DomainException $exception) {
            wc_get_logger()->error('OXXO PAY amount validation: ' . $exception->getMessage(), array(
                'source' => 'spin',
                'order_id' => $order->get_id(),
            ));
            wc_add_notice('No se pudo validar el importe pendiente para OXXO PAY. No se generó una referencia.', 'error');
            return array('result' => 'failure');
        }
        $customer_info = spin_build_customer_info($data);
        $order_metadata = spin_build_order_metadata($data + array(
                'plugin_spin_version' => $this->version,
                'woocommerce_version' => $woocommerce->version,
                'payment_method' => $this->GATEWAY_NAME,
            )
        );
        $rq = new OrderRequest([
            'currency' => $data['currency'],
            'charges' => [
                [
                    'payment_method' => [
                        'type' => 'cash',
                        'expires_at' => spin_get_expired_at($this->settings['order_expiration']),
                    ],
                    'reference_id' => strval($order->get_id()),
                ]
            ],
            'shipping_lines' => $shipping_lines,
            'discount_lines' => $discount_lines,
            'tax_lines' => $tax_lines,
            'customer_info' => $customer_info,
            'line_items' => $line_items,
            'metadata' => $order_metadata
        ]);
        if (!empty($shipping_contact)) {
            $rq->setShippingContact(new CustomerShippingContacts($shipping_contact));
        }
        try {
            $orderCreated = $this->get_api_instance()->createOrder($rq);
            update_post_meta($order->get_id(), 'spin-order-id', $orderCreated->getId());
            $order->update_status('on-hold', __('Awaiting the digital femsa cash payment', 'Spin'));
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );
        } catch (Exception $e) {
            $description = $e->getMessage();
            wc_add_notice('Error:' . $description, 'error');
            $this->spin_mark_as_failed_payment($order);
            WC()->session->reload_checkout = true;
            throw new Exception( $description  );
        }

    }

    /**
     * Checks if woocommerce has enabled available currencies for plugin
     *
     * @access public
     * @return bool
     */
    public function spin_validate_currency(): bool
    {
        return in_array(get_woocommerce_currency(), $this->currencies);
    }
}

function spin_add_gateway($methods)
{
    // Crear una instancia de la clase
    $gateway = new Spin_Block_Gateway();

    $methods[] = $gateway->get_gateway_name();
    return $methods;
}

add_filter('woocommerce_payment_gateways', 'spin_add_gateway');

add_action('woocommerce_blocks_loaded', 'woocommerce_gateway_spin_woocommerce_block_support');
function woocommerce_gateway_spin_woocommerce_block_support()
{
    if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        require_once 'includes/blocks/spin_blocks_support.php';
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                $payment_method_registry->register(new Spin_Blocks_Support());
            }
        );
    }
}
