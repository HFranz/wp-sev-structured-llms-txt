<?php
/**
 * Resolves which categories appear in llms.txt and in what order.
 *
 * @package SevStructuredLlmsTxt
 */

namespace SevStructuredLlmsTxt;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
 * Reads/writes the admin-configured category order and provides the default
 * (post-count descending) when nothing has been configured yet.
 */
class Category_Order {

	public const OPTION_NAME = 'sevllms_category_order';

	/**
	 * Returns the term IDs of the categories to include, in display order.
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
	 * Builds the rows shown on the settings screen: every category that
	 * exists, in the order they should be listed (configured/default order
	 * first, any remaining categories appended alphabetically and unchecked).
	 *
	 * @return array<int, array{term_id: int, name: string, included: bool}>
	 */
	public function admin_rows(): array {
		$ordered_ids = $this->ordered_term_ids();

		$all_terms = get_categories( array( 'hide_empty' => false, 'orderby' => 'name' ) );
		$by_id     = array();
		foreach ( $all_terms as $term ) {
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
	 * every category, most-populated first.
	 *
	 * @return int[]
	 */
	private function default_order(): array {
		$categories = get_categories(
			array(
				'orderby'    => 'count',
				'order'      => 'DESC',
				'hide_empty' => true,
			)
		);

		return array_map(
			static fn ( $category ) => (int) $category->term_id,
			$categories
		);
	}
}
