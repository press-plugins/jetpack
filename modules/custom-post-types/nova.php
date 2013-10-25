<?php

/*
 * Plugin Name: Nova - Restaurant Websites Shouldn't Suck
 * Plugin URI: http://wordpress.org/extend/plugins/nova/
 * Author: Automattic
 * Version: 0.1
 * License: GPL2+
 * Text Domain: nova
 * Domain Path: /languages/
 */

/*
 * Put the following code in your themee's Food Menu Page Template to customize the markup of the menu.

if ( class_exists( 'Nova_Restaurant' ) ) {
	Nova_Restaurant::init( array(
		'menu_tag'               => 'section',
		'menu_class'             => 'menu-items',
		'menu_header_tag'        => 'header',
		'menu_header_class'      => 'menu-group-header',
		'menu_title_tag'         => 'h1',
		'menu_title_class'       => 'menu-group-title',
		'menu_description_tag'   => 'div',
		'menu_description_class' => 'menu-group-description',
	) );
}

*/

/* @todo

Bulk/Quick edit response of Menu Item rows is broken.

Drag and Drop reordering.
*/

class Nova_Restaurant {
	const MENU_ITEM_POST_TYPE = 'nova_menu_item';
	const MENU_ITEM_LABEL_TAX = 'nova_menu_item_label';
	const MENU_TAX = 'nova_menu';

	var $version = '0.1';

	protected $default_menu_item_loop_markup = array(
		'menu_tag'               => 'section',
		'menu_class'             => 'menu-items',
		'menu_header_tag'        => 'header',
		'menu_header_class'      => 'menu-group-header',
		'menu_title_tag'         => 'h1',
		'menu_title_class'       => 'menu-group-title',
		'menu_description_tag'   => 'div',
		'menu_description_class' => 'menu-group-description',
	);

	protected $menu_item_loop_markup = array();
	protected $menu_item_loop_last_term_id = false;
	protected $menu_item_loop_current_term = false;

	static function init( $menu_item_loop_markup = array() ) {
		static $instance = false;

		if ( !$instance ) {
			$instance = new Nova_Restaurant;
		}

		if ( $menu_item_loop_markup ) {
			$instance->menu_item_loop_markup = wp_parse_args( $menu_item_loop_markup, $this->default_menu_item_loop_markup );
		}

		return $instance;
	}

	function __construct() {
		$this->register_taxonomies();
		$this->register_post_types();
		add_action( 'admin_menu', array( $this, 'add_admin_menus' ) );

		// Always sort menu items correctly
		add_action( 'parse_query',   array( $this, 'sort_menu_item_queries_by_menu_order' ) );
		add_filter( 'posts_results', array( $this, 'sort_menu_item_queries_by_menu_taxonomy' ), 10, 2 );

		add_action( 'wp_insert_post', array( $this, 'add_post_meta' ) );

		$this->menu_item_loop_markup = $this->default_menu_item_loop_markup;

		// Only output our Menu Item Loop Marup on a real blog view.  Not feeds, XML-RPC, admin, etc.
		add_filter( 'template_include', array( $this, 'setup_menu_item_loop_markup__in_filter' ) );
	}

/* Setup */

	function register_taxonomies() {
		register_taxonomy( self::MENU_ITEM_LABEL_TAX, self::MENU_ITEM_POST_TYPE, array(
			'labels' => array(
				'name'                       => __( 'Menu Item Labels', 'nova' ),
				'singular_name'              => __( 'Menu Item Label', 'nova' ),
				'search_items'               => __( 'Search Menu Item Labels', 'nova' ),
				'popular_items'              => __( 'Popular Labels', 'nova' ),
				'all_items'                  => __( 'All Menu Item Labels', 'nova' ),
				'edit_item'                  => __( 'Edit Menu Item Label', 'nova' ),
				'view_item'                  => __( 'View Menu Item Label', 'nova' ),
				'update_item'                => __( 'Update Menu Item Label', 'nova' ),
				'add_new_item'               => __( 'Add New Menu Item Label', 'nova' ),
				'new_item_name'              => __( 'New Menu Item Label Name', 'nova' ),
				'separate_items_with_commas' => __( 'Separate Labels with commas', 'nova' ),
				'add_or_remove_items'        => __( 'Add or remove Labels', 'nova' ),
				'choose_from_most_used'      => __( 'Choose from the most used Labels', 'nova' ),
			),
			'no_tagcloud' => __( 'No Labels found', 'nova' ),

			'hierarchical'  => false,
		) );

		register_taxonomy( self::MENU_TAX, self::MENU_ITEM_POST_TYPE, array(
			'labels' => array(
				'name'               => __( 'Food Menus', 'nova' ),
				'singular_name'      => __( 'Food Menu', 'nova' ),
				'search_items'       => __( 'Search Menus', 'nova' ),
				'all_items'          => __( 'All Menus', 'nova' ),
				'parent_item'        => __( 'Parent Menu', 'nova' ),
				'parent_item_colon'  => __( 'Parent Menu:', 'nova' ),
				'edit_item'          => __( 'Edit Menu', 'nova' ),
				'view_item'          => __( 'View Menu', 'nova' ),
				'update_item'        => __( 'Update Menu', 'nova' ),
				'add_new_item'       => __( 'Add New Menu', 'nova' ),
				'new_item_name'      => __( 'New Menu Name', 'nova' ),
			),
			'rewrite' => array(
				'slug'         => 'menu',
				'with_front'   => false,
				'hierarchical' => true,
			),

			'hierarchical'  => true,
			'show_tagcloud' => false,
			'query_var'     => 'menu',
		) );
	}

