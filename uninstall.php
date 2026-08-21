<?php
/**
 * Uninstall routine for SEV Structured llms.txt.
 *
 * Called automatically by WordPress when the plugin is deleted via the admin UI.
 * Removes the plugin's own options and post meta, on every site of a Multisite
 * network if applicable.
 *
 * @package SevStructuredLlmsTxt
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die();
}

/**
 * Removes the plugin's options and post meta from the current site.
 *
 * @return void
 */
function sevllms_uninstall_current_site(): void {
	delete_option( 'sevllms_tagline' );
	delete_option( 'sevllms_category_order' );
	delete_option( 'sevllms_alternate_sites' );
	delete_transient( 'sevllms_cache' );

	global $wpdb;
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_sevllms_exclude' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-off cleanup on uninstall, not a runtime query.
}

/**
 * Cleans up the current site, or every site on a Multisite network.
 *
 * @return void
 */
function sevllms_uninstall(): void {
	if ( ! is_multisite() ) {
		sevllms_uninstall_current_site();
		return;
	}

	$site_ids = get_sites( array( 'fields' => 'ids' ) );

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		sevllms_uninstall_current_site();
		restore_current_blog();
	}
}
sevllms_uninstall();
