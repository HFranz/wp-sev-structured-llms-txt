<?php
/**
 * Asks for a WordPress.org review after the plugin has been in use for a while.
 *
 * @package SevStructuredLlmsTxt
 */

namespace SevStructuredLlmsTxt;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
 * Shows a dismissible admin notice asking for a review, once a site owner
 * has had a real chance to use the plugin. Shows once, until dismissed.
 */
class Review_Notice {

	private const OPTION_FIRST_ACTIVATED_AT = 'sevllms_first_activated_at';

	private const OPTION_DISMISSED = 'sevllms_review_notice_dismissed';

	private const DISMISS_QUERY_ARG = 'sevllms-dismiss-review-notice';

	private const NONCE_ACTION = 'sevllms_dismiss_review_notice';

	/**
	 * Delay after first activation before the notice starts showing, so only
	 * site owners who have had a real chance to use the plugin see it.
	 */
	private const DELAY = 14 * DAY_IN_SECONDS;

	/**
	 * Registers WordPress hooks. Call once during plugins_loaded.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'maybe_set_first_activation_time' ) );
		add_action( 'admin_notices', array( $this, 'maybe_render' ) );
		add_action( 'admin_init', array( $this, 'maybe_dismiss' ) );
	}

	/**
	 * Records the first time this plugin was seen active, if not already
	 * recorded. Hooked into 'init' as a fallback for record_first_activation()
	 * below: register_activation_hook() does not fire when an already-active
	 * plugin is merely updated (WordPress keeps it active throughout, it only
	 * swaps files in place), so without this, every pre-existing installation
	 * updating to the version that introduced this option would never get it
	 * set, and would therefore never see the review notice at all.
	 *
	 * @return void
	 */
	public function maybe_set_first_activation_time(): void {
		self::record_first_activation();
	}

	/**
	 * Records the first time this plugin was activated, if not already
	 * recorded. Intended to be called from register_activation_hook(), for
	 * the most immediate/common case: a fresh install or reactivation.
	 *
	 * @return void
	 */
	public static function record_first_activation(): void {
		if ( false === get_option( self::OPTION_FIRST_ACTIVATED_AT, false ) ) {
			add_option( self::OPTION_FIRST_ACTIVATED_AT, time() );
		}
	}

	/**
	 * Prints the review-request notice, once the delay has elapsed and it
	 * hasn't been dismissed yet.
	 *
	 * @return void
	 */
	public function maybe_render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( 'yes' === get_option( self::OPTION_DISMISSED, 'no' ) ) {
			return;
		}

		$first_activated_at = (int) get_option( self::OPTION_FIRST_ACTIVATED_AT, 0 );

		if ( ! $first_activated_at || ( time() - $first_activated_at ) < self::DELAY ) {
			return;
		}

		$dismiss_url = add_query_arg(
			array(
				self::DISMISS_QUERY_ARG => '1',
				'_wpnonce'              => wp_create_nonce( self::NONCE_ACTION ),
			)
		);

		printf(
			'<div class="notice notice-info"><p>%s</p><p><a href="%s" class="button button-primary" style="margin-right: 10px;" target="_blank" rel="noopener noreferrer">%s</a> <a href="%s">%s</a></p></div>',
			esc_html__( 'Enjoying Structured llms.txt? A quick review helps other site owners find it.', 'sev-structured-llms-txt' ),
			esc_url( 'https://wordpress.org/support/plugin/sev-structured-llms-txt/reviews/#new-post' ),
			esc_html__( 'Leave a review', 'sev-structured-llms-txt' ),
			esc_url( $dismiss_url ),
			esc_html__( 'Dismiss', 'sev-structured-llms-txt' )
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All interpolated values are escaped above.
	}

	/**
	 * Handles the "Dismiss" link on the review-request notice above. Runs on
	 * admin_init, before any output, so the option is updated and the
	 * redirect can still happen before maybe_render() would otherwise print
	 * the notice again on the same request.
	 *
	 * @return void
	 */
	public function maybe_dismiss(): void {
		if ( ! isset( $_GET[ self::DISMISS_QUERY_ARG ], $_GET['_wpnonce'] ) ) {
			return;
		}

		$nonce = sanitize_key( wp_unslash( $_GET['_wpnonce'] ) );

		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		update_option( self::OPTION_DISMISSED, 'yes' );

		wp_safe_redirect( remove_query_arg( array( self::DISMISS_QUERY_ARG, '_wpnonce' ) ) );
		exit;
	}
}