	function register_post_types() {
		register_post_type( self::MENU_ITEM_POST_TYPE, array(
			'description' => __( "Items on your restaurant's menu", 'nova' ),

			'labels' => array(
				'name'               => __( 'Menu Items', 'nova' ),
				'singular_name'      => __( 'Menu Item', 'nova' ),
				'menu_name'          => __( 'Food Menus', 'nova' ),
				'all_items'          => __( 'Menu Items', 'nova' ),
				'add_new'            => __( 'Add One Item', 'nova' ),
				'add_new_item'       => __( 'Add One Item', 'nova' ),
				'edit_item'          => __( 'Edit Menu Item', 'nova' ),
				'new_item'           => __( 'New Menu Item', 'nova' ),
				'view_item'          => __( 'View Menu Item', 'nova' ),
				'search_items'       => __( 'Search Menu Items', 'nova' ),
				'not_found'          => __( 'No Menu Items found', 'nova' ),
				'not_found_in_trash' => __( 'No Menu Items found in Trash', 'nova' ),
			),
			'supports' => array(
				'title',
				'editor',
				'thumbnail',
				'excerpt',
			),
			'rewrite' => array(
				'slug'       => 'item',
				'with_front' => false,
				'feeds'      => false,
				'pages'      => false,
			),
			'register_meta_box_cb' => array( $this, 'register_menu_item_meta_boxes' ),

			'public'          => true,
			'show_ui'         => true, // set to false to replace with custom UI
			'menu_position'   => 20, // below Pages
			'capability_type' => 'page',
			'map_meta_cap'    => true,
			'has_archive'     => false,
			'query_var'       => 'item',
		) );
	}

/* Query */

	function is_menu_item_query( $query ) {
		if (
			( isset( $query->query_vars['taxonomy'] ) && self::MENU_TAX == $query->query_vars['taxonomy'] )
		||
			( isset( $query->query_vars['post_type'] ) && self::MENU_ITEM_POST_TYPE == $query->query_vars['post_type'] )
		) {
			return true;
		}

		return false;
	}

	function sort_menu_item_queries_by_menu_order( $query ) {
		if ( !$this->is_menu_item_query( $query ) ) {
			return;
		}

		$query->query_vars['orderby'] = 'menu_order';
		$query->query_vars['order'] = 'ASC';

		// For now, just turn off paging so we can sort by taxonmy later
		// If we want paging in the future, we'll need to add the taxonomy sort here (or at least before the DB query is made)
		$query->query_vars['posts_per_page'] = -1;
	}

	function sort_menu_item_queries_by_menu_taxonomy( $posts, $query ) {
		if ( !$posts ) {
			return $posts;
		}

		if ( !$this->is_menu_item_query( $query ) ) {
			return $posts;
		}

		$grouped_by_term = array();

		foreach ( $posts as $post ) {
			$term = $this->get_menu_item_menu_leaf( $post->ID );
			if ( !$term || is_wp_error( $term ) ) {
				$term_id = 0;
			} else {
				$term_id = $term->term_id;
			}

			if ( !isset( $grouped_by_term["$term_id"] ) ) {
				$grouped_by_term["$term_id"] = array();
			}

			$grouped_by_term["$term_id"][] = $post;
		}

		$term_order = get_option( 'nova_menu_order', array() );

		$return = array();
		foreach ( $term_order as $term_id ) {
			if ( isset( $grouped_by_term["$term_id"] ) ) {
				$return = array_merge( $return, $grouped_by_term["$term_id"] );
				unset( $grouped_by_term["$term_id"] );
			}
		}

		foreach ( $grouped_by_term as $term_id => $posts ) {
			$return = array_merge( $return, $posts );
		}

		return $return;
	}

/* Admin */

