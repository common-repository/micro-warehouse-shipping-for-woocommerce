<?php
/*
  Plugin Name: Micro Warehouse Shipping for Woocommerce
  Description: Custom shipping plugin developed by Eniture Technology.
  Version: 1.0.7
  Author: Eniture Technology
  Author URI: http://eniture.com/
  Text Domain: eniture-technology
  License: GPL version 2 or later - http://www.eniture.com/
  WC requires at least: 6.4.0
  WC tested up to: 8.9.2
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once 'common/en-micro-warehouse.php';
require_once 'common/product-detail/en-product-detail.php';

add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

if (!function_exists('en_micro_warehouse_admin_script')) {

    function en_micro_warehouse_admin_script()
    {
        wp_register_style('en_micro_warehouse_style', plugin_dir_url(__FILE__) . '/common/css/en-micro-warehouse.css', false, '1.0.1');
        wp_enqueue_style('en_micro_warehouse_style');
    }

    add_action('admin_enqueue_scripts', 'en_micro_warehouse_admin_script');
}
