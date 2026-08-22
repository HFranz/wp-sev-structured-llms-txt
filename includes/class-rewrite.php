<?php
/**
 * Serves the generated llms.txt content at the virtual /llms.txt URL.
 *
 * @package SevStructuredLlmsTxt
 */

namespace SevStructuredLlmsTxt;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
 * Registers a rewrite rule for /llms.txt and serves the (cached) generated
 * content for it, without ever writing a physical file to disk.
 */
class Rewrite {

	private const QUERY_VAR = 'sevllms_txt';

	private Generator $generator;

	private Cache $cache;

	public function __construct( ?Generator $generator = null, ?Cache $cache = null ) {
		$this->generator = $generator ?? new Generator();
		$this->cache     = $cache ?? new Cache();
	}

	/**
	 * Registers the rewrite rule, query var, and request handler.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'add_rewrite_rule' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_filter( 'redirect_canonical', array( $this, 'prevent_canonical_redirect' ) );
		add_action( 'template_redirect', array( $this, 'maybe_serve' ) );
	}

	/**
	 * Adds the rewrite rule mapping /llms.txt to our query var.
	 *
	 * @return void
	 */
	public function add_rewrite_rule(): void {
		add_rewrite_rule( '^llms\.txt$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	/**
	 * Registers our query var with WordPress.
	 *
	 * @param string[] $vars Existing public query vars.
	 * @return string[]
	 */
	public function add_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	/**
	 * Stops WordPress from redirecting /llms.txt to /llms.txt/. Without a
	 * recognized file extension, redirect_canonical() otherwise treats our
	 * virtual URL like a page permalink and appends a trailing slash (the
	 * same issue reported for /.well-known/security.txt, see
	 * https://wordpress.org/support/topic/well-known-security-txt-redirects-301-403s-redirect_canonical-fix/).
	 *
	 * @param string|false $redirect_url The redirect URL WordPress computed, or false.
	 * @return string|false
	 */
	public function prevent_canonical_redirect( $redirect_url ) {
		if ( get_query_var( self::QUERY_VAR ) ) {
			return false;
		}

		return $redirect_url;
	}

	/**
	 * Outputs the generated llms.txt content and stops WordPress from
	 * continuing to render a normal page, if the current request is for it.
	 *
	 * @return void
	 */
	public function maybe_serve(): void {
		if ( ! get_query_var( self::QUERY_VAR ) ) {
			return;
		}

		$content = $this->cache->get();

		if ( null === $content ) {
			$content = $this->generator->generate();
			$this->cache->set( $content );
		}

		header( 'Content-Type: text/plain; charset=utf-8' );
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text markdown output, not HTML.
		exit;
	}

	/**
	 * Registers the rewrite rule and flushes rewrite rules for the current
	 * site, so /llms.txt starts working immediately. Intended to be called
	 * once during plugin activation (per site, via switch_to_blog() for
	 * network activation).
	 *
	 * @return void
	 */
	public static function flush_current_site(): void {
		( new self() )->add_rewrite_rule();
		flush_rewrite_rules();
	}
}
