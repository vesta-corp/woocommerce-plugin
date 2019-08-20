<?php
 /**
  * Removing Plugin data using uninstall.php
  * the below function clears the database table on uninstall
  * only loads this file when uninstalling a plugin.
  *
  * @author : Vesta Cotporation
  */

// if uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

$option_name = 'woocommerce_wc_vesta_payment_settings';

delete_option($option_name);

// for site options in Multisite.
delete_site_option($option_name);

// drop a vesta payment log table from database.
global $wpdb;
$vesta_payment_log_table = $wpdb->prefix . 'vesta_payment_logs';
$wpdb->query("DROP TABLE IF EXISTS {$vesta_payment_log_table}");

