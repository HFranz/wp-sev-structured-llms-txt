<?php
/**
 * Assembles the full llms.txt markdown document.
 *
 * @package SevStructuredLlmsTxt
 */

namespace SevStructuredLlmsTxt;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
 * Builds the llms.txt content: intro block, "Seiten" list, optional alternate
 * language links, "Beiträge" grouped by category, and (if WooCommerce is
 * active) products grouped by product category.
 */
class Generator {

	public const OPTION_TAGLINE = 'sevllms_tagline';

	private Content_Selector $content_selector;

	private Alternate_Sites $alternate_sites;

	private Description_Resolver $description_resolver;

	public function __construct(
		?Content_Selector $content_selector = null,
		?Alternate_Sites $alternate_sites = null,
		?Description_Resolver $description_resolver = null
	) {
		$this->content_selector     = $content_selector ?? new Content_Selector();
		$this->alternate_sites      = $alternate_sites ?? new Alternate_Sites();
		$this->description_resolver = $description_resolver ?? new Description_Resolver();
	}

	/**
	 * Generates the complete llms.txt content.
	 *
	 * @return string
	 */
	public function generate(): string {
		$sections = array( $this->intro_section() );

		$alternates_section = $this->alternates_section();
		if ( '' !== $alternates_section ) {
			$sections[] = $alternates_section;
		}

		$pages_section = $this->pages_section();
		if ( '' !== $pages_section ) {
			$sections[] = $pages_section;
		}

		$posts_section = $this->posts_section();
		if ( '' !== $posts_section ) {
			$sections[] = $posts_section;
		}

		$products_section = $this->products_section();
		if ( '' !== $products_section ) {
			$sections[] = $products_section;
		}

		$content = implode( "\n\n", $sections ) . "\n";

		/**
		 * Filters the fully assembled llms.txt content before it is cached/served.
		 *
		 * @param string $content The generated llms.txt content.
		 */
		return (string) apply_filters( 'sevllms_generated_content', $content );
	}

	/**
	 * Builds the "# Site Name" + tagline blockquote intro block.
	 *
	 * @return string
	 */
	private function intro_section(): string {
		$title   = $this->clean( get_bloginfo( 'name' ) );
		$tagline = trim( (string) get_option( self::OPTION_TAGLINE, '' ) );

		if ( '' === $tagline ) {
			$tagline = get_bloginfo( 'description' );
		}

		$tagline = $this->clean( $tagline );

		$lines = array( '# ' . $title );

		if ( '' !== $tagline ) {
			$lines[] = '';
			$lines[] = '> ' . $tagline;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Builds the "## Seiten" section, or an empty string if there are no pages.
	 *
	 * @return string
	 */
	private function pages_section(): string {
		$pages = $this->content_selector->get_pages();

		if ( empty( $pages ) ) {
			return '';
		}

		$lines = array( __( '## Pages', 'sev-structured-llms-txt' ), '' );

		foreach ( $pages as $page ) {
			$lines[] = $this->link_line( $page );
		}

		return implode( "\n", $lines );
	}

	/**
	 * Builds the alternate-language-version blockquote lines, or an empty
	 * string if none are configured.
	 *
	 * @return string
	 */
	private function alternates_section(): string {
		$alternates = $this->alternate_sites->resolve();

		if ( empty( $alternates ) ) {
			return '';
		}

		$lines = array();

		foreach ( $alternates as $label => $url ) {
			$link = sprintf( '[%1$s](%1$s)', $url );

			/* translators: 1: language label, e.g. "English", 2: markdown link to the alternate llms.txt. */
			$lines[] = sprintf( __( '> %1$s version: %2$s', 'sev-structured-llms-txt' ), $this->clean( $label ), $link );
		}

		return implode( "\n", $lines );
	}

	/**
	 * Builds the "## Beiträge" section with one "###" subheading per category.
	 *
	 * @return string
	 */
	private function posts_section(): string {
		$grouped = $this->content_selector->get_grouped_posts();

		if ( empty( $grouped ) ) {
			return '';
		}

		$lines = array( __( '## Posts', 'sev-structured-llms-txt' ) );

		foreach ( $grouped as $group_name => $posts ) {
			$heading = Content_Selector::UNCATEGORIZED_KEY === $group_name
				? __( 'More posts', 'sev-structured-llms-txt' )
				: $this->clean( $group_name );

			$lines[] = '';
			$lines[] = '### ' . $heading;
			$lines[] = '';

			foreach ( $posts as $post ) {
				$lines[] = $this->link_line( $post );
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Builds the "## Products" section with one "###" subheading per product
	 * category, or an empty string if there are no products (including when
	 * WooCommerce isn't active).
	 *
	 * @return string
	 */
	private function products_section(): string {
		$grouped = $this->content_selector->get_grouped_products();

		if ( empty( $grouped ) ) {
			return '';
		}

		$lines = array( __( '## Products', 'sev-structured-llms-txt' ) );

		foreach ( $grouped as $group_name => $products ) {
			$heading = Content_Selector::UNCATEGORIZED_KEY === $group_name
				? __( 'More products', 'sev-structured-llms-txt' )
				: $this->clean( $group_name );

			$lines[] = '';
			$lines[] = '### ' . $heading;
			$lines[] = '';

			foreach ( $products as $product ) {
				$lines[] = $this->link_line( $product );
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Formats a single "- [Title](URL): Description" markdown line.
	 *
	 * @param \WP_Post $post The post or page.
	 * @return string
	 */
	private function link_line( \WP_Post $post ): string {
		$title       = $this->clean( get_the_title( $post ) );
		$url         = get_permalink( $post );
		$description = $this->clean( $this->description_resolver->resolve( $post ) );

		$line = sprintf( '- [%s](%s)', $title, $url );

		if ( '' !== $description ) {
			$line .= ': ' . $description;
		}

		return $line;
	}

	/**
	 * Strips tags, decodes HTML entities (WordPress stores "&" as "&#038;" in
	 * titles/terms, and wptexturize() turns "-" into "&#8211;" etc.), removes
	 * soft hyphens, and collapses whitespace, so an LLM reading the output sees
	 * the same plain text a human would, not markup-escaped source text.
	 *
	 * @param string $text Raw text pulled from post/term data.
	 * @return string
	 */
	private function clean( string $text ): string {
		$text = wp_strip_all_tags( $text );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = str_replace( "\u{00AD}", '', $text );

		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}
}
