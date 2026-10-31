<?php
/**
 * IconPress uninstaller.
 *
 * Removes everything the plugin created: the icon slots table,
 * the store orders table, the settings option, and the version
 * options. Attachment images uploaded for icons stay in the
 * media library, since they are regular uploads the site may
 * use elsewhere.
 *
 * @package IconPress
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$table = $wpdb->prefix . 'iconpress_icons';
$wpdb->query( "DROP TABLE IF EXISTS $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

$orders = $wpdb->prefix . 'iconpress_orders';
$wpdb->query( "DROP TABLE IF EXISTS $orders" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

delete_option( 'iconpress_settings' );
delete_option( 'iconpress_db_version' );
delete_option( 'iconpress_seeded' );
delete_option( 'widget_iconpress_widget' );