	function add_admin_menus() {
		$hook = add_submenu_page(
			'edit.php?post_type=' . self::MENU_ITEM_POST_TYPE,
			__( 'Add Many Items', 'nova' ),
			__( 'Add Many Items', 'nova' ),
			'edit_pages',
			'add_many_nova_items',
			array( $this, 'add_many_new_items_page' )
		);

		add_action( "load-$hook", array( $this, 'add_many_new_items_page_load' ) );

		add_action( 'current_screen', array( $this, 'current_screen_load' ) );

		$submenu_item = array_pop( $GLOBALS['submenu']['edit.php?post_type=' . self::MENU_ITEM_POST_TYPE] );
		$GLOBALS['submenu']['edit.php?post_type=' . self::MENU_ITEM_POST_TYPE][11] = $submenu_item;

		ksort( $GLOBALS['submenu']['edit.php?post_type=' . self::MENU_ITEM_POST_TYPE] );

		$this->setup_menu_item_columns();

		wp_register_script( 'nova-menu-checkboxes', plugins_url( 'js/menu-checkboxes.js', __FILE__ ), array( 'jquery' ), $this->version, true );

	}

	function current_screen_load() {
		$screen = get_current_screen();
		if ( 'edit-nova_menu_item' !== $screen->id ) {
			return;
		}

		$this->edit_menu_items_page_load();
		add_filter( 'admin_notices', array( $this, 'admin_notices' ) );
	}

/* Edit Items List */

	function admin_notices() {
		if ( isset( $_GET['nova_reordered'] ) )
			printf( '<div class="updated"><p>%s</p></div>', __('Menu Items re-ordered.', 'nova' ) );
	}

	function no_title_sorting( $columns ) {
		if ( isset( $columns['title'] ) )
			unset( $columns['title'] );
		return $columns;
	}

	function setup_menu_item_columns() {
		add_filter( sprintf( 'manage_edit-%s_sortable_columns', self::MENU_ITEM_POST_TYPE ), array( $this, 'no_title_sorting' ) );
		add_filter( sprintf( 'manage_%s_posts_columns', self::MENU_ITEM_POST_TYPE ), array( $this, 'menu_item_columns' ) );

		add_action( sprintf( 'manage_%s_posts_custom_column', self::MENU_ITEM_POST_TYPE ), array( $this, 'menu_item_column_callback' ), 10, 2 );
	}

	function menu_item_columns( $columns ) {
		unset( $columns['date'], $columns['likes'] );

		$columns['labels'] = __( 'Labels', 'nova' );
		$columns['price']  = __( 'Price',  'nova' );
		$columns['order']  = __( 'Order',  'nova' );

		return $columns;
	}

	function menu_item_column_callback( $column, $post_id ) {
		$screen = get_current_screen();

		switch ( $column ) {
		case 'labels' :
			$this->list_admin_labels( $post_id );
			break;
		case 'price' :
			$this->display_price( $post_id );
			break;
		case 'order' :
			$url = admin_url( $screen->parent_file );

			$up_url = add_query_arg( array(
				'action' => 'move-item-up',
				'post_id' => (int) $post_id,
			), wp_nonce_url( $url, 'nova_move_item_up_' . $post_id ) );

			$down_url = add_query_arg( array(
				'action' => 'move-item-down',
				'post_id' => (int) $post_id,
			), wp_nonce_url( $url, 'nova_move_item_down_' . $post_id ) );
			$menu_item = get_post($post_id);
			$this->get_menu_by_post_id( $post_id );
?>
			<input type="hidden" class="menu-order-value" name="nova_order[<?php echo (int) $post_id ?>]" value="<?php echo esc_attr( $menu_item->menu_order ) ?>" />
			<input type="hidden" class='nova-menu-term' name="nova_menu_term[<?php echo (int) $post_id ?>]" value="<?php echo esc_attr( $this->get_menu_by_post_id( $post_id )->term_id ); ?>">

			<span class="hide-if-js">
			&nbsp; &nbsp; &mdash; <a class="nova-move-item-up" data-post-id="<?php echo (int) $post_id; ?>" href="<?php echo esc_url( $up_url ); ?>">up</a>
			<br />
			&nbsp; &nbsp; &mdash; <a class="nova-move-item-down" data-post-id="<?php echo (int) $post_id; ?>" href="<?php echo esc_url( $down_url ); ?>">down</a>
			</span>
<?php
			break;
		}
	}

