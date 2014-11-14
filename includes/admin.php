<?php

/**
 * User Search Admin
 *
 * @package User Search
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'User_Search_Admin' ) ) :
/**
 * User Search Admin Class
 *
 * @since 1.0.0
 */
class User_Search_Admin {

	/**
	 * Setup class structure
	 *
	 * @since 1.0.0
	 *
	 * @uses User_Search_Admin::setup_actions()
	 */
	public function __construct() {
		$this->setup_actions();
	}

	/** Private Methods *******************************************************/

	/**
	 * Setup the default hooks and actions
	 *
	 * @since 1.0.0
	 */
	private function setup_actions() {
		add_action( 'admin_init',          array( $this, 'register_admin_settings' )        );
		add_action( 'admin_menu',          array( $this, 'admin_menu'              )        );
		add_filter( 'plugin_action_links', array( $this, 'plugin_action_links'     ), 10, 2 );
	}

	/** Public Methods ********************************************************/

	/**
	 * Create plugin admin menu
	 *
	 * @since 1.0.0
	 *
	 * @uses add_users_page()
	 */
	public function admin_menu() {

		// Bail if user is uncapable
		if ( ! current_user_can( 'edit_users' ) )
			return;

		// Add users submenu page
		$hook = add_users_page( __( 'User Search', 'user-search' ), __( 'Search', 'user-search' ), 'edit_users', 'user-search', array( $this, 'admin_page' ) );

		// Setup page hooks
		add_action( "load-$hook", array( $this, 'admin_page_load' ) );
	}

	/**
	 * Run logic on plugin admin page load
	 *
	 * @since 1.0.0
	 */
	public function admin_page_load() {
		$us = user_search();

		// When action is set
		if ( isset( $_REQUEST['action'] ) && ( $doaction = $_REQUEST['action'] ) ) {
			check_admin_referer( 'user-search' );

			// Sanitize sendback
			$sendback = remove_query_arg( array( 'message', 'action', '_wpnonce' ), wp_get_referer() );

			// Check action
			switch ( $doaction ) {

				// Sync user posts
				case 'sync_users' :

					// Update all users
					if ( isset( $_GET['all'] ) && $_GET['all'] ) {

						// Walk all users
						foreach ( get_users() as $user ) {
							$post_id = $us->sync_user_to_post( $user->ID );
						}
						$msg = 2;

					// Update only current user posts
					} else {
						$us->update_user_posts();
						$msg = 1;
					}

					$sendback = add_query_arg( 'message', $msg, $sendback );
					break;

				// Delete all posts
				case 'delete_posts' :

					// Walk all users
					foreach ( get_users() as $user ) {
						$post_id = $us->remove_user_post( $user->ID );
					}

					$sendback = add_query_arg( 'message', 3, $sendback );
					break;

				// Default hookable
				default :
					$sendback = apply_filters( 'us_admin_page_load_action', $sendback, $doaction );
					break;
			}

			// Redirect
			wp_redirect( $sendback );
			exit;
		}
	}

	/**
	 * Display plugin admin page
	 *
	 * @since 1.0.0
	 */
	public function admin_page() {

		// Setup page vars
		$_SERVER['REQUEST_URI'] = remove_query_arg( array( 'message' ), $_SERVER['REQUEST_URI'] ); ?>

		<div class="wrap">
			<h2><?php _e( 'User Search', 'user-search' ); ?></h2>

			<?php $this->admin_page_messages(); ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'user-search' ); ?>
				<?php do_settings_sections( 'user-search' ); ?>
				<?php submit_button(); ?>
			</form>
		</div>

		<?php
	}

	/**
	 * Display plugin admin page messages
	 *
	 * @since 1.0.0
	 */
	public function admin_page_messages() {

		// Bail if no message is set
		if ( ! isset( $_GET['message'] ) )
			return;

		$message  = (int) $_GET['message'];
		$messages = apply_filters( 'us_admin_page_messages', array(
			0 => '', // Empty on purpose
			1 => __( 'All current user posts are synced successfully and ready for search.', 'user-search' ),
			2 => __( 'All users are setup successfully and ready for search.', 'user-search' ),
			3 => __( 'All users are successfully removed from search.', 'user-search' ),
		) );

		// Output message
		if ( isset( $messages[ $message ] ) ) {
			$type = isset( $_GET['error'] ) && $_GET['error'] ? 'error' : 'updated'; ?>

			<div class="message <?php echo $type; ?>">
				<p><?php echo $messages[$message]; ?></p>
			</div>

			<?php
		}
	}

	/** Settings **************************************************************/

	/**
	 * Register the settings
	 *
	 * @uses add_settings_section() To add our own settings section
	 * @uses add_settings_field() To add various settings fields
	 * @uses register_setting() To register various settings
	 * @todo Put fields into multidimensional array
	 */
	public static function register_admin_settings() {

		// Bail if no sections available
		$sections = us_admin_get_settings_sections();
		if ( empty( $sections ) )
			return false;

		// Loop through sections
		foreach ( (array) $sections as $section_id => $section ) {

			// Only add section and fields if section has fields
			$fields = us_admin_get_settings_fields_for_section( $section_id );
			if ( empty( $fields ) )
				continue;

			// Add the section
			add_settings_section( $section_id, $section['title'], $section['callback'], $section['page'] );

			// Loop through fields for this section
			foreach ( (array) $fields as $field_id => $field ) {

				// Add the field
				if ( ! empty( $field['callback'] ) && ! empty( $field['title'] ) ) {
					add_settings_field( $field_id, $field['title'], $field['callback'], $section['page'], $section_id, $field['args'] );
				}

				// Register the setting
				if ( false !== $field['sanitize_callback'] ) {
					register_setting( $section['page'], $field_id, $field['sanitize_callback'] );
				}
			}
		}
	}

	/**
	 * Add extra links to plugins area
	 *
	 * @param array $links Links array in which we would prepend our link
	 * @param string $file Current plugin basename
	 * @return array Processed links
	 */
	public static function plugin_action_links( $links, $file ) {

		// Only for User Search
		if ( user_search()->basename == $file ) {

			// Settings
			if ( current_user_can( 'manage_options' ) ) {
				$links['settings'] = '<a href="' . add_query_arg( array( 'page' => 'user-search' ), admin_url( 'users.php' ) ) . '">' . esc_html__( 'Settings', 'user-search' ) . '</a>';
			}
		}

		return $links;
	}
}

/**
 * Setup User Search admin domain
 *
 * @since 1.0.0
 *
 * @uses User_Search_Admin
 */
function user_search_admin() {
	user_search()->admin = new User_Search_Admin;
}

endif; // class_exists
