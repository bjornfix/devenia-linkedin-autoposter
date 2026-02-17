<?php
/**
 * Uninstall script for Devenia Autoposter for LinkedIn.
 *
 * This file is executed when the plugin is deleted through the WordPress admin.
 * It removes all plugin data from the database.
 *
 * @package Devenia_Autoposter_For_LinkedIn
 */

// Exit if accessed directly or not uninstalling.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete plugin options.
delete_option( 'dlap_settings' );
delete_option( 'dlap_access_token' );
delete_option( 'dlap_token_expires' );
delete_option( 'dlap_member_id' );
delete_option( 'dlap_organizations' );
delete_option( 'dlap_last_expiry_email' );
delete_option( 'dlap_gallery_rotation_index' );

// Clear scheduled events.
wp_clear_scheduled_hook( 'dlap_daily_check' );

// Optionally delete post meta (uncomment if desired).
// global $wpdb;
// $wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_dlap_disable' ) );
// $wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_dlap_shared' ) );
// $wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_dlap_post_id' ) );
// $wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_dlap_error' ) );
// $wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_dlap_error_personal' ) );
// $wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_dlap_error_organization' ) );