	function get_menu_by_post_id( $post_id = null ) {
		if ( ! $post_id )
			return false;

		$terms = get_the_terms( $post_id, self::MENU_TAX );
		return array_pop( $terms );
	}

	/**
	 * Fires on a menu edit page. We might have drag-n-drop reordered
	 */
	function maybe_reorder_menu_items() {
		// make sure we clicked our button
		if ( ! ( isset( $_REQUEST['menu_reorder_submit'] ) && $_REQUEST['menu_reorder_submit'] === __( 'Re-order', 'nova' ) ) )
			return;
		;

		// make sure we have the nonce
		if ( ! ( isset( $_REQUEST['drag-drop-reorder'] ) && wp_verify_nonce( $_REQUEST['drag-drop-reorder'], 'drag-drop-reorder' ) ) )
			return;

		$term_pairs = array_map( 'absint', $_REQUEST['nova_menu_term'] );
		$order_pairs = array_map( 'absint', $_REQUEST['nova_order'] );

		foreach( $order_pairs as $ID => $menu_order ) {
			$ID = absint( $ID );
			unset( $order_pairs[$ID] );
			if ( $ID < 0 )
				continue;

			$post = get_post( $ID );
			if ( ! $post )
				continue;

			// save a write if the order hasn't changed
			if ( $menu_order != $post->menu_order )
				wp_update_post( compact( 'ID', 'menu_order' ) );

			// save a write if the term hasn't changed
			if ( $term_pairs[$ID] != $this->get_menu_by_post_id( $ID )->term_id )
				wp_set_object_terms( $ID, $term_pairs[$ID], self::MENU_TAX );

		}

		$redirect = add_query_arg( array(
			'post_type' => self::MENU_ITEM_POST_TYPE,
			'nova_reordered' => '1'
		), admin_url( 'edit.php' ) );
		wp_safe_redirect( $redirect );
		exit;

	}

	function edit_menu_items_page_load() {
		if ( isset( $_GET['action'] ) ) {
			$this->handle_menu_item_actions();
		}

		$this->maybe_reorder_menu_items();

		wp_enqueue_style( 'nova-edit-items', plugins_url( 'css/edit-items.css', __FILE__ ), array(), $this->version );
		wp_enqueue_script( 'nova-drag-drop', plugins_url( 'js/nova-drag-drop.js', __FILE__ ), array( 'jquery-ui-sortable' ), $this->version, true );
		wp_localize_script( 'nova-drag-drop', '_novaDragDrop', array(
			'nonce' => wp_create_nonce( 'drag-drop-reorder' ),
			'nonceName' => 'drag-drop-reorder',
			'reorder' => __( 'Re-order', 'nova' ),
			'reorderName' => 'menu_reorder_submit'
		) );
		add_action( 'the_post', array( $this, 'show_menu_titles_in_menu_item_list' ) );
	}

