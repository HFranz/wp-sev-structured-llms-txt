<?php
/**
 * Caches the generated llms.txt content and invalidates it when relevant data changes.
 *
 * @package SevStructuredLlmsTxt
 */

namespace SevStructuredLlmsTxt;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
 * Transient-backed cache for the generated llms.txt content, invalidated
 * whenever a post/page, category, or plugin setting changes.
 */
class Cache {

	private const TRANSIENT_NAME = 'sevllms_cache';

	private const TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * Registers the hooks that invalidate the cache.
	 *
	 * Plugin settings are invalidated separately, in
	 * Admin_Settings::maybe_clear_cache_after_save(), rather than via
	 * update_option_{$option} here: that hook does not fire on a setting's
	 * very first save (WordPress calls add_option() instead in that case),
	 * so relying on it alone would leave a stale cache after the first save.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'save_post_post', array( $this, 'delete' ) );
		add_action( 'save_post_page', array( $this, 'delete' ) );
		add_action( 'delete_post', array( $this, 'delete' ) );
		add_action( 'edited_category', array( $this, 'delete' ) );
		add_action( 'created_category', array( $this, 'delete' ) );
		add_action( 'delete_category', array( $this, 'delete' ) );
	}

	/**
	 * Returns the cached content, or null if there is none.
	 *
	 * @return string|null
	 */
	public function get(): ?string {
		$cached = get_transient( self::TRANSIENT_NAME );

		return is_string( $cached ) ? $cached : null;
	}

	/**
	 * Stores the given content in the cache.
	 *
	 * @param string $content The generated llms.txt content.
	 * @return void
	 */
	public function set( string $content ): void {
		set_transient( self::TRANSIENT_NAME, $content, self::TTL );
	}

	/**
	 * Clears the cache. Accepts and ignores any hook arguments so it can be
	 * used directly as a callback for hooks like save_post.
	 *
	 * @return void
	 */
	public function delete(): void {
		delete_transient( self::TRANSIENT_NAME );
	}
}
