<?php

/**
 * The User Search Plugin
 *
 * @package User Search
 * @subpackage Main
 */

/**
 * Plugin Name:       User Search
 * Description:       Search for registered site users from the front-end.
 * Plugin URI:        https://github.com/lmoffereins/user-search/
 * Version:           1.0.0
 * Author:            Laurens Offereins
 * Author URI:        https://github.com/lmoffereins/
 * Text Domain:       user-search
 * Domain Path:       /languages/
 * GitHub Plugin URI: lmoffereins/user-search
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'User_Search' ) ) :
/**
 * Main Plugin Class
 *
 * @since 1.0.0
 */
final class User_Search {

	/**
	 * The user post type name
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $post_type_id = '';

	/** Singleton *************************************************************/

	/**
	 * Setup and return singleton pattern
	 *
	 * @since 1.0.0
	 *
	 * @uses User_Search::setup_globals()
	 * @uses User_Search::includes()
	 * @uses User_Search::setup_actions()
	 * @return The one single User_Search
	 */
	public static function instance() {

		// Declare static var locally
		static $instance = null;

		if ( null === $instance ) {
			$instance = new User_Search;
			$instance->setup_globals();
			$instance->includes();
			$instance->setup_actions();
		}

		return $instance;
	}

	/**
	 * Dummy constructor method
	 *
	 * @since 1.0.0
	 */
	private function __construct(){ /* Do nothing here */ }

	/** Private Methods *******************************************************/

	/**
	 * Setup default actions and filters
	 *
	 * @since 1.0.0
	 */
	private function setup_globals() {

		/** Paths *************************************************************/

		$this->file       = __FILE__;
		$this->basename   = plugin_basename( $this->file );
		$this->plugin_dir = plugin_dir_path( $this->file );
		$this->plugin_url = plugin_dir_url ( $this->file );

		// Includes
		$this->includes_dir  = trailingslashit( $this->plugin_dir . 'includes'  );
		$this->includes_url  = trailingslashit( $this->plugin_url . 'includes'  );

		/** Identifiers *******************************************************/

		$this->post_type_id = apply_filters( 'user_search_post_type_id', 'us_user' );

		/** Misc **************************************************************/

		$this->extend = new stdClass();
	}

	/**
	 * Setup default actions and filters
	 *
	 * @since 1.0.0
	 */
	private function includes() {
		require( $this->includes_dir . 'extend.php' );

		// Admin files
		if ( is_admin() ) {
			require( $this->includes_dir . 'admin.php'    );
			require( $this->includes_dir . 'settings.php' );
		}
	}

	/**
	 * Setup default actions and filters
	 *
	 * @since 1.0.0
	 */
	private function setup_actions() {

		// Plugin
		add_action( 'init', array( $this, 'register_post_type' ) );

		// Search
		add_filter( 'posts_join',                              array( $this, 'search_join_query'    ), 10, 2 );
		add_filter( 'posts_search',                            array( $this, 'search_search_query'  ), 10, 2 );
		add_filter( 'posts_groupby',                           array( $this, 'search_groupby_query' ), 10, 2 );
		add_filter( 'user_search_search_query_search_columns', array( $this, 'add_search_columns'   ), 10, 3 );

		// User
		add_action( 'user_register',                  array( $this, 'sync_user_to_post'     ), 50    ); // After BP XProfile WP User Sync
		add_action( 'profile_update',                 array( $this, 'sync_user_to_post'     ), 50    ); // After BP XProfile WP User Sync
		add_action( 'delete_user',                    array( $this, 'remove_user_post'      )        ); // To prevent reassigning
		add_filter( 'post_types_to_delete_with_user', array( $this, 'remove_post_with_user' ), 10, 2 );

		// Post
		add_filter( 'post_type_link',      array( $this, 'post_permalink'    ), 10, 4 );
		add_filter( 'get_edit_post_link',  array( $this, 'post_edit_link'    ), 10, 3 );
		add_filter( 'get_post_metadata',   array( $this, 'post_thumbnail_id' ), 10, 4 );
		add_filter( 'post_thumbnail_html', array( $this, 'post_thumbnail'    ), 10, 5 );
		add_filter( 'map_meta_cap',        array( $this, 'map_meta_cap'      ), 10, 4 );

		// Content
		add_filter( 'user_search_get_user_content', 'wpautop' );

		// Extend
		add_action( 'init', 'user_search_setup_buddypress' );

		// Admin
		if ( is_admin() ) {
			add_action( 'init', 'user_search_admin' );
		}
	}

	/** Public Methods ********************************************************/