	function handle_menu_item_actions() {
		$action = (string) $_GET['action'];

		switch ( $action ) {
		case 'move-item-up' :
		case 'move-item-down' :
			$reorder = false;

			$post_id = (int) $_GET['post_id'];

			$term = $this->get_menu_item_menu_leaf( $post_id );

			// Get all posts in that term
			$query = new WP_Query( array(
				'taxonomy' => self::MENU_TAX,
				'term'     => $term->slug,
			) );

			$order = array();
			foreach ( $query->posts as $post ) {
				$order[] = $post->ID;
			}

			if ( 'move-item-up' == $action ) {
				check_admin_referer( 'nova_move_item_up_' . $post_id );

				$first_post_id = $order[0];
				if ( $post_id == $first_post_id ) {
					break;
				}

				foreach ( $order as $menu_order => $order_post_id ) {
					if ( $post_id != $order_post_id ) {
						continue;
					}

					$swap_post_id = $order[$menu_order - 1];
					$order[$menu_order - 1] = $post_id;
					$order[$menu_order] = $swap_post_id;

					$reorder = true;
					break;
				}
			} else {
				check_admin_referer( 'nova_move_item_down_' . $post_id );

				$last_post_id = end( $order );
				if ( $post_id == $last_post_id ) {
					break;
				}

				foreach ( $order as $menu_order => $order_post_id ) {
					if ( $post_id != $order_post_id ) {
						continue;
					}

					$swap_post_id = $order[$menu_order + 1];
					$order[$menu_order + 1] = $post_id;
					$order[$menu_order] = $swap_post_id;

					$reorder = true;
				}
			}

			if ( $reorder ) {
				foreach ( $order as $menu_order => $ID ) {
					wp_update_post( compact( 'ID', 'menu_order' ) );
				}
			}

			break;
		case 'move-menu-up' :
		case 'move-menu-down' :
			$reorder = false;

			$term_id = (int) $_GET['term_id'];

			$terms = $this->get_menus();

			$order = array();
			foreach ( $terms as $term ) {
				$order[] = $term->term_id;
			}

			if ( 'move-menu-up' == $action ) {
				check_admin_referer( 'nova_move_menu_up_' . $term_id );

				$first_term_id = $order[0];
				if ( $term_id == $first_term_id ) {
					break;
				}

				foreach ( $order as $menu_order => $order_term_id ) {
					if ( $term_id != $order_term_id ) {
						continue;
					}

					$swap_term_id = $order[$menu_order - 1];
					$order[$menu_order - 1] = $term_id;
					$order[$menu_order] = $swap_term_id;

					$reorder = true;
					break;
				}
			} else {
				check_admin_referer( 'nova_move_menu_down_' . $term_id );

				$last_term_id = end( $order );
				if ( $term_id == $last_term_id ) {
					break;
				}

				foreach ( $order as $menu_order => $order_term_id ) {
					if ( $term_id != $order_term_id ) {
						continue;
					}

					$swap_term_id = $order[$menu_order + 1];
					$order[$menu_order + 1] = $term_id;
					$order[$menu_order] = $swap_term_id;

					$reorder = true;
				}
			}

			if ( $reorder ) {
				update_option( 'nova_menu_order', $order );
			}

			break;
		default :
			return;
		}

		$redirect = add_query_arg( array(
			'post_type' => self::MENU_ITEM_POST_TYPE,
			'nova_reordered' => '1'
		), admin_url( 'edit.php' ) );
		wp_safe_redirect( $redirect );
		exit;
	}

	/*
	 * Add menu title rows to the list table
	 */
	function show_menu_titles_in_menu_item_list( $post ) {
		global $wp_list_table;

		static $last_term_id = false;

		$term = $this->get_menu_item_menu_leaf( $post->ID );
		if ( false !== $last_term_id && $last_term_id === $term->term_id ) {
			return;
		}

		$last_term_id = $term->term_id;

		$parent_count = 0;
		$current_term = $term;
		while ( $current_term->parent ) {
			$parent_count++;
			$current_term = get_term( $current_term->parent, self::MENU_TAX );
		}

		$non_order_column_count = $wp_list_table->get_column_count() - 1;

		$screen = get_current_screen();

		$url = admin_url( $screen->parent_file );

		$up_url = add_query_arg( array(
			'action'  => 'move-menu-up',
			'term_id' => (int) $term->term_id,
		), wp_nonce_url( $url, 'nova_move_menu_up_' . $term->term_id ) );

		$down_url = add_query_arg( array(
			'action'  => 'move-menu-down',
			'term_id' => (int) $term->term_id,
		), wp_nonce_url( $url, 'nova_move_menu_down_' . $term->term_id ) );

?>
		<tr class="no-items menu-label-row" data-term_id="<?php echo esc_attr( $term->term_id ) ?>">
			<td class="colspanchange" colspan="<?php echo (int) $non_order_column_count; ?>">
				<h3><?php
					echo str_repeat( ' &mdash; ', (int) $parent_count );
					echo esc_html( sanitize_term_field( 'name', $term->name, $term->term_id, self::MENU_TAX, 'display' ) );
				?></h3>
			</td>
			<td>
				<a class="nova-move-menu-up" title="<?php esc_attr_e( 'Move menu section up' ); ?>" href="<?php echo esc_url( $up_url ); ?>"><?php esc_html_e( 'UP' ); ?></a>
				<br />
				<a class="nova-move-menu-down" title="<?php esc_attr_e( 'Move menu section down' ); ?>" href="<?php echo esc_url( $down_url ); ?>"><?php esc_html_e( 'DOWN' ); ?></a>
			</td>
		</tr>
<?php
	}

/* Edit Many Items */

