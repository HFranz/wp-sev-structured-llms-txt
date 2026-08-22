<?php
/**
 * Detects whether a post/page has been marked "noindex" by an SEO plugin.
 *
 * @package SevStructuredLlmsTxt
 */

namespace SevStructuredLlmsTxt;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
 * Resolves whether a post is set to "noindex" in Yoast SEO, Rank Math,
 * SEOPress, or All in One SEO, so it can be treated the same as a manual
 * "Exclude from llms.txt" flag: a page hidden from search engines is
 * presumably also not meant to be listed for LLMs.
 */
class Noindex_Resolver {

	/**
	 * Whether the given post is marked "noindex" by a supported SEO plugin.
	 *
	 * @param \WP_Post $post The post or page.
	 * @return bool
	 */
	public function is_noindex( \WP_Post $post ): bool {
		$noindex = $this->from_yoast( $post )
			|| $this->from_rank_math( $post )
			|| $this->from_seopress( $post )
			|| $this->from_aioseo( $post );

		/**
		 * Filters whether a post is considered "noindex" for llms.txt purposes.
		 *
		 * @param bool     $noindex Whether the post was detected as noindex.
		 * @param \WP_Post $post    The post the check applies to.
		 */
		return (bool) apply_filters( 'sevllms_is_noindex', $noindex, $post );
	}

	/**
	 * Checks Yoast SEO's "noindex" setting for this post.
	 *
	 * @param \WP_Post $post The post or page.
	 * @return bool
	 */
	private function from_yoast( \WP_Post $post ): bool {
		return '1' === get_post_meta( $post->ID, '_yoast_wpseo_meta-robots-noindex', true );
	}

	/**
	 * Checks Rank Math's "noindex" setting for this post.
	 *
	 * @param \WP_Post $post The post or page.
	 * @return bool
	 */
	private function from_rank_math( \WP_Post $post ): bool {
		$robots = get_post_meta( $post->ID, 'rank_math_robots', true );

		return is_array( $robots ) && in_array( 'noindex', $robots, true );
	}

	/**
	 * Checks SEOPress's "noindex" setting for this post.
	 *
	 * @param \WP_Post $post The post or page.
	 * @return bool
	 */
	private function from_seopress( \WP_Post $post ): bool {
		return 'yes' === get_post_meta( $post->ID, '_seopress_robots_index', true );
	}

	/**
	 * Checks All in One SEO's "noindex" setting for this post.
	 *
	 * @param \WP_Post $post The post or page.
	 * @return bool
	 */
	private function from_aioseo( \WP_Post $post ): bool {
		return '1' === (string) get_post_meta( $post->ID, '_aioseo_noindex', true );
	}
}