	/**
	 * Return the user post type name
	 *
	 * @since 1.0.0
	 *
	 * @return string Post type name
	 */
	public function get_post_type() {
		return $this->post_type_id;
	}

	/**
	 * Setup user search post type
	 *
	 * @since 1.0.0
	 *
	 * @uses register_post_type()
	 * @uses apply_filters() Calls 'user_search_register_post_type'
	 */
	public function register_post_type() {

		// Register user post type
		register_post_type(
			$this->get_post_type(),
			apply_filters( 'user_search_register_post_type', array(
				'labels'              => array( 'name' => __( 'User Search Users', 'user-search' ) ),
				'rewrite'             => array(),
				'supports'            => array(),
				'description'         => __( 'Searchable User', 'user-search' ),
				'menu_position'       => 0,
				'has_archive'         => false,
				'exclude_from_search' => false,
				'show_in_nav_menus'   => false,
				'public'              => true,
				'show_ui'             => false,
				'can_export'          => false,
				'hierarchical'        => false,
				'query_var'           => true,
				'menu_icon'           => ''
			) )
		);
	}

	/** Search ****************************************************************/

	/**
	 * Manipulate query join clause to include user search
	 *
	 * @since 1.0.0
	 *
	 * @param string $join Join query clause
	 * @param WP_Query $query Query object
	 * @return string Join query clause
	 */
	public function search_join_query( $join, $query ) {
		global $wpdb;

		// Searching
		if ( $query->is_search && ! empty( $query->query_vars['search_terms'] ) ) {

			// Walk all search terms
			foreach ( (array) $query->query_vars['search_terms'] as $i => $term ) {

				// Connect user meta table
				$join .= $wpdb->prepare( " LEFT JOIN $wpdb->usermeta um{$i} ON ( $wpdb->posts.post_type = %s AND $wpdb->posts.post_author = um{$i}.user_id )", $this->get_post_type() );

				// BuddyPress XProfile
				if ( function_exists( 'buddypress' ) && bp_is_active( 'xprofile' ) ) {
					global $bp;

					// Connect XProfile Data table
					$join .= $wpdb->prepare( " LEFT JOIN {$bp->profile->table_name_data} bpxp{$i} ON ( $wpdb->posts.post_type = %s AND $wpdb->posts.post_author = bpxp{$i}.user_id )", $this->get_post_type() );
				}
			}
		}

		return $join;
	}

	/**
	 * Rewrite query search clause to include user search
	 *
	 * @since 1.0.0
	 *
	 * @see WP_Query::parse_search()
	 *
	 * @param string $search Search query clause
	 * @param WP_Query $query Query object
	 * @return string Search query clause
	 */
	public function search_search_query( $search, $query ) {
		global $wpdb;

		// Searching
		if ( $query->is_search && ! empty( $query->query_vars['search_terms'] ) ) {

			// Setup local vars
			$search = '';
			$n = ! empty( $query->query_vars['exact'] ) ? '' : '%';
			$searchand = '';

			// Walk all search terms
			foreach ( (array) $query->query_vars['search_terms'] as $i => $term ) {
				$term = $wpdb->esc_like( esc_sql( $term ) ); // Requires WP 4.0+
				if ( $n ) {
					$q['search_orderby_title'][] = "$wpdb->posts.post_title LIKE '%$term%'";
				}

				// Filter search columns. Defaults are post title and post content
				$columns = apply_filters( 'user_search_search_query_search_columns', array( "$wpdb->posts.post_title", "$wpdb->posts.post_content" ), $term, $query );

				$search .= "{$searchand}((" . implode( " LIKE '{$n}{$term}{$n}') OR (", $columns ) . " LIKE '{$n}{$term}{$n}'))";
				$searchand = ' AND ';
			}

			if ( ! empty( $search ) ) {
				$search = " AND ({$search}) ";
				if ( ! is_user_logged_in() )
					$search .= " AND ($wpdb->posts.post_password = '') ";
			}
		}

		return $search;
	}

	/**
	 * Add user search columns user meta and BP XProfile data
	 *
	 * @since 1.0.0
	 *
	 * @param array $columns Search columns
	 * @param string $term Search term
	 * @param WP_Query $query Query object
	 * @return array Search columns
	 */
	public function add_search_columns( $columns, $term, $query ) {

		// Find current term
		$i = array_search( $term, $query->query_vars['search_terms'] );

		// User meta
		$columns[] = "um{$i}.meta_value";

		// BuddyPress XProfile
		if ( function_exists( 'buddypress' ) && bp_is_active( 'xprofile' ) ) {
			$columns[] = "bpxp{$i}.value";
		}

		return $columns;
	}