	function add_many_new_items_page_load() {
		if ( 'POST' === strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			$this->process_form_request();
			exit;
		}

		$this->enqueue_many_items_styles();
		$this->enqueue_many_items_scripts();
	}

	function enqueue_many_items_styles() {
		wp_enqueue_style( 'nova-many-items', plugins_url( 'css/many-items.css', __FILE__ ), array(), $this->version );
	}

	function enqueue_many_items_scripts() {
		wp_enqueue_script( 'nova-many-items', plugins_url( 'js/many-items.js', __FILE__ ), array( 'jquery' ), $this->version, true );
	}

	function process_form_request() {
		if ( !isset( $_POST['nova_title'] ) || !is_array( $_POST['nova_title'] ) ) {
			return;
		}

		$is_ajax = !empty( $_POST['ajax'] );

		if ( $is_ajax ) {
			check_ajax_referer( 'nova_many_items' );
		} else {
			check_admin_referer( 'nova_many_items' );
		}

		foreach ( array_keys( $_POST['nova_title'] ) as $key ) :
			// $_POST is already slashed
			$post_details = array(
				'post_status'  => 'publish',
				'post_type'    => self::MENU_ITEM_POST_TYPE,
				'post_content' => $_POST['nova_content'][$key],
				'post_title'   => $_POST['nova_title'][$key],
				'tax_input'    => array(
					self::MENU_ITEM_LABEL_TAX => $_POST['nova_labels'][$key],
					self::MENU_TAX            => $_POST['nova_menu_tax'],
				),
			);

			$post_id = wp_insert_post( $post_details );
			if ( !$post_id || is_wp_error( $post_id ) ) {
				continue;
			}

			$this->set_price( $post_id, isset( $_POST['nova_price'][$key] ) ? stripslashes( $_POST['nova_price'][$key] ) : '' );

			if ( $is_ajax ) :
				$post = get_post( $post_id );
				$GLOBALS['post'] = $post;
				setup_postdata( $post );

?>
			<td><?php the_title(); ?></td>
			<td><?php the_content(); ?></td>
			<td><?php $this->display_price(); ?></td>
			<td><?php $this->list_labels( $post_id ); ?></td>
<?php
			endif;

		endforeach;

		if ( $is_ajax ) {
			exit;
		}

		wp_safe_redirect( admin_url( 'edit.php?post_type=' . self::MENU_ITEM_POST_TYPE ) );
		exit;
	}

