<?php
/**
 * Adds the "Exclude from llms.txt" checkbox to posts and pages.
 *
 * @package SevStructuredLlmsTxt
 */

namespace SevStructuredLlmsTxt;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
 * Registers the exclude checkbox meta box on posts and pages, and persists
 * its value.
 */
class Post_Meta {

	public const META_KEY = '_sevllms_exclude';

	private const NONCE_ACTION = 'sevllms_save_exclude';

	private const NONCE_NAME = 'sevllms_exclude_nonce';

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_post', array( $this, 'save' ) );
		add_action( 'save_post_page', array( $this, 'save' ) );
	}

	/**
	 * Adds the meta box to the post and page edit screens.
	 *
	 * @return void
	 */
	public function add_meta_box(): void {
		foreach ( array( 'post', 'page' ) as $post_type ) {
			add_meta_box(
				'sevllms-exclude',
				__( 'llms.txt', 'sev-structured-llms-txt' ),
				array( $this, 'render' ),
				$post_type,
				'side'
			);
		}
	}

	/**
	 * Renders the exclude checkbox.
	 *
	 * @param \WP_Post $post The post or page being edited.
	 * @return void
	 */
	public function render( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		$excluded = '1' === get_post_meta( $post->ID, self::META_KEY, true );
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::META_KEY ); ?>" value="1" <?php checked( $excluded ); ?> />
			<?php esc_html_e( 'Exclude from llms.txt', 'sev-structured-llms-txt' ); ?>
		</label>
		<?php
	}

	/**
	 * Persists the exclude checkbox value.
	 *
	 * @param int $post_id The post being saved.
	 * @return void
	 */
	public function save( int $post_id ): void {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! empty( $_POST[ self::META_KEY ] ) ) {
			update_post_meta( $post_id, self::META_KEY, '1' );
		} else {
			delete_post_meta( $post_id, self::META_KEY );
		}
	}
}
