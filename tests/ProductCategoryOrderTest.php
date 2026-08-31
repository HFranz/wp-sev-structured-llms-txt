<?php

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;
use SevStructuredLlmsTxt\Product_Category_Order;

final class ProductCategoryOrderTest extends TestCase {

	protected function setUp(): void {
		WPTestStub::reset();
	}

	public function test_uses_saved_order_when_present(): void {
		WPTestStub::$options[ Product_Category_Order::OPTION_NAME ] = array( 5, 2 );

		$this->assertSame( array( 5, 2 ), ( new Product_Category_Order() )->ordered_term_ids() );
	}

	public function test_defaults_to_product_count_descending_when_nothing_saved(): void {
		WPTestStub::$product_terms = array(
			1 => new WP_Term( 1, 'Few', 2 ),
			2 => new WP_Term( 2, 'Many', 10 ),
			3 => new WP_Term( 3, 'Empty', 0 ),
		);

		$this->assertSame( array( 2, 1 ), ( new Product_Category_Order() )->ordered_term_ids() );
	}

	public function test_defaults_to_empty_when_taxonomy_has_no_terms(): void {
		$this->assertSame( array(), ( new Product_Category_Order() )->ordered_term_ids() );
	}

	public function test_admin_rows_marks_saved_categories_as_included_and_appends_the_rest(): void {
		WPTestStub::$product_terms = array(
			1 => new WP_Term( 1, 'Alpha', 3 ),
			2 => new WP_Term( 2, 'Beta', 1 ),
			3 => new WP_Term( 3, 'Gamma', 0 ),
		);
		WPTestStub::$options[ Product_Category_Order::OPTION_NAME ] = array( 2 );

		$rows = ( new Product_Category_Order() )->admin_rows();

		$this->assertSame(
			array(
				array( 'term_id' => 2, 'name' => 'Beta', 'included' => true ),
				array( 'term_id' => 1, 'name' => 'Alpha', 'included' => false ),
				array( 'term_id' => 3, 'name' => 'Gamma', 'included' => false ),
			),
			$rows
		);
	}

	public function test_admin_rows_do_not_leak_into_post_categories(): void {
		WPTestStub::$terms = array(
			1 => new WP_Term( 1, 'Post category', 1 ),
		);
		WPTestStub::$product_terms = array(
			2 => new WP_Term( 2, 'Product category', 1 ),
		);

		$rows = ( new Product_Category_Order() )->admin_rows();

		$this->assertSame( array( 'Product category' ), array_column( $rows, 'name' ) );
	}
}
