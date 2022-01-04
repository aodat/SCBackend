<?php
/*
Plugin Name:  Shipcash
Plugin URI:   https://app-dev.shipcash.net/
Description:  A short little description of the plugin. It will be displayed on the Plugins page in WordPress admin area. 
Version:      1.0
Author:       Shipcash 
Author URI:   https://app-dev.shipcash.net/
License:      GPL2
License URI:  https://app-dev.shipcash.net/
Text Domain:  wpb-tutorial
Domain Path:  /languages

*/

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))))
    return;

add_action('plugins_loaded', 'shipcach_payment_init', 11);
function shipcach_payment_init()
{
    if (class_exists('WC_Payment_Gateway')) {

        include_once dirname(__FILE__) . '/includes/shipcash-payment.php';

        include_once dirname(__FILE__) . '/includes/class-shipcach-pay.php';
    }
}

add_filter('woocommerce_payment_gateways', 'add_to_woo_shipcach_payment_gateway');
function add_to_woo_shipcach_payment_gateway($gateways)
{
    $gateways[] = 'Shipcach_pay_Gateway';
    return $gateways;
}
