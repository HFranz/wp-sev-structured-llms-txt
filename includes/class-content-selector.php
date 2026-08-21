<?php
/**
 * Selects and groups the pages/posts shown in llms.txt.
 *
 * @package SevStructuredLlmsTxt
 */

namespace SevStructuredLlmsTxt;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
 * Builds the "Seiten" list and the category-grouped "Beiträge" list.
 */
class Content_Selector {

	/**
	 * Key used for the trailing group of posts without any category.
	 */
	public const UNCATEGORIZED_KEY = '__uncategorized__';

	private Category_Order $category_order;

	public function __construct( ?Category_Order $category_order = null ) {
		$this->category_order = $category_order ?? new Category_Order();
	}

	/**
	 * Returns every published, non-excluded page in standard page order.
	 *
	 * @return \WP_Post[]
	 */
	public function get_pages(): array {
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'posts_per_page' => -1,
				'no_found_rows'  => true,
			)
		);

		return array_values( array_filter( $pages, array( $this, 'is_included' ) ) );
	}

	/**
	 * Returns every published, non-excluded post grouped by its primary
	 * category name, ordered per the configured category order, each group's
	 * posts newest first. Posts without a category are returned under
	 * self::UNCATEGORIZED_KEY, only if that group is non-empty.
	 *
	 * @return array<string, \WP_Post[]>
	 */
	public function get_grouped_posts(): array {
		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
			)
		);
		$posts = array_filter( $posts, array( $this, 'is_included' ) );

		$by_term_id = array();
		$uncategorized = array();

		foreach ( $posts as $post ) {
			$term_id = $this->primary_category_term_id( $post );

			if ( null === $term_id ) {
				$uncategorized[] = $post;
				continue;
			}

			$by_term_id[ $term_id ][] = $post;
		}

		$grouped = array();

		foreach ( $this->category_order->ordered_term_ids() as $term_id ) {
			if ( empty( $by_term_id[ $term_id ] ) ) {
				continue;
			}

			$term = get_term( $term_id, 'category' );

			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			$grouped[ $term->name ] = $by_term_id[ $term_id ];
		}

		if ( ! empty( $uncategorized ) ) {
			$grouped[ self::UNCATEGORIZED_KEY ] = $uncategorized;
		}

		return $grouped;
	}

	/**
	 * Whether a post/page has not been excluded via the "llms.txt" checkbox.
	 *
	 * @param \WP_Post $post The post or page.
	 * @return bool
	 */
	private function is_included( \WP_Post $post ): bool {
		return '1' !== get_post_meta( $post->ID, Post_Meta::META_KEY, true );
	}

	/**
	 * Resolves a post's primary category: Yoast's chosen primary category if
	 * set and still valid, otherwise the assigned category with the lowest
	 * term ID. Returns null if the post has no category at all.
	 *
	 * @param \WP_Post $post The post.
	 * @return int|null Term ID, or null if uncategorized.
	 */
	private function primary_category_term_id( \WP_Post $post ): ?int {
		$categories = get_the_category( $post->ID );

		if ( empty( $categories ) ) {
			return null;
		}

		$primary_id = (int) get_post_meta( $post->ID, '_yoast_wpseo_primary_category', true );

		if ( $primary_id > 0 ) {
			foreach ( $categories as $category ) {
				if ( (int) $category->term_id === $primary_id ) {
					return $primary_id;
				}
			}
		}

		$term_ids = array_map( static fn ( $category ) => (int) $category->term_id, $categories );
		sort( $term_ids );

		return $term_ids[0];
	}
}