	function add_many_new_items_page() {
?>
	<div class="wrap">
		<h2><?php esc_html_e( 'Add Many Items', 'nova' ); ?></h2>

		<p><?php _e( 'Use the <kbd>TAB</kbd> key on your keyboard to move between colums and the <kbd>ENTER</kbd> or <kbd>RETURN</kbd> key to save each row and move on to the next.', 'nova' ); ?></p>

		<form method="post" action="" enctype="multipart/form-data">
			<p><?php wp_dropdown_categories( array(
				'id'           => 'nova-menu-tax',
				'name'         => 'nova_menu_tax',
				'taxonomy'     => self::MENU_TAX,
				'hide_empty'   => false,
				'hierarchical' => true,
			) ); ?></p>

			<table class="many-items-table wp-list-table widefat">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Name', 'nova' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Description', 'nova' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Price', 'nova' ); ?></th>
						<th scope="col"><?php _e( 'Labels: <small>spicy, favorite, etc.</small>', 'nova' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><input type="text" name="nova_title[]" aria-required="true" /></td>
						<td><textarea name="nova_content[]" cols="20" rows="2"></textarea>
						<td><input type="text" name="nova_price[]" /></td>
						<td><input type="text" name="nova_labels[]" /></td>
					</tr>
				</tbody>
				<tfoot>
					<tr>
						<th></th>
						<th></th>
						<th></th>
						<th scope="col"><em><?php esc_html_e( 'Separate Labels with commas', 'nova' ); ?></em></th>
					</tr>
				</tfoot>
			</table>

			<p class="submit">
				<input type="submit" class="button-primary" value="<?php esc_attr_e( 'Add These New Menu Items', 'nova' ); ?>" />
				<?php wp_nonce_field( 'nova_many_items' ); ?>
			</p>
		</form>
	</div>
<?php
	}

/* Edit One Item */

	function register_menu_item_meta_boxes() {
		wp_enqueue_script( 'nova-menu-checkboxes' );

		add_meta_box( 'menu_item_price', __( 'Price', 'nova' ), array( $this, 'menu_item_price_meta_box' ), null, 'side', 'high' );
	}

	function menu_item_price_meta_box( $post, $meta_box ) {
		$price = $this->get_price( $post->ID );
?>
	<label for="nova-price-<?php echo (int) $post->ID; ?>" class="screen-reader-text"><?php esc_html_e( 'Price', 'nova' ); ?></label>
	<input type="text" id="nova-price-<?php echo (int) $post->ID; ?>" class="widefat" name="nova_price[<?php echo (int) $post->ID; ?>]" value="<?php echo esc_attr( $price ); ?>" />
<?php
	}

	function add_post_meta( $post_id ) {
		if ( !isset( $_POST['nova_price'][$post_id] ) ) {
			return;
		}

		$this->set_price( $post_id, stripslashes( $_POST['nova_price'][$post_id] ) );
	}

/* Data */

	function get_menus( $args = array() ) {
		$args = wp_parse_args( $args, array(
			'hide_empty' => false,
		) );

		$terms = get_terms( self::MENU_TAX, $args );
		if ( !$terms || is_wp_error( $terms ) ) {
			return array();
		}

		$terms_by_id = array();
		foreach ( $terms as $term ) {
			$terms_by_id["{$term->term_id}"] = $term;
		}

		$term_order = get_option( 'nova_menu_order', array() );

		$return = array();
		foreach ( $term_order as $term_id ) {
			if ( isset( $terms_by_id["$term_id"] ) ) {
				$return[] = $terms_by_id["$term_id"];
				unset( $terms_by_id["$term_id"] );
			}
		}

		foreach ( $terms_by_id as $term_id => $term ) {
			$return[] = $term;
		}

		return $return;
	}

	function get_menu_item_menu_leaf( $post_id ) {
		// Get first menu taxonomy "leaf"
		$term_ids = wp_get_object_terms( $post_id, self::MENU_TAX, array( 'fields' => 'ids' ) );

		foreach ( $term_ids as $term_id ) {
			$children = get_term_children( $term_id, self::MENU_TAX );
			if ( !$children ) {
				break;
			}
		}

		return get_term( $term_id, self::MENU_TAX );
	}

	function list_labels( $post_id = 0 ) {
		$post = get_post( $post_id );
		echo get_the_term_list( $post->ID, self::MENU_ITEM_LABEL_TAX, '', _x( ', ', 'Nova label separator', 'nova' ), '' );
	}

	function list_admin_labels( $post_id = 0 ) {
		$post = get_post( $post_id );
		$labels = get_the_terms( $post->ID, self::MENU_ITEM_LABEL_TAX );
		if ( !empty( $labels ) ) {
			$out = array();
			foreach ( $labels as $label ) {
				$out[] = sprintf( '<a href="%s">%s</a>',
					esc_url( add_query_arg( array(
						'post_type' => self::MENU_ITEM_POST_TYPE,
						'taxonomy'  => self::MENU_ITEM_LABEL_TAX,
						'term'      => $label->slug
					), 'edit.php' ) ),
					esc_html( sanitize_term_field( 'name', $label->name, $label->term_id, self::MENU_ITEM_LABEL_TAX, 'display' ) )
				);
			}

			echo join( _x( ', ', 'Nova label separator', 'nova' ), $out );
		} else {
			esc_html_e( 'No Labels', 'nova' );
		}
	}

	function set_price( $post_id = 0, $price = '' ) {
		$post = get_post( $post_id );

		return update_post_meta( $post->ID, 'nova_price', $price );
	}

	function get_price( $post_id = 0 ) {
		$post = get_post( $post_id );

		return get_post_meta( $post->ID, 'nova_price', true );
	}

	function display_price( $post_id = 0 ) {
		echo esc_html( $this->get_price( $post_id ) );
	}

/* Menu Item Loop Markup */

	/* Does not support nested loops */

	function get_menu_item_loop_markup( $field = null ) {
		return $this->menu_item_loop_markup;
	}

	/**
	 * Sets up the loop markup.
	 * Attached to the 'template_include' *filter*,
	 * which fires only during a real blog view (not in admin, feeds, etc.)
	 *
	 * @param string Template File
	 * @return string Template File.  VERY Important.
	 */
	function setup_menu_item_loop_markup__in_filter( $template ) {
		add_action( 'loop_start', array( $this, 'start_menu_item_loop' ) );

		return $template;
	}

	/**
	 * If the Query is a Menu Item Query, start outputing the Menu Item Loop Marku
	 * Attached to the 'loop_start' action.
	 *
	 * @param WP_Query
	 */
	function start_menu_item_loop( $query ) {
		if ( !$this->is_menu_item_query( $query ) ) {
			return;
		}

		$this->menu_item_loop_last_term_id = false;
		$this->menu_item_loop_current_term = false;

		add_action( 'the_post', array( $this, 'menu_item_loop_each_post' ) );
		add_action( 'loop_end', array( $this, 'stop_menu_item_loop' ) );
	}

	/**
	 * Outputs the Menu Item Loop Marku
	 * Attached to the 'the_post' action.
	 *
	 * @param WP_Post
	 */
	function menu_item_loop_each_post( $post ) {
		$this->menu_item_loop_current_term = $this->get_menu_item_menu_leaf( $post->ID );

		if ( false === $this->menu_item_loop_last_term_id ) {
			// We're at the very beginning of the loop

			$this->menu_item_loop_open_element( 'menu' ); // Start a new menu section
			$this->menu_item_loop_header(); // Output the menu's header
		} elseif ( $this->menu_item_loop_last_term_id != $this->menu_item_loop_current_term->term_id ) {
			// We're not at the very beginning but still need to start a new menu section.  End the previous menu section first.

			$this->menu_item_loop_close_element( 'menu' ); // End the previous menu section
			$this->menu_item_loop_open_element( 'menu' ); // Start a new menu section
			$this->menu_item_loop_header(); // Output the menu's header
		}

		$this->menu_item_loop_last_term_id = $this->menu_item_loop_current_term->term_id;
	}

	/**
	 * If the Query is a Menu Item Query, stop outputing the Menu Item Loop Marku
	 * Attached to the 'loop_end' action.
	 *
	 * @param WP_Query
	 */
	function stop_menu_item_loop( $query ) {
		if ( !$this->is_menu_item_query( $query ) ) {
			return;
		}

		remove_action( 'the_post', array( $this, 'menu_item_loop_each_post' ) );
		remove_action( 'loop_start', array( $this, 'start_menu_item_loop' ) );
		remove_action( 'loop_end', array( $this, 'stop_menu_item_loop' ) );

		$this->menu_item_loop_close_element( 'menu' ); // End the last menu section
	}

	/**
	 * Outputs the Menu Group Header
	 */
	function menu_item_loop_header() {
		$this->menu_item_loop_open_element( 'menu_header' );
			$this->menu_item_loop_open_element( 'menu_title' );
				echo esc_html( $this->menu_item_loop_current_term->name ); // @todo tax filter
			$this->menu_item_loop_close_element( 'menu_title' );
		if ( $this->menu_item_loop_current_term->description ) :
			$this->menu_item_loop_open_element( 'menu_description' );
				echo esc_html( $this->menu_item_loop_current_term->description ); // @todo kses, tax filter
			$this->menu_item_loop_close_element( 'menu_description' );
		endif;
		$this->menu_item_loop_close_element( 'menu_header' );
	}

	/**
	 * Outputs a Menu Item Markup element opening tag
	 *
	 * @param string $field - Menu Item Markup settings field
	 */
	function menu_item_loop_open_element( $field ) {
		$markup = $this->get_menu_item_loop_markup();
		echo '<' . tag_escape( $markup["{$field}_tag"] ) .  $this->menu_item_loop_class( $markup["{$field}_class"] ) . ">\n";
	}

	/**
	 * Outputs a Menu Item Markup element closing tag
	 *
	 * @param string $field - Menu Item Markup settings field
	 */
	function menu_item_loop_close_element( $field ) {
		$markup = $this->get_menu_item_loop_markup();
		echo '</' . tag_escape( $markup["{$field}_tag"] ) . ">\n";
	}

	/**
	 * Returns a Menu Item Markup element's class attribute
	 *
	 * @param string $class
	 * @return string HTML class attribute with leading whitespace
	 */
	function menu_item_loop_class( $class ) {
		if ( !$class ) {
			return '';
		}

		return ' class="' . esc_attr( $class ) . '"';
	}
}

add_action( 'init', array( 'Nova_Restaurant', 'init' ) );