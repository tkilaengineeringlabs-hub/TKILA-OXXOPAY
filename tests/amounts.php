<?php

define('ABSPATH', __DIR__);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../spin_gateway_helper.php';

function sanitize_text_field($value) { return $value; }
function esc_html($value) { return $value; }
function add_action(...$arguments) {}
function add_filter(...$arguments) {}
function wc_add_notice(...$arguments) {}
function __($message, $domain) { return $message; }
function update_post_meta($id, $key, $value) { $GLOBALS['order_meta'][$id][$key] = $value; }
function wc_get_logger() { return new class {
    public function error($message, $context) { $GLOBALS['logs'][] = [$message, $context]; }
}; }

class WC_Payment_Gateway {
    public array $settings = ['order_expiration' => 1];
    public function get_return_url($order) { return 'https://example.invalid/order/123'; }
}
class WC_Product {
    public function __construct($id) {}
    public function get_sku() { return ''; }
}
class TestFee {
    public function __construct(private $total, private $tax = '0', private $wallet = false) {}
    public function get_total() { return $this->total; }
    public function get_total_tax() { return $this->tax; }
    public function get_meta($key) {
        return $this->wallet && $key === '_legacy_fee_key' ? '_via_wallet_partial_payment' : '';
    }
}
class TestCoupon {
    public function get_code() { return 'TEST'; }
    public function get_discount() { return '10.00'; }
}
class WC_Order {
    public static array $fixture = [];
    public static array $mutations = [];
    public function __construct($id) {}
    public function get_id() { return 123; }
    public function get_items($type = '') {
        return $type === 'coupon' ? (self::$fixture['coupons'] ?? []) : (self::$fixture['items'] ?? [
            ['line_subtotal' => '100.00', 'qty' => 1, 'product_id' => 1, 'name' => 'Test'],
        ]);
    }
    public function get_fees() { return self::$fixture['fees'] ?? []; }
    public function get_taxes() { return self::$fixture['taxes'] ?? []; }
    public function get_total() { return self::$fixture['total'] ?? '100.00'; }
    public function get_currency() { return 'MXN'; }
    public function get_shipping_total() { return self::$fixture['shipping'] ?? '0'; }
    public function get_shipping_method() { return isset(self::$fixture['shipping']) ? 'Test shipping' : ''; }
    public function is_paid() { return self::$fixture['paid'] ?? false; }
    public function update_status($status, $note) { self::$mutations[] = [$status, $note]; }
    public function __call($name, $arguments) {
        if (str_starts_with($name, 'get_')) { return ''; }
        throw new RuntimeException('Unexpected order mutation: ' . $name);
    }
}
require __DIR__ . '/../spin_plugin.php';
require __DIR__ . '/../spin_block_gateway.php';

class TestGateway extends Spin_Block_Gateway {
    public int $api_calls = 0;
    public function __construct() {}
    public function get_api_instance(): DigitalFemsa\Api\OrdersApi {
        $this->api_calls++;
        throw new RuntimeException('Network forbidden');
    }
}

class CapturingGateway extends Spin_Block_Gateway {
    private DigitalFemsa\Api\OrdersApi $api;
    public array $history = [];
    public function __construct() {
        $handler = GuzzleHttp\HandlerStack::create(new GuzzleHttp\Handler\MockHandler([
            new GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/json'], '{"id":"ord_test_only","payment_status":"pending_payment"}'),
        ]));
        $handler->push(GuzzleHttp\Middleware::history($this->history));
        $config = new DigitalFemsa\Configuration();
        $config->setHost('https://example.invalid');
        $this->api = new DigitalFemsa\Api\OrdersApi(new GuzzleHttp\Client(['handler' => $handler]), $config);
    }
    public function get_api_instance(): DigitalFemsa\Api\OrdersApi { return $this->api; }
}

