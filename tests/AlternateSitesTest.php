<?php

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;
use SevStructuredLlmsTxt\Alternate_Sites;

final class AlternateSitesTest extends TestCase {

	protected function setUp(): void {
		WPTestStub::reset();
	}

	public function test_returns_empty_array_when_not_multisite(): void {
		WPTestStub::$is_multisite = false;
		WPTestStub::$options[ Alternate_Sites::OPTION_NAME ] = array( array( 'site_id' => 2, 'label' => '' ) );

		$this->assertSame( array(), ( new Alternate_Sites() )->resolve() );
	}

	public function test_derives_label_from_target_site_locale(): void {
		WPTestStub::$is_multisite = true;
		WPTestStub::$sites        = array( 2 => new stdClass() );
		WPTestStub::$site_locales = array( 2 => 'en_US' );
		WPTestStub::$options[ Alternate_Sites::OPTION_NAME ] = array(
			array( 'site_id' => 2, 'label' => '' ),
		);

		$resolved = ( new Alternate_Sites() )->resolve();

		$this->assertSame( array( 'English' => 'https://site2.example.com/llms.txt' ), $resolved );
	}

	public function test_manual_label_overrides_derived_one(): void {
		WPTestStub::$is_multisite = true;
		WPTestStub::$sites        = array( 2 => new stdClass() );
		WPTestStub::$site_locales = array( 2 => 'en_US' );
		WPTestStub::$options[ Alternate_Sites::OPTION_NAME ] = array(
			array( 'site_id' => 2, 'label' => 'US Site' ),
		);

		$resolved = ( new Alternate_Sites() )->resolve();

		$this->assertSame( array( 'US Site' => 'https://site2.example.com/llms.txt' ), $resolved );
	}

	public function test_unknown_locale_falls_back_to_locale_code(): void {
		WPTestStub::$is_multisite = true;
		WPTestStub::$sites        = array( 2 => new stdClass() );
		WPTestStub::$site_locales = array( 2 => 'xx_YY' );
		WPTestStub::$options[ Alternate_Sites::OPTION_NAME ] = array(
			array( 'site_id' => 2, 'label' => '' ),
		);

		$resolved = ( new Alternate_Sites() )->resolve();

		$this->assertSame( array( 'xx_YY' => 'https://site2.example.com/llms.txt' ), $resolved );
	}

	public function test_skips_entries_for_sites_that_no_longer_exist(): void {
		WPTestStub::$is_multisite = true;
		WPTestStub::$sites        = array();
		WPTestStub::$options[ Alternate_Sites::OPTION_NAME ] = array(
			array( 'site_id' => 99, 'label' => 'Gone' ),
		);

		$this->assertSame( array(), ( new Alternate_Sites() )->resolve() );
	}
}
