<?php

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;
use SevStructuredLlmsTxt\Content_Selector;
use SevStructuredLlmsTxt\Post_Meta;

final class ContentSelectorTest extends TestCase {

	protected function setUp(): void {
		WPTestStub::reset();
	}

	public function test_get_pages_returns_only_published_pages_in_menu_order(): void {
		WPTestStub::$posts = array(
			new WP_Post( array( 'ID' => 1, 'post_type' => 'page', 'post_status' => 'publish', 'menu_order' => 2, 'post_title' => 'Second' ) ),
			new WP_Post( array( 'ID' => 2, 'post_type' => 'page', 'post_status' => 'publish', 'menu_order' => 1, 'post_title' => 'First' ) ),
			new WP_Post( array( 'ID' => 3, 'post_type' => 'page', 'post_status' => 'draft', 'menu_order' => 0, 'post_title' => 'Draft' ) ),
			new WP_Post( array( 'ID' => 4, 'post_type' => 'post', 'post_status' => 'publish', 'menu_order' => 0, 'post_title' => 'A Post' ) ),
		);

		$titles = array_map( static fn ( $post ) => $post->post_title, ( new Content_Selector() )->get_pages() );

		$this->assertSame( array( 'First', 'Second' ), $titles );
	}

	public function test_get_pages_excludes_posts_flagged_for_exclusion(): void {
		WPTestStub::$posts = array(
			new WP_Post( array( 'ID' => 1, 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Visible' ) ),
			new WP_Post( array( 'ID' => 2, 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Hidden' ) ),
		);
		WPTestStub::$post_meta[2] = array( Post_Meta::META_KEY => '1' );

		$titles = array_map( static fn ( $post ) => $post->post_title, ( new Content_Selector() )->get_pages() );

		$this->assertSame( array( 'Visible' ), $titles );
	}

	public function test_get_pages_excludes_pages_marked_noindex(): void {
		WPTestStub::$posts = array(
			new WP_Post( array( 'ID' => 1, 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Visible' ) ),
			new WP_Post( array( 'ID' => 2, 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Impressum' ) ),
		);
		WPTestStub::$post_meta[2] = array( '_yoast_wpseo_meta-robots-noindex' => '1' );

		$titles = array_map( static fn ( $post ) => $post->post_title, ( new Content_Selector() )->get_pages() );

		$this->assertSame( array( 'Visible' ), $titles );
	}

	public function test_get_grouped_posts_groups_by_primary_category_newest_first(): void {
		WPTestStub::$terms = array(
			10 => new WP_Term( 10, 'WordPress', 2 ),
			20 => new WP_Term( 20, 'PHP', 1 ),
		);
		WPTestStub::$options['sevllms_category_order'] = array( 10, 20 );

		WPTestStub::$posts = array(
			new WP_Post( array( 'ID' => 1, 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Old WP post', 'post_date' => '2026-01-01' ) ),
			new WP_Post( array( 'ID' => 2, 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'New WP post', 'post_date' => '2026-06-01' ) ),
			new WP_Post( array( 'ID' => 3, 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'PHP post', 'post_date' => '2026-03-01' ) ),
		);
		WPTestStub::$post_categories = array(
			1 => array( WPTestStub::$terms[10] ),
			2 => array( WPTestStub::$terms[10] ),
			3 => array( WPTestStub::$terms[20] ),
		);

		$grouped = ( new Content_Selector() )->get_grouped_posts();

		$this->assertSame( array( 'WordPress', 'PHP' ), array_keys( $grouped ) );
		$this->assertSame( array( 'New WP post', 'Old WP post' ), array_map( static fn ( $p ) => $p->post_title, $grouped['WordPress'] ) );
		$this->assertSame( array( 'PHP post' ), array_map( static fn ( $p ) => $p->post_title, $grouped['PHP'] ) );
	}

	public function test_get_grouped_posts_prefers_yoast_primary_category(): void {
		WPTestStub::$terms = array(
			10 => new WP_Term( 10, 'WordPress', 1 ),
			20 => new WP_Term( 20, 'PHP', 1 ),
		);
		WPTestStub::$options['sevllms_category_order'] = array( 10, 20 );

		WPTestStub::$posts = array(
			new WP_Post( array( 'ID' => 1, 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Cross-posted', 'post_date' => '2026-01-01' ) ),
		);
		WPTestStub::$post_categories = array(
			1 => array( WPTestStub::$terms[10], WPTestStub::$terms[20] ),
		);
		WPTestStub::$post_meta[1] = array( '_yoast_wpseo_primary_category' => '20' );

		$grouped = ( new Content_Selector() )->get_grouped_posts();

		$this->assertSame( array( 'PHP' ), array_keys( $grouped ) );
	}

	public function test_get_grouped_posts_falls_back_to_lowest_term_id_without_yoast(): void {
		WPTestStub::$terms = array(
			10 => new WP_Term( 10, 'WordPress', 1 ),
			20 => new WP_Term( 20, 'PHP', 1 ),
		);
		WPTestStub::$options['sevllms_category_order'] = array( 10, 20 );

		WPTestStub::$posts = array(
			new WP_Post( array( 'ID' => 1, 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Cross-posted', 'post_date' => '2026-01-01' ) ),
		);
		WPTestStub::$post_categories = array(
			1 => array( WPTestStub::$terms[20], WPTestStub::$terms[10] ),
		);

		$grouped = ( new Content_Selector() )->get_grouped_posts();

		$this->assertSame( array( 'WordPress' ), array_keys( $grouped ) );
	}

	public function test_uncategorized_posts_land_in_special_group_when_non_empty(): void {
		WPTestStub::$posts = array(
			new WP_Post( array( 'ID' => 1, 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'No category', 'post_date' => '2026-01-01' ) ),
		);

		$grouped = ( new Content_Selector() )->get_grouped_posts();

		$this->assertSame( array( Content_Selector::UNCATEGORIZED_KEY ), array_keys( $grouped ) );
	}

	public function test_no_uncategorized_group_when_there_are_no_posts(): void {
		$this->assertSame( array(), ( new Content_Selector() )->get_grouped_posts() );
	}

	public function test_get_grouped_products_returns_empty_array_when_woocommerce_is_not_active(): void {
		$this->assertSame( array(), ( new Content_Selector() )->get_grouped_products() );
	}

	public function test_get_grouped_products_groups_by_primary_product_category_newest_first(): void {
		WPTestStub::$product_terms = array(
			10 => new WP_Term( 10, 'Shirts', 2 ),
			20 => new WP_Term( 20, 'Mugs', 1 ),
		);
		WPTestStub::$options['sevllms_product_category_order'] = array( 10, 20 );

		WPTestStub::$posts = array(
			new WP_Post( array( 'ID' => 1, 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'Old shirt', 'post_date' => '2026-01-01' ) ),
			new WP_Post( array( 'ID' => 2, 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'New shirt', 'post_date' => '2026-06-01' ) ),
			new WP_Post( array( 'ID' => 3, 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'Mug', 'post_date' => '2026-03-01' ) ),
		);
		WPTestStub::$post_terms = array(
			1 => array( 'product_cat' => array( WPTestStub::$product_terms[10] ) ),
			2 => array( 'product_cat' => array( WPTestStub::$product_terms[10] ) ),
			3 => array( 'product_cat' => array( WPTestStub::$product_terms[20] ) ),
		);

		$grouped = ( new Content_Selector() )->get_grouped_products();

		$this->assertSame( array( 'Shirts', 'Mugs' ), array_keys( $grouped ) );
		$this->assertSame( array( 'New shirt', 'Old shirt' ), array_map( static fn ( $p ) => $p->post_title, $grouped['Shirts'] ) );
		$this->assertSame( array( 'Mug' ), array_map( static fn ( $p ) => $p->post_title, $grouped['Mugs'] ) );
	}

	public function test_get_grouped_products_prefers_yoast_primary_product_category(): void {
		WPTestStub::$product_terms = array(
			10 => new WP_Term( 10, 'Shirts', 1 ),
			20 => new WP_Term( 20, 'Mugs', 1 ),
		);
		WPTestStub::$options['sevllms_product_category_order'] = array( 10, 20 );

		WPTestStub::$posts = array(
			new WP_Post( array( 'ID' => 1, 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'Cross-listed', 'post_date' => '2026-01-01' ) ),
		);
		WPTestStub::$post_terms = array(
			1 => array( 'product_cat' => array( WPTestStub::$product_terms[10], WPTestStub::$product_terms[20] ) ),
		);
		WPTestStub::$post_meta[1] = array( '_yoast_wpseo_primary_product_cat' => '20' );

		$grouped = ( new Content_Selector() )->get_grouped_products();

		$this->assertSame( array( 'Mugs' ), array_keys( $grouped ) );
	}

	public function test_get_grouped_products_excludes_products_flagged_for_exclusion(): void {
		WPTestStub::$posts = array(
			new WP_Post( array( 'ID' => 1, 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'Visible', 'post_date' => '2026-01-01' ) ),
			new WP_Post( array( 'ID' => 2, 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'Hidden', 'post_date' => '2026-01-01' ) ),
		);
		WPTestStub::$post_meta[2] = array( Post_Meta::META_KEY => '1' );

		$grouped = ( new Content_Selector() )->get_grouped_products();

		$this->assertSame( array( Content_Selector::UNCATEGORIZED_KEY ), array_keys( $grouped ) );
		$this->assertSame( array( 'Visible' ), array_map( static fn ( $p ) => $p->post_title, $grouped[ Content_Selector::UNCATEGORIZED_KEY ] ) );
	}
}