function payload_total(array $payload): int {
    $total = 0;
    foreach ($payload['line_items'] as $line) {
        verify(is_int($line['unit_price']) && $line['unit_price'] >= 0, 'wire product price');
        $total += $line['unit_price'] * $line['quantity'];
    }
    foreach (['tax_lines' => 1, 'shipping_lines' => 1, 'discount_lines' => -1] as $field => $sign) {
        foreach ($payload[$field] as $line) {
            verify(is_int($line['amount']) && $line['amount'] >= 0, 'wire adjustment amount');
            $total += $sign * $line['amount'];
        }
    }
    return $total;
}

function verify($condition, $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}

$cases = [
    'normal' => [[], null],
    'coupon' => [['coupons' => [new TestCoupon()], 'total' => '90'], null],
    'wallet partial blocked' => [['fees' => [new TestFee('-20')], 'total' => '80'], 'unsupported_negative_fee'],
    'wallet and coupon blocked' => [['coupons' => [new TestCoupon()], 'fees' => [new TestFee('-20')], 'total' => '70'], 'unsupported_negative_fee'],
    'multiple fees' => [['fees' => [new TestFee('5'), new TestFee('7')], 'total' => '112'], null],
    'shipping rounding' => [['shipping' => '0.29', 'total' => '100.29'], null],
    'tax and shipping' => [['shipping' => '10', 'taxes' => [['tax_amount' => '16', 'shipping_tax_amount' => '1.60', 'label' => 'IVA']], 'total' => '127.60'], null],
    'coupon tax' => [['coupons' => [new TestCoupon()], 'taxes' => [['tax_amount' => '14.40', 'label' => 'IVA']], 'total' => '104.40'], null],
    'quantity remainder' => [['items' => [['line_subtotal' => '10', 'qty' => 3, 'product_id' => 1, 'name' => 'Test']], 'total' => '10'], null],
    'zero remaining' => [['total' => '0'], 'no_pending_payment'],
    'wallet covers all blocked' => [['fees' => [new TestFee('-100')], 'total' => '0'], 'unsupported_negative_fee'],
    'mismatch' => [['total' => '99.99'], 'amount_mismatch'],
    'external total reduction' => [['total' => '80'], 'amount_mismatch'],
    'negative taxes' => [['taxes' => [['tax_amount' => '-1', 'label' => 'IVA']], 'total' => '99'], 'invalid_adjustment_amount'],
    'fractional quantity' => [['items' => [['line_subtotal' => '100', 'qty' => 1.5, 'product_id' => 1, 'name' => 'Test']]], 'invalid_product_amount'],
    'already paid' => [['paid' => true], 'no_pending_payment'],
    'invalid total' => [['total' => 'not money'], 'invalid_money'],
    'wallet marked persisted fee' => [['fees' => [new TestFee('-20', '0', true)], 'total' => '80'], 'unsupported_negative_fee'],
    'wallet tiny negative fee' => [['fees' => [new TestFee('-0.001', '0', true)], 'total' => '100'], 'unsupported_negative_fee'],
    'wallet taxed coupon shipping fees quantities' => [[
        'items' => [['line_subtotal' => '100', 'qty' => 3, 'product_id' => 1, 'name' => 'Test']],
        'coupons' => [new TestCoupon()],
        'fees' => [new TestFee('5'), new TestFee('-20', '-3.20', true), new TestFee('7')],
        'shipping' => '10',
        'taxes' => [['tax_amount' => '13.12', 'shipping_tax_amount' => '1.60', 'label' => 'IVA']],
        'total' => '106.72',
    ], 'unsupported_negative_fee'],
    'combined without wallet' => [[
        'items' => [['line_subtotal' => '100', 'qty' => 3, 'product_id' => 1, 'name' => 'Test']],
        'coupons' => [new TestCoupon()],
        'fees' => [new TestFee('5'), new TestFee('7')],
        'shipping' => '10',
        'taxes' => [['tax_amount' => '16.32', 'shipping_tax_amount' => '1.60', 'label' => 'IVA']],
        'total' => '129.92',
    ], null],
];

