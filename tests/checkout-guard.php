<?php

define('ABSPATH', __DIR__);
define('WC_VERSION', '10.3.5');
$source_dir = $argv[1] ?? (getenv('SPIN_TEST_SOURCES') ?: sys_get_temp_dir() . '/spin-oxxopay-test-sources');
$hashes = [
    'class-wc-checkout.php' => '2dffab4519544bbe6d469b1f3281458fd0641cc7b7d7af7f3180e6cb6b3f5e21',
    'class-wc-form-handler.php' => 'addf8cdfeffe10fe5cd397058748da8dc6f732280522aa3a6a9a752db0c34e52',
    'Checkout.php' => 'a7d9c453396c615833690fa01a0bbb2ba8bf80b678e4dc5ba45823ccbc7ee00c',
    'CheckoutOrder.php' => 'f4245329ec96176b4d3dcb6bad310bb5ea9393f608cefe0d11a8bf0b1cbf13e9',
    'AbstractCartRoute.php' => '87e560f14305714e153b1eb50ccc13c13362d5f9b6db75759d2cebe93a222564',
    'CartController.php' => 'fa37377c351f9f95c757ba6bdb273de6952d77a408e4f08dca2703c016d8f166',
    'class-wp-hook.php' => '711375e96ed5b31d2435e079602168c4d15032e4b7bd96271c89eac039600766',
    'wp-plugin.php' => '87a819241f339b4702b8e986dd2bd9f817755bae61d4631a1db11fdc34149c31',
    'wp-rest-server.php' => 'd76f51f1c63eaef78535e62d187ac9a3eaccfb070308713e784814d1363e898b',
    'wallet.php' => '4e1ce1f8a29d3bc0ea4ae138423f95e3ca266c77cc89d15e0a1a3f0c6368b243',
    'wallet-ops.php' => 'c72b5b804adac29a239dd38f22378903cc7d17225ecb6efd6e78a39f76c4e9e9',
];
function check($condition, $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}
foreach ($hashes as $file => $hash) {
    check(is_file($source_dir . '/' . $file), 'Missing ' . $source_dir . '/' . $file . '. Run: sh tests/fetch-hook-sources.sh (or pass the prepared directory as the first argument).');
    check(hash_file('sha256', $source_dir . '/' . $file) === $hash, 'Source mismatch: ' . $file);
    $sources[$file] = file_get_contents($source_dir . '/' . $file);
}
if (($argv[2] ?? '') === '--verify-sources-only') {
    echo "PASS all 11 pinned source SHA-256 checks\n";
    exit(0);
}
function method_source($source, $name): string {
    $tokens = token_get_all($source);
    $found = false;
    $depth = 0;
    $body = '';
    foreach ($tokens as $index => $token) {
        if (!$found && is_array($token) && $token[0] === T_FUNCTION) {
            $next = $index + 1;
            while (is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE) { $next++; }
            $found = is_array($tokens[$next]) && $tokens[$next][1] === $name;
        }
        if (!$found) { continue; }
        $text = is_array($token) ? $token[1] : $token;
        $body .= $text;
        if ($token === '{' || (is_array($token) && $token[0] === T_CURLY_OPEN)) { $depth++; }
        if ($token === '}' && --$depth === 0) { return $body; }
    }
    throw new RuntimeException('Missing source method ' . $name);
}
function in_order($source, array $needles): void {
    $offset = 0;
    foreach ($needles as $needle) {
        $position = strpos($source, $needle, $offset);
        check($position !== false, 'Source order contract failed: ' . $needle);
        $offset = $position + strlen($needle);
    }
}
in_order(method_source($sources['class-wc-checkout.php'], 'validate_checkout'), ["do_action( 'woocommerce_after_checkout_validation'"]);
in_order(method_source($sources['class-wc-checkout.php'], 'process_checkout'), [
    '$this->validate_checkout( $posted_data, $errors )', "wc_add_notice( \$message, 'error'", "0 === wc_notice_count( 'error' )", '$this->create_order( $posted_data )', "do_action( 'woocommerce_checkout_order_processed'",
]);
in_order(method_source($sources['Checkout.php'], 'get_response'), ['$this->load_cart_session( $request )', '$this->get_response_by_request_method( $request )']);
in_order(method_source($sources['Checkout.php'], 'get_route_post_response'), ['$this->cart_controller->calculate_totals()', '$this->cart_controller->validate_cart()', '$this->create_or_update_draft_order( $request )', "do_action( 'woocommerce_store_api_checkout_order_processed'"]);
in_order(method_source($sources['CartController.php'], 'validate_cart'), ["do_action( 'woocommerce_store_api_cart_errors'", '$cart_errors->has_errors()', 'throw new InvalidCartException']);
in_order(method_source($sources['class-wc-form-handler.php'], 'pay_action'), ["wp_verify_nonce( \$nonce_value, 'woocommerce-pay' )", 'hash_equals(', "do_action( 'woocommerce_before_pay_action'", '$order->set_payment_method', '$order->save()', '$payment_method->process_payment']);
in_order(method_source($sources['wp-rest-server.php'], 'respond_to_request'), ["'rest_request_before_callbacks'", "\$handler['permission_callback']", "'rest_dispatch_request'", "\$response = call_user_func( \$handler['callback']", "'rest_request_after_callbacks'"]);
foreach (['woocommerce_checkout_order_processed', 'woocommerce_store_api_checkout_order_processed'] as $hook) {
    check(str_contains($sources['wallet.php'], "add_action( '$hook', array( \$this->wallet, 'woocommerce_order_processed' ), 99 )"), 'TeraWallet debit registration changed');
}
in_order(method_source($sources['wallet-ops.php'], 'woocommerce_order_processed'), ["'partial_payment_debit_on'", '$this->debit_partial_payment_for_order( $order )']);
echo "PASS pinned upstream source order contracts\n";

