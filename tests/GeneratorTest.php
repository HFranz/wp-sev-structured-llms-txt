<?php

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;
use SevStructuredLlmsTxt\Alternate_Sites;
use SevStructuredLlmsTxt\Category_Order;
use SevStructuredLlmsTxt\Generator;

final class GeneratorTest extends TestCase {

	protected function setUp(): void {
		WPTestStub::reset();
		WPTestStub::$bloginfo = array(
			'name'        => 'sevmatic',
			'description' => 'Wir entwickeln digitale Systeme.',
		);
	}

	public function test_generates_full_document_with_all_sections(): void {
		WPTestStub::$terms = array(
			10 => new WP_Term( 10, 'WordPress & CMS', 1 ),
		);
		WPTestStub::$options[ Category_Order::OPTION_NAME ] = array( 10 );

		WPTestStub::$posts = array(
			new WP_Post( array( 'ID' => 1, 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Start', 'post_name' => 'start' ) ),
			new WP_Post( array( 'ID' => 2, 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Blog Post', 'post_name' => 'blog-post', 'post_date' => '2026-06-01' ) ),
		);
		WPTestStub::$post_meta = array(
			1 => array( '_yoast_wpseo_metadesc' => 'Homepage description' ),
			2 => array( '_yoast_wpseo_metadesc' => 'Post description' ),
		);
		WPTestStub::$post_categories = array(
			2 => array( WPTestStub::$terms[10] ),
		);

		WPTestStub::$is_multisite = true;
		WPTestStub::$sites        = array( 2 => new stdClass() );
		WPTestStub::$site_locales = array( 2 => 'en_US' );
		WPTestStub::$options[ Alternate_Sites::OPTION_NAME ] = array(
			array( 'site_id' => 2, 'label' => '' ),
		);

		$expected = <<<'MARKDOWN'
		# sevmatic

		> Wir entwickeln digitale Systeme.

		## Pages

		- [Start](https://example.com/start/): Homepage description

		> English version: https://site2.example.com/llms.txt

		## Posts

		### WordPress & CMS

		- [Blog Post](https://example.com/blog-post/): Post description
		MARKDOWN;

		$this->assertSame( $expected . "\n", ( new Generator() )->generate() );
	}

	public function test_omits_empty_sections(): void {
		$content = ( new Generator() )->generate();

		$this->assertSame( "# sevmatic\n\n> Wir entwickeln digitale Systeme.\n", $content );
	}

	public function test_custom_tagline_overrides_site_description(): void {
		WPTestStub::$options[ Generator::OPTION_TAGLINE ] = 'Custom tagline';

		$content = ( new Generator() )->generate();

		$this->assertStringContainsString( '> Custom tagline', $content );
		$this->assertStringNotContainsString( 'Wir entwickeln digitale Systeme.', $content );
	}

	public function test_uncategorized_posts_render_under_more_posts_heading(): void {
		WPTestStub::$posts = array(
			new WP_Post( array( 'ID' => 1, 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Loose post', 'post_name' => 'loose-post', 'post_date' => '2026-01-01' ) ),
		);

		$content = ( new Generator() )->generate();

		$this->assertStringContainsString( "### More posts\n\n- [Loose post](https://example.com/loose-post/)", $content );
	}

	public function test_products_section_is_omitted_when_there_are_no_products(): void {
		$content = ( new Generator() )->generate();

		$this->assertStringNotContainsString( '## Products', $content );
	}

	public function test_products_section_renders_grouped_by_product_category(): void {
		WPTestStub::$product_terms = array(
			10 => new WP_Term( 10, 'Shirts', 1 ),
		);
		WPTestStub::$options['sevllms_product_category_order'] = array( 10 );

		WPTestStub::$posts = array(
			new WP_Post( array( 'ID' => 1, 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'Basic Shirt', 'post_name' => 'basic-shirt', 'post_date' => '2026-06-01' ) ),
		);
		WPTestStub::$post_terms = array(
			1 => array( 'product_cat' => array( WPTestStub::$product_terms[10] ) ),
		);

		$content = ( new Generator() )->generate();

		$this->assertStringContainsString( "## Products\n\n### Shirts\n\n- [Basic Shirt](https://example.com/basic-shirt/)", $content );
	}

	public function test_uncategorized_products_render_under_more_products_heading(): void {
		WPTestStub::$posts = array(
			new WP_Post( array( 'ID' => 1, 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'Loose product', 'post_name' => 'loose-product', 'post_date' => '2026-01-01' ) ),
		);

		$content = ( new Generator() )->generate();

		$this->assertStringContainsString( "### More products\n\n- [Loose product](https://example.com/loose-product/)", $content );
	}

	public function test_generated_content_is_filterable(): void {
		add_filter(
			'sevllms_generated_content',
			static fn ( string $content ): string => $content . 'APPENDED'
		);

		$this->assertStringEndsWith( 'APPENDED', ( new Generator() )->generate() );
	}
}