	/**
	 * Rewrite query groupby clause to include user search
	 *
	 * @since 1.0.0
	 *
	 * @param string $groupby Group by query clause
	 * @param WP_Query $query Query object
	 * @return string Group by query clause
	 */
	public function search_groupby_query( $groupby, $query ) {
		global $wpdb;

		// Searching
		if ( $query->is_search ) {

			// Make results unique if not done already
			if ( empty( $groupby ) )
				$groupby .= " $wpdb->posts.ID";
		}

		return $groupby;
	}

	/** User ******************************************************************/

	/**
	 * Update user post to be in sync with the user's data
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID
	 * @return int User post ID
	 */
	public function sync_user_to_post( $user_id ) {

		// Bail if user does not exist
		if ( ! $user = get_userdata( $user_id ) )
			return false;

		// Bail if user is not to be synced
		if ( ! apply_filters( 'user_search_sync_user_to_post', true, $user_id ) )
			return;

		// Setup post arguments
		$args = array(
			'post_type'             => $this->get_post_type(),
			'post_author'           => $user->ID, // Key binder to user meta
			'post_title'            => $this->get_user_display_name( $user->ID ),
			'post_content'          => $this->get_user_content( $user->ID ),
			'post_status'           => 'publish',
			'post_parent'           => 0,
			'post_excerpt'          => $this->get_user_excerpt( $user->ID ),
			'post_content_filtered' => '',
			'post_mime_type'        => '',
			'post_password'         => '',
			'post_name'             => '', // Do not register a unique slug
			'guid'                  => $this->get_user_url( $user->ID ),
			'menu_order'            => 0,
			'pinged'                => '',
			'to_ping'               => '',
			'ping_status'           => 'closed',
			'comment_status'        => 'closed',
			'comment_count'         => 0
		);

		// Get user's post
		$post = get_posts( array(
			'author'    => $user->ID,
			'post_type' => $this->get_post_type()
		) );

		// Post exists, so update it
		if ( ! empty( $post ) ) {
			$args['ID'] = $post[0]->ID;
		}

		// Create/Update user post
		$post_id = wp_insert_post( apply_filters( 'user_search_sync_user_to_post_args', $args, $user, $post ) );

		// Hook after update
		do_action( 'user_search_update_user_post', $user, $post_id );

		return $post_id;
	}

	/**
	 * Run logic to update all user posts
	 *
	 * @since 1.0.0
	 */
	public function update_user_posts() {

		// Walk all user posts
		foreach ( $this->get_user_posts() as $post ) {
			$this->sync_user_to_post( $this->get_post_user_id( $post ) );
		}
	}

	/**
	 * Delete the user's post
	 *
	 * @since 1.0.0
	 *
	 * @uses wp_delete_post()
	 * @param int $user_id User ID
	 * @return bool Delete success
	 */
	public function remove_user_post( $user_id ) {
		$post = get_posts( array(
			'author'    => $user_id,
			'post_type' => $this->get_post_type()
		), true );

		if ( ! empty( $post ) ) {

			// Even though there should only be 1, walk all
			foreach ( $post as $p ) {
				wp_delete_post( $p->ID, true );
			}

			return true;
		} else {
			return false;
		}
	}

	/**
	 * Ensure that user search posts are removed with user deletion
	 *
	 * May be obsolete since the post will have been deleted when hooking
	 * into 'delete_user' with {@see User_Search::remove_user_post()}.
	 *
	 * @since 1.0.0
	 *
	 * @param array $post_types Post type names
	 * @param int $user_id User ID
	 * @return array Post types to delete with user
	 */
	public function remove_post_with_user( $post_types, $user_id ) {
		$post_type = $this->get_post_type();
		if ( ! in_array( $post_type, $post_types ) )
			$post_types[] = $post_type;
		return $post_types;
	}

	/** Template **************************************************************/

	/**
	 * Return the display name of the given user
	 *
	 * Supports BuddyPress display name logic, else defaults
	 * to the author display name.
	 *
	 * @since 1.0.0
	 * 
	 * @param int $user_id User ID
	 * @return string User display name
	 */
	public function get_user_display_name( $user_id ) {

		// Support BuddyPress
		if ( function_exists( 'buddypress' ) ) {
			$display_name = bp_core_get_user_displayname( $user_id );

		// Default to author posts url
		} else {
			$display_name = get_the_author_meta( 'display_name', $user_id );
		}

		return apply_filters( 'user_search_get_user_display_name', $display_name, $user_id );
	}

