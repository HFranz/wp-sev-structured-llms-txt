<?php /** @noinspection PhpUnused */

declare( strict_types=1 );

/*
 * Minimal WordPress stubs for PHPUnit tests without a full WP bootstrap.
 * Only the functions/classes used by the plugin (and exercised by the tests)
 * are provided. Admin-UI classes (Admin_Settings, Post_Meta, Rewrite, Cache)
 * touch WP APIs (meta boxes, rewrite rules, headers/exit, transients) that
 * aren't meaningfully unit-testable here and are intentionally not covered.
 * See AGENTS.md.
 */

define( 'ABSPATH', dirname( __DIR__, 3 ) . '/' );

// ---------------------------------------------------------------------------
// Simple filter system
// ---------------------------------------------------------------------------

$GLOBALS['wp_filter'] = array();

function add_filter( string $tag, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
	$GLOBALS['wp_filter'][ $tag ][ $priority ][] = $callback;
	return true;
}

function apply_filters( string $tag, mixed $value, mixed ...$extra ): mixed {
	if ( empty( $GLOBALS['wp_filter'][ $tag ] ) ) {
		return $value;
	}
	ksort( $GLOBALS['wp_filter'][ $tag ] );
	foreach ( $GLOBALS['wp_filter'][ $tag ] as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$value = $callback( $value, ...$extra );
		}
	}
	return $value;
}

function add_action( string $tag, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
	return true;
}

// ---------------------------------------------------------------------------
// i18n stub
// ---------------------------------------------------------------------------

function __( string $text, string $domain = 'default' ): string { // phpcs:ignore WordPress.WP.I18n
	return $text;
}

// ---------------------------------------------------------------------------
// Content helpers
// ---------------------------------------------------------------------------

function wp_strip_all_tags( string $text ): string {
	return trim( strip_tags( $text ) );
}

function strip_shortcodes( string $content ): string {
	return preg_replace( '/\[[^\]]+\]/', '', $content );
}

function wp_trim_words( string $text, int $num_words = 55 ): string {
	$words = preg_split( '/\s+/', trim( $text ) );
	$words = array_filter( $words, static fn ( $word ) => '' !== $word );

	if ( count( $words ) <= $num_words ) {
		return trim( $text );
	}

	return implode( ' ', array_slice( $words, 0, $num_words ) ) . '…';
}

// ---------------------------------------------------------------------------
// Fake post/term value objects
// ---------------------------------------------------------------------------

class WP_Post {
	public int $ID = 0;
	public string $post_title = '';
	public string $post_name = '';
	public string $post_excerpt = '';
	public string $post_content = '';
	public string $post_date = '';
	public int $menu_order = 0;
	public string $post_type = 'post';
	public string $post_status = 'publish';

	public function __construct( array $props = array() ) {
		foreach ( $props as $key => $value ) {
			$this->$key = $value;
		}
	}
}

class WP_Term {
	public int $term_id;
	public string $name;
	public int $count;

	public function __construct( int $term_id, string $name, int $count = 0 ) {
		$this->term_id = $term_id;
		$this->name    = $name;
		$this->count   = $count;
	}
}

// ---------------------------------------------------------------------------
// WordPress function stubs (delegate to WPTestStub)
// ---------------------------------------------------------------------------

function get_post_meta( int $post_id, string $key = '', bool $single = false ): mixed {
	if ( '' === $key ) {
		return WPTestStub::$post_meta[ $post_id ] ?? array();
	}

	if ( ! array_key_exists( $post_id, WPTestStub::$post_meta ) || ! array_key_exists( $key, WPTestStub::$post_meta[ $post_id ] ) ) {
		return $single ? '' : array();
	}

	$value = WPTestStub::$post_meta[ $post_id ][ $key ];

	return $single ? $value : array( $value );
}

function get_option( string $option, mixed $default = false ): mixed {
	return WPTestStub::$options[ $option ] ?? $default;
}

function update_option( string $option, mixed $value ): bool {
	WPTestStub::$options[ $option ] = $value;
	return true;
}

/**
 * @param array<string, mixed> $args
 * @return WP_Post[]
 */
function get_posts( array $args = array() ): array {
	$posts = array_filter(
		WPTestStub::$posts,
		static function ( WP_Post $post ) use ( $args ): bool {
			if ( isset( $args['post_type'] ) && $post->post_type !== $args['post_type'] ) {
				return false;
			}
			if ( isset( $args['post_status'] ) && $post->post_status !== $args['post_status'] ) {
				return false;
			}
			return true;
		}
	);
	$posts = array_values( $posts );

	$orderby = $args['orderby'] ?? '';

	if ( is_array( $orderby ) && isset( $orderby['menu_order'] ) ) {
		usort(
			$posts,
			static function ( WP_Post $a, WP_Post $b ): int {
				$by_menu_order = $a->menu_order <=> $b->menu_order;
				return 0 !== $by_menu_order ? $by_menu_order : $a->post_title <=> $b->post_title;
			}
		);
	} elseif ( 'date' === $orderby ) {
		$direction = strtoupper( (string) ( $args['order'] ?? 'DESC' ) );
		usort(
			$posts,
			static function ( WP_Post $a, WP_Post $b ) use ( $direction ): int {
				return 'ASC' === $direction ? $a->post_date <=> $b->post_date : $b->post_date <=> $a->post_date;
			}
		);
	}

	return $posts;
}

