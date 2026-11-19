<?php
define('ABSPATH', __DIR__);
class WC_Payment_Gateway {}
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../spin_plugin.php';

function env_check($ok, $message) {
    if (!$ok) { fwrite(STDERR, "FAIL $message\n"); exit(1); }
    echo "PASS $message\n";
}

$sandbox = Spin_Plugin::get_spin_instance('key_test_placeholder', 'test');
$production = Spin_Plugin::get_spin_instance('key_live_placeholder', 'production');
$unknown = Spin_Plugin::get_spin_instance('key_test_placeholder', 'unexpected');
class EnvironmentResponse { public function __construct(private $live) {} public function getLivemode() { return $this->live; } }
env_check($sandbox->getHost() === 'https://api.digitalfemsa.io', 'sandbox selects documented Direct API host');
env_check($sandbox->getAccessToken() === 'key_test_placeholder', 'sandbox preserves only supplied sandbox credential');
env_check($production->getHost() === 'https://api.digitalfemsa.io', 'production uses documented Direct API host');
env_check($unknown->getHost() === 'https://api.digitalfemsa.io', 'unknown mode retains shared host without changing key mode');
env_check(!Spin_Plugin::is_environment_response_allowed(new EnvironmentResponse(true), 'test'), 'sandbox rejects live-mode response');
env_check(Spin_Plugin::is_environment_response_allowed(new EnvironmentResponse(false), 'test'), 'sandbox accepts test-mode response');
env_check(!Spin_Plugin::is_environment_response_allowed(new class { public function getLivemode() { return null; } }, 'test'), 'sandbox rejects missing or indeterminate livemode');