	/**
	 * Return the url of the given user
	 *
	 * Supports BuddyPress and bbPress profile urls, else
	 * defaults to the author posts url.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID
	 * @return string User url
	 */
	public function get_user_url( $user_id ) {

		// Support BuddyPress
		if ( function_exists( 'buddypress' ) ) {
			$url = bp_core_get_user_domain( $user_id );

		// Support bbPress
		} elseif ( function_exists( 'bbpress' ) ) {
			$url = bbp_get_user_profile_url( $user_id );

		// Default to author posts url
		} else {
			$url = get_author_posts_url( $user_id );
		}

		return apply_filters( 'user_search_get_user_url', $url, $user_id );
	}

	/**
	 * Return the url of the given user
	 *
	 * Supports BuddyPress and bbPress profile urls, else
	 * defaults to the author posts url.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID
	 * @return string User url
	 */
	public function get_user_edit_url( $user_id ) {

		// Support BuddyPress
		if ( function_exists( 'buddypress' ) ) {

			// BP rewrites the WP profile url
			$url = get_edit_profile_url( $user_id );

		// Support bbPress
		} elseif ( function_exists( 'bbpress' ) ) {
			$url = bbp_get_user_profile_edit_url( $user_id );

		// Default to admin profile page
		} else {

			// Editing another user
			if ( get_current_user_id() != $user_id ) {
				$url = add_query_arg( 'user_id', $user_id, admin_url( 'user-edit.php' ) );

			// Default to own profile
			} else {
				$url = get_edit_profile_url( $user_id );
			}
		}

		return apply_filters( 'user_search_get_user_edit_url', $url, $user_id );
	}

	/**
	 * Return the user description
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID
	 * @return string User content
	 */
	public function get_user_content( $user_id ) {
		return apply_filters( 'user_search_get_user_content', get_the_author_meta( 'description', $user_id ), $user_id );
	}

	/**
	 * Return the user excerpt
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID
	 * @return string User excerpt
	 */
	public function get_user_excerpt( $user_id ) {
		return apply_filters( 'user_search_get_user_excerpt', get_the_author_meta( 'description', $user_id ), $user_id );
	}

	/**
	 * Return the user avatar
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID
	 * @param string|int|array $size Avatar size name or array with dimensions
	 * @param string $attr Thumbnail element attributes
	 * @return string User avatar html
	 */
	public function get_user_avatar( $user_id, $size = '', $attr = '' ) {

		// Get size dimensions from other than array
		if ( ! is_array( $size ) && count( $size ) < 2 ) {

			// Single value array
			if ( is_array( $size ) ) {
				$size = reset( $size );
			}

			// Single value is passed
			if ( is_numeric( $size ) ) {
				$size = array( $size, $size );

			} else {
				global $_wp_additional_image_sizes;

				// Get the size dimensions from registered image size
				if ( isset( $_wp_additional_image_sizes[ $size ] ) ) {
					$size = $_wp_additional_image_sizes[ $size ];

				// Default to 'post-thumbnail' dimensions
				} else if ( isset( $_wp_additional_image_sizes[ 'post-thumbnail' ] ) ) {
					$size = $_wp_additional_image_sizes[ 'post-thumbnail' ];

				// Default to 96
				} else {
					$size = array( 96, 96 );
				}
			}
		}

		// Get width and height
		list( $width, $height ) = $size;

		// Support BuddyPress
		if ( function_exists( 'buddypress' ) ) {
			$avatar = bp_core_fetch_avatar( wp_parse_args( $attr, array(
				'item_id' => $user_id,
				'object'  => 'user',
				'type'    => 'full',
				'width'   => $width,
				'height'  => $height,
			) ) );

		// Default to WP avatar
		} else {

			// Get the lower of both dimensions as size
			$avatar = get_avatar( $user_id, $width < $height ? $width : $height );
		}

		return apply_filters( 'user_search_get_user_avatar', $avatar, $user_id, $size, $attr );
	}

	/** Post ******************************************************************/

	/**
	 * Return whether the post is a user post
	 *
	 * @since 1.0.0
	 *
	 * @param int|object $post Optional. Post ID or object
	 * @return bool Post is user post
	 */
	public function is_user_post( $post = '' ) {

		// Passed a post ID
		if ( is_numeric( $post ) ) {
			$post_id   = $post;
			$post_type = get_post_type( $post_id );

		// Passed a post object
		} else if ( is_object( $post ) ) {
			$post_id   = $post->ID;
			$post_type = $post->post_type;

		// Fetch current post
		} else {
			$post      = get_post();
			$post_id   = $post->ID;
			$post_type = $post->post_type;
		}

		// Post is of user post type
		$is = $this->get_post_type() == $post_type;

		return apply_filters( 'user_search_is_user_post', $is, $post_id );
	}

