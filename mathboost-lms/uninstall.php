<?php
// Only run when WordPress itself triggers uninstall
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Drop all plugin-owned tables
$tables = [
    'mb_qcm_categories',
    'mb_qcms',
    'mb_categories',
    'mb_levels',
    'mb_activation_codes',
    'mb_sessions',
    'mb_error_reports',
    'mb_user_progress',
];

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Delete all plugin options
$options = [
    'mb_migration_v2_done',
    'mb_paypal_client_id',
    'mb_paypal_secret',
    'mb_price',
    'mb_currency',
    'mb_max_sessions',
    'mb_free_locked_count',
    'mb_premium_duration',
    'mb_email_contact',
    'mb_payment_page_url',
    'mb_login_page_url',
    'mb_register_page_url',
    'mb_db_version',
];

foreach ( $options as $option ) {
    delete_option( $option );
}

// Delete user meta added by this plugin
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ('mb_premium','mb_premium_expires','mb_last_paypal_order','mb_payment_date')" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
