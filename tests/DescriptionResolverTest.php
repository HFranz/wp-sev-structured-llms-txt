<?php

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;
use SevStructuredLlmsTxt\Description_Resolver;

final class DescriptionResolverTest extends TestCase {

	protected function setUp(): void {
		WPTestStub::reset();
	}

	public function test_prefers_yoast_meta_description(): void {
		$post = new WP_Post( array( 'ID' => 1, 'post_excerpt' => 'Excerpt text' ) );
		WPTestStub::$post_meta[1] = array( '_yoast_wpseo_metadesc' => 'Yoast description' );

		$this->assertSame( 'Yoast description', ( new Description_Resolver() )->resolve( $post ) );
	}

	public function test_falls_back_through_seo_plugins_in_order(): void {
		$post = new WP_Post( array( 'ID' => 2 ) );
		WPTestStub::$post_meta[2] = array(
			'rank_math_description' => 'Rank Math description',
			'_aioseo_description'   => 'AIOSEO description',
		);

		$this->assertSame( 'Rank Math description', ( new Description_Resolver() )->resolve( $post ) );
	}

	public function test_falls_back_to_excerpt_when_no_seo_meta_is_set(): void {
		$post = new WP_Post( array( 'ID' => 3, 'post_excerpt' => 'My excerpt' ) );

		$this->assertSame( 'My excerpt', ( new Description_Resolver() )->resolve( $post ) );
	}

	public function test_falls_back_to_trimmed_content_when_nothing_else_is_set(): void {
		$long_content = implode( ' ', array_fill( 0, 40, 'word' ) );
		$post          = new WP_Post( array( 'ID' => 4, 'post_content' => $long_content ) );

		$description = ( new Description_Resolver() )->resolve( $post );

		$this->assertStringEndsWith( '…', $description );
		$without_ellipsis = preg_replace( '/…$/u', '', $description );
		$this->assertCount( 30, array_filter( explode( ' ', $without_ellipsis ) ) );
	}

	public function test_strips_shortcodes_from_content_fallback(): void {
		$post = new WP_Post( array( 'ID' => 5, 'post_content' => '[gallery ids="1,2"] Real text here' ) );

		$this->assertSame( 'Real text here', ( new Description_Resolver() )->resolve( $post ) );
	}

	public function test_returns_empty_string_when_nothing_is_available(): void {
		$post = new WP_Post( array( 'ID' => 6 ) );

		$this->assertSame( '', ( new Description_Resolver() )->resolve( $post ) );
	}

	public function test_description_is_filterable(): void {
		$post = new WP_Post( array( 'ID' => 7, 'post_excerpt' => 'Original' ) );

		add_filter(
			'sevllms_description',
			static function ( string $description, WP_Post $filtered_post ): string {
				return $filtered_post->ID === 7 ? 'Overridden' : $description;
			},
			10,
			2
		);

		$this->assertSame( 'Overridden', ( new Description_Resolver() )->resolve( $post ) );
	}
}