require $source_dir . '/wp-plugin.php';

class WP_Error {
    public array $errors = [];
    public function __construct($code = '', $message = '', $data = []) { if ($code) { $this->add($code, $message); } }
    public function add($code, $message) { $this->errors[$code][] = $message; }
    public function has_errors() { return (bool) $this->errors; }
}
class Woo_Wallet {}
class GuardRequest {
    public function __construct(private $route, private $method = 'POST', private $payment = 'spin') {}
    public function get_route() { return $this->route; }
    public function get_method() { return $this->method; }
    public function get_param($key) { return $key === 'payment_method' ? $this->payment : null; }
}
class GuardCart {
    public array $fees = [];
    public function get_fees() { return $this->fees; }
}
class GuardSession {
    public array $values = [];
    public function get($key, $default = '') { return $this->values[$key] ?? $default; }
}
class GuardFee {
    public function __construct(private $amount, private $tax = 0) {}
    public function get_total() { return $this->amount; }
    public function get_total_tax() { return $this->tax; }
}
class GuardOrder {
    public array $fees = [];
    public string $method = 'spin';
    public bool $debited = false;
    public function get_fees() { return $this->fees; }
    public function get_payment_method() { return $this->method; }
    public function get_meta($key, $single) { return $this->debited; }
}
class GuardStopped extends RuntimeException {}
function __($value, $domain = '') { return $value; }
function esc_html($value) { return $value; }
function esc_html__($value, $domain) { return $value; }
function wp_unslash($value) { return $value; }
function wp_die(...$args) { throw new GuardStopped('stopped'); }
function WC() { return $GLOBALS['wc']; }
function wc_get_order($id) { return $GLOBALS['order']; }
function is_enable_wallet_partial_payment() { return $GLOBALS['auto_partial']; }

