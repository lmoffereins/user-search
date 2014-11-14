<?php

/**
 * User Search BuddyPress Functions
 *
 * @package User Search
 * @subpackage Extend
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'User_Search_BuddyPress' ) ) :
/**
 * User Search BuddyPress Class
 *
 * @since 1.0.0
 */
class User_Search_BuddyPress {

	/**
	 * Setup class structure
	 *
	 * @since 1.0.0
	 *
	 * @uses User_Search_BuddyPress::setup_actions()
	 */
	public function __construct() {
		$this->setup_actions();
	}

	/** Private Methods *******************************************************/

	/**
	 * Setup the default hooks and actions
	 *
	 * @since 1.0.0
	 * @access private
	 * @uses add_action() To add various actions
	 */
	private function setup_actions() {

		// Search
		add_filter( 'posts_where', array( $this, 'member_where_query' ), 10, 2 );

		// Settings
		add_filter( 'us_admin_get_settings_fields', array( $this, 'add_settings_fields' ) );

		// Template
		// - set $member_template global?
		// Filter
		// - bp_get_member_user_id
		// - bp_displayed_user_id
	}

	/** Search ****************************************************************/

	/**
	 * Manipulate query where clause to include user groups
	 *
	 * @since 1.0.0
	 *
	 * @param string $where Where query clause
	 * @param WP_Query $query Query object
	 * @return string Where query clause
	 */
	public function member_where_query( $where, $query ) {
		global $wpdb, $bp;

		// Searching with groups
		if ( $query->is_search && bp_is_active( 'groups' ) ) {

			// Get the groups
			$groups = $this->get_searchable_groups();

			// Require groups
			if ( ! empty( $groups ) ) {
				$member_query = '';

				// Handle groupless users
				if ( in_array( -1, $groups ) ) {

					// Where a user is not a member of anything
					$member_query = " $wpdb->posts.post_author NOT IN ( SELECT user_id FROM {$bp->groups->table_name_members} )";

					if ( count( $groups ) > 1 )
						$member_query .= " OR";
				}

				// Remove groupless reference
				$_groups = array_diff( $groups, array( -1 ) );

				// Handle regular groups
				if ( ! empty( $_groups ) ) {
					$member_query .= sprintf( " $wpdb->posts.post_author IN ( SELECT user_id FROM {$bp->groups->table_name_members} WHERE group_id IN (%s) )", implode( ',', $_groups ) );
				}

				// Query for group members
				$post_type = user_search()->get_post_type();
				$where .= $wpdb->prepare( " AND ( $wpdb->posts.post_type != %s OR ( $wpdb->posts.post_type = %s AND ( $member_query ) ) )", $post_type, $post_type );
			}
		}

		return $where;
	}

	/** Settings **************************************************************/

	/**
	 * Add BP settings fields
	 *
	 * @since 1.0.0
	 *
	 * @param array $settings Settings fields
	 * @return array Settings fields
	 */
	public function add_settings_fields( $settings ) {

		// Using groups
		if ( bp_is_active( 'groups' ) ) {

			// Has groups
			if ( ( $groups = groups_get_groups( array( 'type' => 'alphabetical', 'show_hidden' => true ) ) ) && ! empty( $groups['groups'] ) ) {

				// Searchable groups
				$settings['us_main_settings']['us_bp_searchable_groups'] = array(
					'title'             => __( 'BP User Groups', 'user-search' ),
					'callback'          => array( $this, 'setting_callback_searchable_groups' ),
					'sanitize_callback' => array( $this, 'sanitize_group_ids' ),
					'args'              => array()
				);
			}
		}

		return $settings;
	}

	/**
	 * Display the settings field for searchable groups
	 *
	 * @since 1.0.0
	 */
	public function setting_callback_searchable_groups() {

		// Get groups
		$selected = $this->get_searchable_groups();
		$groups   = groups_get_groups( array( 'type' => 'alphabetical', 'show_hidden' => true ) ); ?>

		<p><?php _e( 'Select of which groups only members are selected for search. When no groups are checked, all users are used in the search.', 'user-search' ); ?></p>

		<?php if ( ! empty( $groups['groups'] ) ) : ?>

			<ul>
				<li><label><input type="checkbox" name="us_bp_searchable_groups[]" value="-1" <?php checked( in_array( -1, $selected ) ); ?>> <?php _e( 'Users without any group', 'user-search' ); ?></label></li>

				<?php foreach( $groups['groups'] as $group ) : ?>

				<li><label><input type="checkbox" name="us_bp_searchable_groups[]" value="<?php echo $group->id; ?>" <?php checked( in_array( $group->id, $selected ) ); ?>> <?php echo $group->name; ?></label></li>

				<?php endforeach; ?>
			</ul>

		<?php else : ?>

			<p><?php _e( 'There were no groups found to select.', 'user-search' ); ?></p>

		<?php endif;
	}

	/**
	 * Return the sanitized group ids input
	 *
	 * @since 1.0.0
	 *
	 * @param array $input Group ids
	 * @return array Sanitized ids
	 */
	public function sanitize_group_ids( $input ) {
		return array_filter( array_map( 'intval', (array) $input ) );
	}

	/**
	 * Return the saved option of searchable groups
	 *
	 * @since 1.0.0
	 *
	 * @return array Searchable groups
	 */
	public function get_searchable_groups() {
		return (array) apply_filters( 'user_search_bp_get_searchable_groups', get_option( 'us_bp_searchable_groups', array() ) );
	}

	/** Template **************************************************************/

	// fns
}

endif; // class_exists
