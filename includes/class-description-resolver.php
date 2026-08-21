<?php
/**
 * Resolves the short description shown behind a page/post link in llms.txt.
 *
 * @package SevStructuredLlmsTxt
 */

namespace SevStructuredLlmsTxt;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
 * Resolves a post's description from the first available SEO plugin meta
 * description, falling back to its excerpt and finally to a trimmed excerpt
 * of its content.
 */
class Description_Resolver {

	/**
	 * Postmeta keys checked in order, one per supported SEO plugin.
	 *
	 * @var string[]
	 */
	private const SEO_META_KEYS = array(
		'_yoast_wpseo_metadesc',
		'rank_math_description',
		'_seopress_titles_desc',
		'_aioseo_description',
	);

	/**
	 * Number of words used when falling back to a trimmed content excerpt.
	 */
	private const FALLBACK_EXCERPT_WORDS = 30;

	/**
	 * Resolves the description for a given post.
	 *
	 * @param \WP_Post $post The post or page.
	 * @return string The resolved description, possibly empty.
	 */
	public function resolve( \WP_Post $post ): string {
		$description = $this->from_seo_meta( $post );

		if ( '' === $description ) {
			$description = $this->from_excerpt( $post );
		}

		/**
		 * Filters the resolved llms.txt description for a post.
		 *
		 * @param string   $description The resolved description.
		 * @param \WP_Post $post        The post the description belongs to.
		 */
		return (string) apply_filters( 'sevllms_description', $description, $post );
	}

	/**
	 * Reads the first non-empty SEO plugin meta description.
	 *
	 * @param \WP_Post $post The post or page.
	 * @return string The meta description, or an empty string if none is set.
	 */
	private function from_seo_meta( \WP_Post $post ): string {
		foreach ( self::SEO_META_KEYS as $meta_key ) {
			$value = get_post_meta( $post->ID, $meta_key, true );

			if ( is_string( $value ) && '' !== trim( $value ) ) {
				return trim( $value );
			}
		}

		return '';
	}

	/**
	 * Falls back to the post's own excerpt, or a trimmed excerpt of its content.
	 *
	 * @param \WP_Post $post The post or page.
	 * @return string The excerpt-based description, possibly empty.
	 */
	private function from_excerpt( \WP_Post $post ): string {
		if ( '' !== trim( $post->post_excerpt ) ) {
			return trim( wp_strip_all_tags( $post->post_excerpt ) );
		}

		$content = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );

		if ( '' === trim( $content ) ) {
			return '';
		}

		return trim( wp_trim_words( $content, self::FALLBACK_EXCERPT_WORDS ) );
	}
}
