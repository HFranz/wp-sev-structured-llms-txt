<?php
/**
 * Settings page under Settings → llms.txt.
 *
 * @package SevStructuredLlmsTxt
 */

namespace SevStructuredLlmsTxt;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
 * Adds the "Settings → llms.txt" admin page: tagline, category order, and
 * (on Multisite) alternate-language site links, plus a live preview.
 */
class Admin_Settings {

	private const PAGE_SLUG = 'sev-structured-llms-txt';

	/**
	 * Hook suffix of our settings page, as returned by add_options_page().
	 * Used to only enqueue our assets on that page.
	 *
	 * @var string|false
	 */
	private string|false $settings_page_hook = false;

	private Generator $generator;

	public function __construct( ?Generator $generator = null ) {
		$this->generator = $generator ?? new Generator();
	}

	/**
	 * Registers WordPress hooks. Call once during plugins_loaded.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'maybe_clear_cache_after_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_sevllms_purge_cache', array( $this, 'handle_purge_cache' ) );
	}

	/**
	 * Adds the settings page under the "Settings" menu.
	 *
	 * @return void
	 */
	public function add_settings_page(): void {
		$this->settings_page_hook = add_options_page(
			__( 'llms.txt', 'sev-structured-llms-txt' ),
			__( 'llms.txt', 'sev-structured-llms-txt' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Registers the settings fields with the Settings API.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::PAGE_SLUG,
			Generator::OPTION_TAGLINE,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => '',
			)
		);

		register_setting(
			self::PAGE_SLUG,
			Category_Order::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_category_order' ),
				'default'           => array(),
			)
		);

