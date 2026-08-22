<?php

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;
use SevStructuredLlmsTxt\Noindex_Resolver;

final class NoindexResolverTest extends TestCase {

	protected function setUp(): void {
		WPTestStub::reset();
		WPTestStub::$posts = array( new WP_Post( array( 'ID' => 1 ) ) );
	}

	public function test_not_noindex_by_default(): void {
		$this->assertFalse( ( new Noindex_Resolver() )->is_noindex( WPTestStub::$posts[0] ) );
	}

	public function test_detects_yoast_noindex(): void {
		WPTestStub::$post_meta[1] = array( '_yoast_wpseo_meta-robots-noindex' => '1' );

		$this->assertTrue( ( new Noindex_Resolver() )->is_noindex( WPTestStub::$posts[0] ) );
	}

	public function test_yoast_index_value_is_not_noindex(): void {
		WPTestStub::$post_meta[1] = array( '_yoast_wpseo_meta-robots-noindex' => '2' );

		$this->assertFalse( ( new Noindex_Resolver() )->is_noindex( WPTestStub::$posts[0] ) );
	}

	public function test_detects_rank_math_noindex(): void {
		WPTestStub::$post_meta[1] = array( 'rank_math_robots' => array( 'noindex' ) );

		$this->assertTrue( ( new Noindex_Resolver() )->is_noindex( WPTestStub::$posts[0] ) );
	}

	public function test_detects_seopress_noindex(): void {
		WPTestStub::$post_meta[1] = array( '_seopress_robots_index' => 'yes' );

		$this->assertTrue( ( new Noindex_Resolver() )->is_noindex( WPTestStub::$posts[0] ) );
	}

	public function test_detects_aioseo_noindex(): void {
		WPTestStub::$post_meta[1] = array( '_aioseo_noindex' => '1' );

		$this->assertTrue( ( new Noindex_Resolver() )->is_noindex( WPTestStub::$posts[0] ) );
	}

	public function test_filter_can_override_result(): void {
		add_filter( 'sevllms_is_noindex', static fn () => true );

		$this->assertTrue( ( new Noindex_Resolver() )->is_noindex( WPTestStub::$posts[0] ) );
	}
}
