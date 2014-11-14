<?php

/**
 * User Search Extend
 *
 * @package User Search
 * @subpackage Extend
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Setup extending functions for BuddyPress
 *
 * @since 1.0.0
 *
 * @return If BuddyPress is not active
 */
function user_search_setup_buddypress() {

	// BuddyPress is active
	if ( ! function_exists( 'buddypress' ) )
		return;

	if ( ! buddypress() || buddypress()->maintenance_mode )
		return;

	require( user_search()->includes_dir . 'extend/buddypress.php' );

	user_search()->extend->bp = new User_Search_BuddyPress;
}