		register_setting(
			self::PAGE_SLUG,
			Product_Category_Order::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_product_category_order' ),
				'default'           => array(),
			)
		);

		register_setting(
			self::PAGE_SLUG,
			Alternate_Sites::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_alternate_sites' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Sanitizes the submitted category order/inclusion list: the checked
	 * checkboxes, in the order the browser submitted them (which matches the
	 * on-screen drag order), limited to categories that actually exist.
	 *
	 * @param mixed $value Raw submitted value.
	 * @return int[]
	 */
	public function sanitize_category_order( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$existing_ids = array_map(
			'strval',
			array_keys(
				get_terms(
					array(
						'taxonomy'   => 'category',
						'hide_empty' => false,
						'fields'     => 'id=>name',
					)
				)
			)
		);

		$ids = array();
		foreach ( $value as $raw_id ) {
			$id = (string) (int) $raw_id;
			if ( in_array( $id, $existing_ids, true ) && ! in_array( (int) $id, $ids, true ) ) {
				$ids[] = (int) $id;
			}
		}

		return $ids;
	}

	/**
	 * Sanitizes the submitted product category order/inclusion list, the same
	 * way sanitize_category_order() does for post categories. Returns an
	 * empty array if the "product_cat" taxonomy doesn't exist (WooCommerce
	 * not active).
	 *
	 * @param mixed $value Raw submitted value.
	 * @return int[]
	 */
	public function sanitize_product_category_order( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => Product_Category_Order::TAXONOMY,
				'hide_empty' => false,
				'fields'     => 'id=>name',
			)
		);

		if ( ! is_array( $terms ) ) {
			return array();
		}

		$existing_ids = array_map( 'strval', array_keys( $terms ) );

		$ids = array();
		foreach ( $value as $raw_id ) {
			$id = (string) (int) $raw_id;
			if ( in_array( $id, $existing_ids, true ) && ! in_array( (int) $id, $ids, true ) ) {
				$ids[] = (int) $id;
			}
		}

		return $ids;
	}

	/**
	 * Sanitizes the submitted alternate-sites repeater rows.
	 *
	 * @param mixed $value Raw submitted value.
	 * @return array<int, array{site_id: int, label: string}>
	 */
	public function sanitize_alternate_sites( mixed $value ): array {
		if ( ! is_array( $value ) || ! is_multisite() ) {
			return array();
		}

		$rows = array();

		foreach ( $value as $row ) {
			$site_id = isset( $row['site_id'] ) ? (int) $row['site_id'] : 0;

			if ( $site_id <= 0 || ! get_site( $site_id ) ) {
				continue;
			}

			$rows[] = array(
				'site_id' => $site_id,
				'label'   => isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '',
			);
		}

		return $rows;
	}

	/**
	 * Clears the cache after the Settings API redirects back here with
	 * "settings-updated" set, so the next /llms.txt request (and the preview
	 * on this page) reflects the just-saved settings immediately.
	 *
	 * Deliberately not tied to update_option_{$option} in Cache::register():
	 * that hook does not fire on a setting's very first save, and does not
	 * fire at all when an unchecked checkbox list submits no value.
	 *
	 * @return void
	 */
	public function maybe_clear_cache_after_save(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only check of our own already nonce-verified redirect flag from options.php; nothing is written here.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		if ( self::PAGE_SLUG !== $page || ! isset( $_GET['settings-updated'] ) ) {
			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		( new Cache() )->delete();
	}

	/**
	 * Enqueues jQuery UI Sortable (bundled with WordPress core) plus our own
	 * inline admin script, only on our settings page.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( $hook_suffix !== $this->settings_page_hook ) {
			return;
		}

		wp_enqueue_script( 'jquery-ui-sortable' );

		wp_register_script( 'sevllms-admin-settings', false, array( 'jquery', 'jquery-ui-sortable' ), SEVLLMS_VERSION, true );
		wp_enqueue_script( 'sevllms-admin-settings' );
		wp_add_inline_script(
			'sevllms-admin-settings',
			"jQuery(function($){
				$('#sevllms-category-order, #sevllms-product-category-order').sortable({ handle: '.sevllms-drag-handle' });

				var addRow = function(){
					var template = $('#sevllms-alternate-site-template').html();
					var index = Date.now();
					$('#sevllms-alternate-sites-rows').append(template.replace(/__INDEX__/g, index));
				};
				$('#sevllms-add-alternate-site').on('click', function(e){
					e.preventDefault();
					addRow();
				});
				$('#sevllms-alternate-sites-rows').on('click', '.sevllms-remove-row', function(e){
					e.preventDefault();
					$(this).closest('.sevllms-alternate-site-row').remove();
				});
			});"
		);

		wp_register_style( 'sevllms-admin-settings', false, array(), SEVLLMS_VERSION );
		wp_enqueue_style( 'sevllms-admin-settings' );
		wp_add_inline_style(
			'sevllms-admin-settings',
			'.sevllms-settings ul.sevllms-term-order { max-width: 480px; }
			.sevllms-settings .sevllms-term-order li { display: flex; align-items: center; gap: 8px; padding: 4px 8px; background: #fff; border: 1px solid #dcdcde; margin-bottom: 4px; }
			.sevllms-settings .sevllms-drag-handle { cursor: move; color: #787c82; }
			.sevllms-settings .sevllms-alternate-site-row { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
			.sevllms-settings pre.sevllms-preview { max-height: 400px; overflow: auto; background: #fff; border: 1px solid #dcdcde; padding: 12px; }'
		);
	}

	/**
	 * Renders the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap sevllms-settings">
			<h1><?php esc_html_e( 'llms.txt', 'sev-structured-llms-txt' ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: %s: the llms.txt URL. */
					esc_html__( 'Available at %s.', 'sev-structured-llms-txt' ),
					'<a href="' . esc_url( home_url( 'llms.txt' ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( home_url( 'llms.txt' ) ) . '</a>'
				);
				?>
			</p>

			<form action="options.php" method="post">
				<?php
				settings_fields( self::PAGE_SLUG );
				$this->render_tagline_field();
				$this->render_category_order_field();
				$this->render_product_category_order_field();
				$this->render_alternate_sites_field();
				submit_button( __( 'Save settings', 'sev-structured-llms-txt' ) );
				?>
			</form>

			<hr />

			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="sevllms_purge_cache" />
				<?php wp_nonce_field( 'sevllms_purge_cache' ); ?>
				<?php submit_button( __( 'Clear cache', 'sev-structured-llms-txt' ), 'secondary' ); ?>
			</form>

			<h2><?php esc_html_e( 'Preview', 'sev-structured-llms-txt' ); ?></h2>
			<pre class="sevllms-preview"><?php echo esc_html( $this->generator->generate() ); ?></pre>
		</div>
		<?php
	}

	/**
	 * Renders the tagline textarea field.
	 *
	 * @return void
	 */
	private function render_tagline_field(): void {
		$tagline = get_option( Generator::OPTION_TAGLINE, '' );
		?>
		<h2><?php esc_html_e( 'Tagline', 'sev-structured-llms-txt' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Shown as the blockquote below the site title. Leave empty to use the site tagline.', 'sev-structured-llms-txt' ); ?>
		</p>
		<textarea name="<?php echo esc_attr( Generator::OPTION_TAGLINE ); ?>" rows="2" class="large-text"><?php echo esc_textarea( $tagline ); ?></textarea>
		<?php
	}

	/**
	 * Renders the sortable, checkable category order list.
	 *
	 * @return void
	 */
	private function render_category_order_field(): void {
		$rows = ( new Category_Order() )->admin_rows();
		?>
		<h2><?php esc_html_e( 'Post categories', 'sev-structured-llms-txt' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Choose which categories appear as a "Posts" subsection, and drag to set their order.', 'sev-structured-llms-txt' ); ?>
		</p>
		<ul id="sevllms-category-order" class="sevllms-term-order">
			<?php foreach ( $rows as $row ) : ?>
				<li>
					<span class="sevllms-drag-handle dashicons dashicons-menu"></span>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( Category_Order::OPTION_NAME ); ?>[]" value="<?php echo esc_attr( (string) $row['term_id'] ); ?>" <?php checked( $row['included'] ); ?> />
						<?php echo esc_html( $row['name'] ); ?>
					</label>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Renders the sortable, checkable product category order list, only if
	 * the "product_cat" taxonomy exists (i.e. WooCommerce is active).
	 *
	 * @return void
	 */
	private function render_product_category_order_field(): void {
		if ( ! taxonomy_exists( Product_Category_Order::TAXONOMY ) ) {
			return;
		}

		$rows = ( new Product_Category_Order() )->admin_rows();
		?>
		<h2><?php esc_html_e( 'Product categories', 'sev-structured-llms-txt' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Choose which product categories appear as a "Products" subsection, and drag to set their order.', 'sev-structured-llms-txt' ); ?>
		</p>
		<ul id="sevllms-product-category-order" class="sevllms-term-order">
			<?php foreach ( $rows as $row ) : ?>
				<li>
					<span class="sevllms-drag-handle dashicons dashicons-menu"></span>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( Product_Category_Order::OPTION_NAME ); ?>[]" value="<?php echo esc_attr( (string) $row['term_id'] ); ?>" <?php checked( $row['included'] ); ?> />
						<?php echo esc_html( $row['name'] ); ?>
					</label>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Renders the alternate-language sites repeater, only if this is a
	 * Multisite network.
	 *
	 * @return void
	 */
	private function render_alternate_sites_field(): void {
		if ( ! is_multisite() ) {
			return;
		}

		$configured = get_option( Alternate_Sites::OPTION_NAME, array() );
		$sites      = get_sites( array( 'number' => 0 ) );
		?>
		<h2><?php esc_html_e( 'Alternate language sites', 'sev-structured-llms-txt' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Link to this llms.txt of other sites in this network (e.g. one site per language). The label is derived from the target site\'s language unless you set one.', 'sev-structured-llms-txt' ); ?>
		</p>
		<div id="sevllms-alternate-sites-rows">
			<?php foreach ( (array) $configured as $index => $row ) : ?>
				<?php $this->render_alternate_site_row( (string) $index, (int) ( $row['site_id'] ?? 0 ), (string) ( $row['label'] ?? '' ), $sites ); ?>
			<?php endforeach; ?>
		</div>
		<p><button type="button" id="sevllms-add-alternate-site" class="button"><?php esc_html_e( 'Add site', 'sev-structured-llms-txt' ); ?></button></p>

		<script type="text/template" id="sevllms-alternate-site-template">
			<?php $this->render_alternate_site_row( '__INDEX__', 0, '', $sites ); ?>
		</script>
		<?php
	}

	/**
	 * Renders one alternate-site repeater row.
	 *
	 * @param string     $index          Repeater row index (or the __INDEX__ placeholder).
	 * @param int        $selected_site  Currently selected site ID.
	 * @param string     $label          Currently configured custom label.
	 * @param \WP_Site[] $sites          All sites in the network.
	 * @return void
	 */
	private function render_alternate_site_row( string $index, int $selected_site, string $label, array $sites ): void {
		$option = Alternate_Sites::OPTION_NAME;
		?>
		<div class="sevllms-alternate-site-row">
			<select name="<?php echo esc_attr( $option ); ?>[<?php echo esc_attr( $index ); ?>][site_id]">
				<option value="0"><?php esc_html_e( '— Select a site —', 'sev-structured-llms-txt' ); ?></option>
				<?php foreach ( $sites as $site ) : ?>
					<option value="<?php echo esc_attr( (string) $site->blog_id ); ?>" <?php selected( $selected_site, (int) $site->blog_id ); ?>>
						<?php echo esc_html( $site->blogname ? $site->blogname : $site->domain . $site->path ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<input type="text" name="<?php echo esc_attr( $option ); ?>[<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $label ); ?>" placeholder="<?php esc_attr_e( 'Label (optional, e.g. English)', 'sev-structured-llms-txt' ); ?>" class="regular-text" />
			<button type="button" class="button-link sevllms-remove-row"><?php esc_html_e( 'Remove', 'sev-structured-llms-txt' ); ?></button>
		</div>
		<?php
	}

	/**
	 * Handles the "Clear cache" button.
	 *
	 * @return void
	 */
	public function handle_purge_cache(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'sev-structured-llms-txt' ) );
		}

		check_admin_referer( 'sevllms_purge_cache' );

		( new Cache() )->delete();

		wp_safe_redirect( add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'options-general.php' ) ) );
		exit;
	}
}
