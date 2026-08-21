<?php
/**
 * Resolves the "alternate language version" links shown below the "Seiten" list.
 *
 * @package SevStructuredLlmsTxt
 */

namespace SevStructuredLlmsTxt;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
 * Reads the admin-configured list of other network sites to link to (e.g. a
 * separate site per language) and resolves each into a label + URL pair.
 */
class Alternate_Sites {

	public const OPTION_NAME = 'sevllms_alternate_sites';

	/**
	 * Built-in fallback map from WordPress locale codes to a human-readable
	 * language name, used when a linked site has no custom label configured.
	 *
	 * @var array<string, string>
	 */
	private const LOCALE_LABELS = array(
		'de_DE' => 'German',
		'de_AT' => 'German',
		'de_CH' => 'German',
		'en_US' => 'English',
		'en_GB' => 'English',
		'fr_FR' => 'French',
		'es_ES' => 'Spanish',
		'it_IT' => 'Italian',
		'nl_NL' => 'Dutch',
		'pl_PL' => 'Polish',
		'pt_PT' => 'Portuguese',
		'pt_BR' => 'Portuguese',
	);

	/**
	 * Resolves the configured alternate sites into label => URL pairs.
	 *
	 * Sites that no longer exist are silently skipped.
	 *
	 * @return array<string, string>
	 */
	public function resolve(): array {
		if ( ! is_multisite() ) {
			return array();
		}

		$configured = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $configured ) ) {
			return array();
		}

		$resolved = array();

		foreach ( $configured as $entry ) {
			$site_id = isset( $entry['site_id'] ) ? (int) $entry['site_id'] : 0;

			if ( $site_id <= 0 || ! get_site( $site_id ) ) {
				continue;
			}

			$label = isset( $entry['label'] ) ? trim( (string) $entry['label'] ) : '';

			if ( '' === $label ) {
				$label = $this->label_from_locale( $site_id );
			}

			$url = get_home_url( $site_id, 'llms.txt' );

			$resolved[ $label ] = $url;
		}

		return $resolved;
	}

	/**
	 * Derives a human-readable label from the target site's own locale.
	 *
	 * @param int $site_id Target site ID.
	 * @return string
	 */
	private function label_from_locale( int $site_id ): string {
		switch_to_blog( $site_id );
		$locale = get_locale();
		restore_current_blog();

		$label = self::LOCALE_LABELS[ $locale ] ?? $locale;

		/**
		 * Filters the auto-derived label for an alternate-site llms.txt link.
		 *
		 * @param string $label   The derived label.
		 * @param int    $site_id The target site's ID.
		 * @param string $locale  The target site's locale.
		 */
		return (string) apply_filters( 'sevllms_alternate_site_label', $label, $site_id, $locale );
	}
}