require __DIR__ . '/../spin_wallet_guard.php';
$debits = 0;
$created = 0;
foreach (['woocommerce_checkout_order_processed', 'woocommerce_store_api_checkout_order_processed'] as $hook) {
    add_action($hook, function () use (&$debits) { $debits++; }, 99);
}
function reset_checkout(): void {
    $GLOBALS['wc'] = (object) ['cart' => new GuardCart(), 'session' => new GuardSession()];
    $GLOBALS['auto_partial'] = false;
    $GLOBALS['order'] = new GuardOrder();
    $GLOBALS['debits'] = 0;
    $GLOBALS['created'] = 0;
    $_POST = [];
}
function classic($method): bool {
    $errors = new WP_Error();
    do_action('woocommerce_after_checkout_validation', ['payment_method' => $method], $errors);
    if ($errors->has_errors()) { return false; }
    $GLOBALS['created']++;
    $GLOBALS['order']->method = $method;
    do_action('woocommerce_checkout_order_processed', 1, ['payment_method' => $method], $GLOBALS['order']);
    return true;
}
function blocks($request, $late_cart = null): bool {
    $response = apply_filters('rest_request_before_callbacks', null, [], $request);
    try {
        $response = apply_filters('rest_dispatch_request', $response, $request, $request->get_route(), []);
        if ($response instanceof WP_Error) { return false; }
        if ($late_cart) { WC()->cart = $late_cart; }
        $errors = new WP_Error();
        do_action('woocommerce_store_api_cart_errors', $errors, WC()->cart);
        if ($errors->has_errors()) { return false; }
        $GLOBALS['created']++;
        $GLOBALS['order']->method = $request->get_param('payment_method') ?? 'spin';
        do_action('woocommerce_store_api_checkout_order_processed', $GLOBALS['order']);
        return true;
    } finally {
        apply_filters('rest_request_after_callbacks', $response, [], $request);
    }
}
foreach (['fee', 'session', 'auto', 'missing_cart', 'missing_session', 'subcent'] as $mode) {
    foreach (['classic', 'blocks'] as $flow) {
        reset_checkout();
        if ($mode === 'fee' || $mode === 'subcent') { WC()->cart->fees = [(object) ['amount' => $mode === 'fee' ? -20 : -0.001]]; }
        if ($mode === 'session') { WC()->session->values['partial_payment_amount'] = 20; }
        if ($mode === 'auto') { $auto_partial = true; }
        if ($mode === 'missing_cart') { WC()->cart = null; }
        if ($mode === 'missing_session') { WC()->session = null; }
        $result = $flow === 'classic' ? classic('spin') : blocks(new GuardRequest('/wc/store/v1/checkout'));
        check(!$result && $created === 0 && $debits === 0, "$flow $mode must stop before order/debit");
        echo "PASS $flow $mode before order/debit\n";
    }
}
foreach (['classic', 'blocks'] as $flow) {
    foreach (['cod', 'woo_wallet', 'spin'] as $method) {
        reset_checkout();
        if ($method !== 'spin') { WC()->cart->fees = [(object) ['amount' => -20]]; }
        check($flow === 'classic' ? classic($method) : blocks(new GuardRequest('/wc/store/v1/checkout', 'POST', $method)), "$flow $method must pass");
        check($created === 1 && $debits === 1, "$flow $method normal lifecycle");
        echo "PASS $flow $method unaffected\n";
    }
}
reset_checkout();
$loaded_cart = new GuardCart();
$loaded_cart->fees = [(object) ['amount' => -20]];
check(!blocks(new GuardRequest('/wc/store/v1/checkout'), $loaded_cart) && $debits === 0 && $created === 0, 'cart token session loaded after REST preflight');
echo "PASS Blocks uses loaded cart, not preflight cart\n";
foreach (['PATCH', 'GET'] as $verb) {
    reset_checkout();
    check(!blocks(new GuardRequest('/wc/store/v1/checkout', $verb)) && $created === 0 && $debits === 0, 'unsupported draft flow');
    echo "PASS conservative $verb fallback\n";
}
reset_checkout();
check(!blocks(new GuardRequest('/wc/store/v2/checkout')) && $debits === 0, 'unsupported API version');
echo "PASS unsupported API fallback\n";
foreach (['fee', 'tax', 'debited'] as $mode) {
    reset_checkout();
    if ($mode === 'fee') { $order->fees = [new GuardFee(-20)]; }
    if ($mode === 'tax') { $order->fees = [new GuardFee(0, -3.2)]; }
    if ($mode === 'debited') { $order->debited = true; }
    $_POST['payment_method'] = 'spin';
    try { do_action('woocommerce_before_pay_action', $order); throw new RuntimeException('order pay not stopped'); }
    catch (GuardStopped $exception) {}
    check(!blocks(new GuardRequest('/wc/store/v1/checkout/123')) && $debits === 0 && $created === 0, 'existing Blocks order stopped');
    foreach (['woocommerce_checkout_order_processed', 'woocommerce_store_api_checkout_order_processed'] as $hook) {
        try { do_action($hook, $order); throw new RuntimeException('processed not stopped'); }
        catch (Exception $exception) { check($exception->getMessage() === Spin_Wallet_Guard::message(), 'expected guard exception'); }
        check($debits === 0, 'guard priority must precede TeraWallet priority 99');
    }
    $_POST['payment_method'] = 'cod';
    do_action('woocommerce_before_pay_action', $order);
    check(blocks(new GuardRequest('/wc/store/v1/checkout/123', 'POST', 'cod')), 'existing order other method unaffected');
    echo "PASS existing order and pre-debit barrier $mode\n";
}
reset_checkout();
WC()->session->values['chosen_payment_method'] = 'spin';
WC()->cart->fees = [(object) ['amount' => -20]];
check(!blocks(new GuardRequest('/wc/store/v1/checkout', 'POST', null)), 'session fallback');
$errors = new WP_Error();
do_action('woocommerce_store_api_cart_errors', $errors, WC()->cart);
check(!$errors->has_errors(), 'REST context must not leak to next request');
echo "PASS omitted method and REST context cleanup\n";