/**
 * @return WP_Term[]
 */
function get_the_category( int $post_id ): array {
	return WPTestStub::$post_categories[ $post_id ] ?? array();
}

function get_term( int $term_id, string $taxonomy = '' ): WP_Term|false {
	return WPTestStub::$terms[ $term_id ] ?? false;
}

/**
 * @param array<string, mixed> $args
 * @return WP_Term[]
 */
function get_categories( array $args = array() ): array {
	$terms = WPTestStub::$terms;

	if ( ! empty( $args['hide_empty'] ) ) {
		$terms = array_filter( $terms, static fn ( WP_Term $term ) => $term->count > 0 );
	}

	$terms = array_values( $terms );

	if ( 'count' === ( $args['orderby'] ?? '' ) ) {
		$direction = strtoupper( (string) ( $args['order'] ?? 'ASC' ) );
		usort(
			$terms,
			static fn ( WP_Term $a, WP_Term $b ) => 'ASC' === $direction ? $a->count <=> $b->count : $b->count <=> $a->count
		);
	} elseif ( 'name' === ( $args['orderby'] ?? '' ) ) {
		usort( $terms, static fn ( WP_Term $a, WP_Term $b ) => strcmp( $a->name, $b->name ) );
	}

	return $terms;
}

function is_multisite(): bool {
	return WPTestStub::$is_multisite;
}

function get_site( int $site_id ): object|false {
	return WPTestStub::$sites[ $site_id ] ?? false;
}

function switch_to_blog( int $site_id ): bool {
	WPTestStub::$current_site = $site_id;
	return true;
}

function restore_current_blog(): bool {
	WPTestStub::$current_site = null;
	return true;
}

function get_locale(): string {
	if ( null === WPTestStub::$current_site ) {
		return 'en_US';
	}
	return WPTestStub::$site_locales[ WPTestStub::$current_site ] ?? 'en_US';
}

function get_home_url( int $site_id, string $path = '' ): string {
	return "https://site{$site_id}.example.com/" . ltrim( $path, '/' );
}

function get_bloginfo( string $show = '' ): string {
	return WPTestStub::$bloginfo[ $show ] ?? '';
}

function get_the_title( WP_Post $post ): string {
	return $post->post_title;
}

function get_permalink( WP_Post $post ): string {
	return "https://example.com/{$post->post_name}/";
}

// ---------------------------------------------------------------------------
// Configurable stub data store
// ---------------------------------------------------------------------------

class WPTestStub {

	/** @var WP_Post[] */
	public static array $posts = array();

	/** post_id => array<meta_key, single value> */
	public static array $post_meta = array();

	/** option_name => value */
	public static array $options = array();

	/** post_id => WP_Term[] */
	public static array $post_categories = array();

	/** term_id => WP_Term */
	public static array $terms = array();

	public static bool $is_multisite = false;

	/** site_id => truthy stub object */
	public static array $sites = array();

	/** site_id => locale */
	public static array $site_locales = array();

	public static ?int $current_site = null;

	/** get_bloginfo() show => value */
	public static array $bloginfo = array();

	/** Resets all mock data (without clearing registered filters). */
	public static function reset(): void {
		self::$posts           = array();
		self::$post_meta        = array();
		self::$options          = array();
		self::$post_categories  = array();
		self::$terms            = array();
		self::$is_multisite     = false;
		self::$sites            = array();
		self::$site_locales     = array();
		self::$current_site     = null;
		self::$bloginfo         = array();
	}
}

// ---------------------------------------------------------------------------
// Load plugin classes
// ---------------------------------------------------------------------------

require_once dirname( __DIR__ ) . '/includes/class-description-resolver.php';
require_once dirname( __DIR__ ) . '/includes/class-category-order.php';
require_once dirname( __DIR__ ) . '/includes/class-post-meta.php';
require_once dirname( __DIR__ ) . '/includes/class-noindex-resolver.php';
require_once dirname( __DIR__ ) . '/includes/class-content-selector.php';
require_once dirname( __DIR__ ) . '/includes/class-alternate-sites.php';
require_once dirname( __DIR__ ) . '/includes/class-generator.php';