$GLOBALS['woocommerce'] = (object) ['version' => 'test'];
foreach ($cases as $name => [$fixture, $expected_error]) {
    WC_Order::$fixture = $fixture;
    WC_Order::$mutations = [];
    $GLOBALS['order_meta'] = [];
    $order = new WC_Order(123);
    $actual_error = null;
    try {
        $data = spin_get_request_data($order);
        $lines = array_merge(spin_build_line_items($order->get_items(), '1.0.12'), spin_build_get_fees($order->get_fees(), '1.0.12'));
        $discounts = spin_build_discount_lines($data);
        $taxes = spin_build_tax_lines($order->get_taxes());
        $shipping = spin_build_shipping_lines($data);
        spin_validate_breakdown($order, $lines, $discounts, $taxes, $shipping);
        foreach ($lines as $line) {
            verify((new DigitalFemsa\Model\Product($line))->valid(), $name . ': SDK product');
        }
        foreach ($discounts as $discount) {
            verify((new DigitalFemsa\Model\OrderDiscountLinesRequest($discount))->valid(), $name . ': SDK discount');
        }
        $request = new DigitalFemsa\Model\OrderRequest(['currency' => 'MXN', 'line_items' => $lines, 'discount_lines' => $discounts, 'tax_lines' => $taxes, 'shipping_lines' => $shipping]);
        $serialized = json_decode(json_encode(DigitalFemsa\ObjectSerializer::sanitizeForSerialization($request)), true);
        verify($serialized['line_items'][0]['unit_price'] >= 0, $name . ': serialization');
    } catch (DomainException $exception) {
        $actual_error = $exception->getMessage();
    }
    verify($actual_error === $expected_error, $name . ': unexpected result ' . ($actual_error ?? 'success'));
    if ($expected_error !== null) {
        $gateway = new TestGateway();
        verify($gateway->process_payment(123) === ['result' => 'failure'], $name . ': gateway must fail');
        verify($gateway->api_calls === 0, $name . ': must not call API');
        verify(WC_Order::$mutations === [] && $GLOBALS['order_meta'] === [], $name . ': no order mutation');
        verify(end($GLOBALS['logs'])[0] === 'OXXO PAY amount validation: ' . $expected_error, $name . ': safe logging');
    } else {
        $gateway = new CapturingGateway();
        verify($gateway->process_payment(123)['result'] === 'success', $name . ': simulated creation');
        verify(count($gateway->history) === 1, $name . ': one simulated request');
        $payload = json_decode((string) $gateway->history[0]['request']->getBody(), true);
        verify(payload_total($payload) === spin_amount_validation($order->get_total()), $name . ': wire total');
        verify($payload['charges'][0]['payment_method']['type'] === 'cash', $name . ': cash request');
        verify(WC_Order::$mutations[0][0] === 'on-hold', $name . ': remains unpaid');
    }
    echo 'PASS ', $name, PHP_EOL;
}
verify(spin_amount_validation('0.29') === 29, 'cent conversion');
verify(spin_amount_validation('1.005') === 101, 'half up');
verify(spin_amount_validation('-1.005') === -101, 'negative half up');
verify(count(spin_build_get_fees([new TestFee(5), new TestFee(7), new TestFee(9)], '1.0.12')) === 3, 'no duplicated fees');
echo 'PASS monetary conversion and fee count', PHP_EOL;

foreach (['wallet partial blocked', 'wallet and coupon blocked', 'wallet covers all blocked', 'wallet tiny negative fee', 'wallet taxed coupon shipping fees quantities'] as $name) {
    WC_Order::$fixture = $cases[$name][0];
    WC_Order::$mutations = [];
    $GLOBALS['order_meta'] = [123 => ['spin-order-id' => 'ord_existing_test']];
    $before = serialize(WC_Order::$fixture);
    $gateway = new TestGateway();
    for ($attempt = 0; $attempt < 3; $attempt++) {
        verify($gateway->process_payment(123) === ['result' => 'failure'], $name . ': retry blocked');
    }
    verify($gateway->api_calls === 0, $name . ': retry never calls API');
    verify(serialize(WC_Order::$fixture) === $before, $name . ': totals/fees unchanged');
    verify(WC_Order::$mutations === [], $name . ': retry no status change');
    verify($GLOBALS['order_meta'][123]['spin-order-id'] === 'ord_existing_test', $name . ': keeps existing reference');
    echo 'PASS retries ', $name, PHP_EOL;
}
