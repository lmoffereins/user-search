<?php

/**
 * User Search Settings Functions
 *
 * @package User Search
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Get the User Search settings sections
 *
 * @since 1.0.0
 *
 * @return array Settings sections
 */
function us_admin_get_settings_sections() {
	return (array) apply_filters( 'us_admin_get_settings_sections', array(
		'us_main_settings' => array(
			'title'    => __( 'Main Settings', 'user-search' ),
			'callback' => 'us_admin_setting_callback_main_section',
			'page'     => 'user-search',
		),
	) );
}

/**
 * Get all of the settings fields
 *
 * @since 1.0.0
 *
 * @return array Settings fields
 */
function us_admin_get_settings_fields() {
	return (array) apply_filters( 'us_admin_get_settings_fields', array(

		/** Features Section **************************************************/

		'us_main_settings' => array(

			// Synchronize users and posts
			'us_sync_users' => array(
				'title'             => __( 'Synchronize', 'user-search' ),
				'callback'          => 'us_admin_setting_callback_sync_users',
				'sanitize_callback' => false,
				'args'              => array()
			),

		),
	) );
}

/**
 * Get settings fields by section
 *
 * @since 1.0.0
 *
 * @param string $section_id
 * @return mixed False if section is invalid, array of fields otherwise.
 */
function us_admin_get_settings_fields_for_section( $section_id = '' ) {

	// Bail if section is empty
	if ( empty( $section_id ) )
		return false;

	$fields = us_admin_get_settings_fields();
	$retval = isset( $fields[$section_id] ) ? $fields[$section_id] : false;

	return (array) apply_filters( 'us_admin_get_settings_fields_for_section', $retval, $section_id );
}

/** Main **********************************************************************/

/**
 * Display the main settings section intro
 *
 * @since 1.0.0
 */
function us_admin_setting_callback_main_section() {
	// Nothing to show
}

/**
 * Display the sync users settings field
 *
 * @since 1.0.0
 */
function us_admin_setting_callback_sync_users() {

	// Get current user posts
	$posts     = get_posts( array( 'post_type' => user_search()->get_post_type(), 'posts_per_page' => -1 ) );
	$num_posts = count( $posts );
	$num_users = count( get_users() );

	// Setup field vars
	$nonce_url = wp_nonce_url( add_query_arg( 'page', 'user-search' ), 'user-search' ); ?>

	<p>
		<?php printf( __( 'You have %1$d out of %2$d users that are included in the post search.', 'user-search' ), $num_posts, $num_users ); ?>
	</p>
	<p>
		<a class="button-primary"   href="<?php echo add_query_arg( 'action', 'sync_users', $nonce_url ); ?>"><?php _e( 'Sync user posts',  'user-search' ); ?></a>
		<a class="button-secondary" href="<?php echo add_query_arg( array( 'action' => 'sync_users', 'all' => 1 ), $nonce_url ); ?>"><?php _e( 'Setup all user posts',  'user-search' ); ?></a>
		<a class="button-secondary" href="<?php echo add_query_arg( 'action', 'delete_posts', $nonce_url ); ?>"><?php _e( 'Remove all user posts', 'user-search' ); ?></a>
	</p>

	<?php
}
