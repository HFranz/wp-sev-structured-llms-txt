<?php
/**
 * Plugin Name: SEV Structured llms.txt
 * Plugin URI: https://github.com/HFranz/wp-sev-structured-llms-txt
 * Description: Generates a structured llms.txt at /llms.txt, listing pages, posts, and (if WooCommerce is active) products, grouped by category, so AI assistants and LLMs can discover your site's content. Works on single sites and WordPress Multisite, network-wide or per site.
 * Version: 1.2.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Heinrich Franz
 * Author URI: https://sevmatic.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sev-structured-llms-txt
 * Domain Path: /languages
 *
 * php version 8.0
 *
 * @package SevStructuredLlmsTxt
 */

use SevStructuredLlmsTxt\Admin_Settings;
use SevStructuredLlmsTxt\Cache;
use SevStructuredLlmsTxt\Post_Meta;
use SevStructuredLlmsTxt\Rewrite;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

const SEVLLMS_VERSION = '1.2.0';

require_once plugin_dir_path( __FILE__ ) . 'includes/class-description-resolver.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-category-order.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-product-category-order.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-noindex-resolver.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-content-selector.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-alternate-sites.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-generator.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-cache.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-rewrite.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-post-meta.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-admin-settings.php';

add_action(
	'plugins_loaded',
	static function () {
		$rewrite = new Rewrite();
		$rewrite->register();

		( new Cache() )->register();
		( new Post_Meta() )->register();

		if ( is_admin() ) {
			( new Admin_Settings() )->register();
		}
	}
);

/**
 * Registers the rewrite rule and flushes rewrite rules so /llms.txt works immediately.
 *
 * @param bool $network_wide Whether the plugin is being network-activated.
 * @return void
 */
function sevllms_activate( bool $network_wide ): void {
	if ( is_multisite() && $network_wide ) {
		foreach ( get_sites( array( 'fields' => 'ids' ) ) as $site_id ) {
			switch_to_blog( (int) $site_id );
			Rewrite::flush_current_site();
			restore_current_blog();
		}
		return;
	}

	Rewrite::flush_current_site();
}
register_activation_hook( __FILE__, 'sevllms_activate' );

/**
 * Flushes rewrite rules on the newly created site so /llms.txt works there too,
 * for networks where this plugin is active network-wide.
 *
 * @param \WP_Site $new_site The newly created site.
 * @return void
 */
function sevllms_on_new_site( \WP_Site $new_site ): void {
	if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	if ( ! is_plugin_active_for_network( plugin_basename( __FILE__ ) ) ) {
		return;
	}

	switch_to_blog( (int) $new_site->blog_id );
	Rewrite::flush_current_site();
	restore_current_blog();
}
add_action( 'wp_initialize_site', 'sevllms_on_new_site', 100 );

/**
 * Cleans up rewrite rules on deactivation.
 *
 * @return void
 */
function sevllms_deactivate(): void {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'sevllms_deactivate' );
