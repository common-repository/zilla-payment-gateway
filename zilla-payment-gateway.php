<?php

/**
 * Plugin Name: Zilla Payment Gateway
 * Plugin URI: https://wordpress.org/plugins/zilla-payment-gateway
 * Author: Zilla
 * Author URI: https://zilla.africa/
 * Description: Integrate zilla payment gateway with woocommerce
 * Version: 1.5.3
 * License: 1.5.3
 * License URL: http://www.gnu.org/licenses/gpl-2.0.txt
 * text-domain: zilla-payment-gateway
 */

if (!defined('ABSPATH')) {
	exit;
}

define('WC_ZILLA_MAIN_FILE', __FILE__);
define('WC_ZILLA_URL', untrailingslashit(plugins_url('/', __FILE__)));

define('WC_ZILLA_VERSION', '1.5.2');

function zilla_payment_init()
{

	load_plugin_textdomain('zilla-payment-gateway', false, plugin_basename(dirname(__FILE__)) . '/languages');

	if (!class_exists('WC_Payment_Gateway')) {
		add_action('admin_notices', 'zilla_payment_wc_missing_notice');
		return;
	}

	add_action('admin_notices', 'zilla_payment_testmode_notice');

	require_once dirname(__FILE__) . '/include/class-wc-zilla-gateway.php';
	require_once dirname(__FILE__) . '/include/actions.php';
	require_once dirname(__FILE__) . '/include/checkout-customise.php';

	add_filter('woocommerce_payment_gateways', 'zilla_wc_add_gateway', 99);

	add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'zilla_payment_plugin_action_links');
}
add_action('plugins_loaded', 'zilla_payment_init', 99);

/**
 * Add Settings link to the plugin entry in the plugins menu.
 *
 * @param array $links Plugin action links.
 *
 * @return array
 **/
function zilla_payment_plugin_action_links($links)
{

	$settings_link = array(
		'settings' => '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=zilla') . '" title="' . __('View Zilla WooCommerce Settings', 'zilla-payment-gateway') . '">' . __('Settings', 'zilla-payment-gateway') . '</a>',
	);

	return array_merge($settings_link, $links);
}

/**
 * Add Zilla Gateway to WooCommerce.
 *
 * @param array $methods WooCommerce payment gateways methods.
 *
 * @return array
 */
function zilla_wc_add_gateway($methods)
{

	if (class_exists("WC_Gateway_Zilla")) {
		$methods[] = 'WC_Gateway_Zilla';
	}
	return $methods;
}

/**
 * Display a notice if WooCommerce is not installed
 */
function zilla_payment_wc_missing_notice()
{
	echo '<div class="error"><p><strong>' . sprintf(__('Zilla requires WooCommerce to be installed and active. Click %s to install WooCommerce.', 'zilla-payment-gateway'), '<a href="' . admin_url('plugin-install.php?tab=plugin-information&plugin=woocommerce&TB_iframe=true&width=772&height=539') . '" class="thickbox open-plugin-details-modal">here</a>') . '</strong></p></div>';
}

/**
 * Display the test mode notice.
 **/
function zilla_payment_testmode_notice()
{

	if (!current_user_can('manage_options')) {
		return;
	}

	$zilla_settings = get_option('woocommerce_zilla_settings');
	$test_mode         = isset($zilla_settings['testmode']) ? $zilla_settings['testmode'] : '';

	if ('yes' === $test_mode) {
		echo '<div class="error"><p>' . sprintf(__('Zilla test mode is still enabled, Click <strong><a href="%s">here</a></strong> to disable it when you want to start accepting live payment on your site.', 'zilla-payment-gateway'), esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=zilla'))) . '</p></div>';
	}
}