	/**
	 * Return a single or all user posts
	 *
	 * Defaults to returning all users posts.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id Optional. User ID
	 * @return object|array User post(s)
	 */
	public function get_user_posts( $user_id = 0 ) {

		// Setup query args
		$args = array( 'post_type' => $this->get_post_type() );
		if ( ! empty( $user_id ) ) {
			$args['author'] = (int) $user_id;
		}

		// Get posts
		$posts = get_posts( $args );

		// Return single or all posts
		if ( $user_id && ! empty( $posts ) ) {
			return $posts[0];
		} else {
			return $posts;
		}
	}

	/**
	 * Return the user post's user ID
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Post|int $post Post object or ID
	 * @return int User ID
	 */
	public function get_post_user_id( $post ) {

		// Post ID was provided
		if ( is_numeric( $post ) ) {
			$post = get_post( $post );
		}

		// User is stored in post author field
		$user_id = $post->post_author;

		return apply_filters( 'user_search_get_post_user_id', $user_id, $post );
	}

	/**
	 * Overwrite the post permalink for user posts
	 *
	 * @since 1.0.0
	 *
	 * @param string $permalink Post permalink
	 * @param WP_Post $post Post object
	 * @param bool $leavename
	 * @param bool $sample Whether this is a sample link
	 * @return string Post permalink
	 */
	public function post_permalink( $permalink, $post, $leavename, $sample ) {

		// Post is of user post type
		if ( ! $sample && $this->is_user_post( $post ) ) {

			// Set permalink to the user url
			$permalink = $this->get_user_url( $this->get_post_user_id( $post ) );
		}

		return $permalink;
	}

	/**
	 * Overwrite the post edit link for user posts
	 *
	 * @since 1.0.0
	 *
	 * @param string $edit_link Post edit link
	 * @param int $post Post ID
	 * @param string $context Whether to encode special chars
	 * @return string Post edit link
	 */
	public function post_edit_link( $edit_link, $post_id, $context ) {
		$post = get_post( $post_id );

		// Post is of user post type
		if ( $this->is_user_post( $post ) ) {

			// Set permalink to the user edit url
			$edit_link = $this->get_user_edit_url( $this->get_post_user_id( $post ) );
		}

		return $edit_link;
	}

	/**
	 * Overwrite the post thumbnail html for user posts
	 *
	 * @since 1.0.0
	 *
	 * @param string $html
	 * @param string $post_id
	 * @param string $post_thumbnail_id
	 * @param string $size
	 * @param string $attr Query string of attributes
	 * @return string Post thumbnail html
	 */
	public function post_thumbnail( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
		$post = get_post( $post_id );

		// Post is of user post type
		if ( $this->is_user_post( $post ) ) {

			// Set permalink to the user edit url
			$html = $this->get_user_avatar( $this->get_post_user_id( $post ), $size, $attr );
		}

		return $html;
	}

	/**
	 * Return empty post thumbnail ID for user posts
	 *
	 * @since 1.0.0
	 *
	 * @param NULL null Metadata hijack value
	 * @param int $post_id The post ID
	 * @param string $meta_key The queried meta key
	 * @param bool $single
	 * @return mixed Metadata hijack value
	 */
	public function post_thumbnail_id( $null, $post_id, $meta_key, $single ) {

		// Return post thumbnail ID 0 for user posts
		if ( '_thumbnail_id' == $meta_key && $this->is_user_post( $post_id ) ) {
			$null = -1;
		}

		return $null;
	}

	/**
	 * Filter caps for user posts
	 *
	 * @since 1.0.0
	 *
	 * @param array $caps Required caps
	 * @param string $cap Requested cap
	 * @param int $user_id User ID
	 * @param array $args Context arguments
	 * @return array Caps
	 */
	public function map_meta_cap( $caps, $cap, $user_id, $args ) {

		// Check capability
		switch ( $cap ) {

			// Editing
			case 'edit_post' :
				$post = get_post( $args[0] );

				// This is a user post
				if ( $post && $this->is_user_post( $post ) ) {

					// Admins can edit users
					if ( user_can( $user_id, 'edit_users' ) ) {
						$caps = array( 'edit_users' );

					// User can edit itself
					} elseif ( $this->get_post_user_id( $post ) == $user_id ) {
						$caps = array( 'read' );

					// No editing allowed
					} else {
						$caps = array( 'do_not_allow' );
					}
				}

				break;
		}

		return $caps;
	}
}

/**
 * Return the single plugin instance
 *
 * @since 1.0.0
 *
 * @uses User_Search
 * @return User_Search
 */
function user_search() {
	return User_Search::instance();
}

// Fire it up!
user_search();

endif; // class_exists
