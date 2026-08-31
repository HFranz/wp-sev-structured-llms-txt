<?php
/**
 * Resolves which product categories appear in llms.txt and in what order.
 *
 * @package SevStructuredLlmsTxt
 */

namespace SevStructuredLlmsTxt;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
 * Reads/writes the admin-configured product category order and provides the
 * default (product-count descending) when nothing has been configured yet.
 * Mirrors Category_Order, but for WooCommerce's "product_cat" taxonomy. If
 * WooCommerce isn't active, the taxonomy simply doesn't exist and every
 * method here returns an empty result.
 */
class Product_Category_Order {

	public const OPTION_NAME = 'sevllms_product_category_order';

	public const TAXONOMY = 'product_cat';

	/**
	 * Returns the term IDs of the product categories to include, in display order.
	 *
	 * @return int[]
	 */
	public function ordered_term_ids(): array {
		$stored = get_option( self::OPTION_NAME, null );

		if ( is_array( $stored ) ) {
			return array_values( array_map( 'intval', $stored ) );
		}

		return $this->default_order();
	}

	/**
	 * Builds the rows shown on the settings screen: every product category
	 * that exists, in the order they should be listed (configured/default
	 * order first, any remaining categories appended alphabetically and
	 * unchecked).
	 *
	 * @return array<int, array{term_id: int, name: string, included: bool}>
	 */
	public function admin_rows(): array {
		$ordered_ids = $this->ordered_term_ids();

		$by_id = array();
		foreach ( $this->all_terms(
			array(
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		) as $term ) {
			$by_id[ (int) $term->term_id ] = $term;
		}

		$rows = array();

		foreach ( $ordered_ids as $term_id ) {
			if ( ! isset( $by_id[ $term_id ] ) ) {
				continue;
			}

			$rows[] = array(
				'term_id'  => $term_id,
				'name'     => $by_id[ $term_id ]->name,
				'included' => true,
			);

			unset( $by_id[ $term_id ] );
		}

		foreach ( $by_id as $term_id => $term ) {
			$rows[] = array(
				'term_id'  => $term_id,
				'name'     => $term->name,
				'included' => false,
			);
		}

		return $rows;
	}

	/**
	 * Default order used before the admin has ever saved a configuration:
	 * every product category, most-populated first.
	 *
	 * @return int[]
	 */
	private function default_order(): array {
		$terms = $this->all_terms(
			array(
				'orderby'    => 'count',
				'order'      => 'DESC',
				'hide_empty' => true,
			)
		);

		return array_map(
			static fn ( $term ) => (int) $term->term_id,
			$terms
		);
	}

	/**
	 * Fetches product_cat terms, tolerating a WP_Error (e.g. WooCommerce not
	 * active, so the taxonomy doesn't exist) by returning an empty array.
	 *
	 * @param array<string, mixed> $args get_terms() args, minus 'taxonomy'.
	 * @return \WP_Term[]
	 */
	private function all_terms( array $args ): array {
		$terms = get_terms( array_merge( $args, array( 'taxonomy' => self::TAXONOMY ) ) );

		return is_array( $terms ) ? $terms : array();
	}
}
