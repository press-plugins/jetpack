<?php

// Declare the new instances here, so that the classes can
// be pulled in elsewhere if desired without activating them.
require_once( dirname(__FILE__) . '/omnisearch-posts.php' );
new Jetpack_Omnisearch_Posts;

require_once( dirname(__FILE__) . '/omnisearch-pages.php' );
new Jetpack_Omnisearch_Pages;

require_once( dirname(__FILE__) . '/omnisearch-comments.php' );
new Jetpack_Omnisearch_Comments;

if ( current_user_can( 'install_plugins' ) ) {
	require_once( dirname(__FILE__) . '/omnisearch-plugins.php' );
	new Jetpack_Omnisearch_Plugins;
}

class Jetpack_Omnisearch {
	static $instance;
	static $num_results = 5;

	function __construct() {
		self::$instance = $this;
		add_action( 'wp_loaded', array( $this, 'wp_loaded' ) );
		add_action(	'jetpack_admin_menu', array( $this, 'jetpack_admin_menu' ) );
		if( is_admin() ) {
			add_action( 'admin_bar_menu', array( $this, 'admin_bar_search' ), 4 );
		}
	}

	function wp_loaded() {
		$deps = null;
		if ( wp_style_is( 'genericons', 'registered' ) ) {
			$deps = array( 'genericons' );
		}

		wp_register_style( 'omnisearch-admin', plugins_url( 'omnisearch.css', __FILE__ ), $deps );
	}

	function jetpack_admin_menu() {
		$slug = add_submenu_page( 'jetpack', __('Omnisearch', 'jetpack'), __('Omnisearch', 'jetpack'), 'edit_posts', 'omnisearch', array( $this, 'omnisearch_page' ) );

		add_action( "admin_print_styles-{$slug}", array( $this, 'admin_print_styles' ) );
	}

	function admin_print_styles() {
		wp_enqueue_style( 'omnisearch-admin' );
	}

	function omnisearch_page() {
		$results = array();
		$s = isset( $_GET['s'] ) ? $_GET['s'] : '';
		if( $s ) {
			$results = apply_filters( 'omnisearch_results', $results, $s );
		}
		?>
		<div class="wrap">
			<h2 class="page-title"><?php esc_html_e('Jetpack Omnisearch', 'jetpack'); ?></h2>
			<br class="clear" />
			<?php echo self::get_omnisearch_form( array(
							'form_class'       => 'omnisearch-form',
							'search_class'     => 'omnisearch',
							'submit_class'     => 'omnisearch-submit',
							'alternate_submit' => true,
						) ); ?>
			<?php if( ! empty( $results ) ): ?>
				<h3 id="results-title"><?php esc_html_e('Results:', 'jetpack'); ?></h3>
				<div class="jump-to"><strong><?php esc_html_e('Jump to:', 'jetpack'); ?></strong></div>
				<br class="clear" />
				<script>var search_term = '<?php echo esc_js( $s ); ?>', num_results = <?php echo intval( self::$num_results ); ?>;</script>
				<ul class="omnisearch-results">
					<?php foreach( $results as $label => $result ) : ?>
						<li id="result-<?php echo sanitize_title( $label ); ?>" data-label="<?php echo esc_attr( $label ); ?>">
							<?php echo $result; ?>
							<a class="back-to-top" href="#results-title"><?php esc_html_e('Back to Top &uarr;', 'jetpack'); ?></a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div><!-- /wrap -->
		<script>
		jQuery(document).ready(function($){
			$('.omnisearch-results > li').each(function(){
				$('.jump-to').append(' <a href="#' + $(this).attr('id') + '">' + $(this).attr('data-label') + '</a>');
			});
		});
		</script>
		<?php
	}

	function admin_bar_search( $wp_admin_bar ) {
		if( ! is_admin() ) return;

		$form = self::get_omnisearch_form( array(
			'form_id'      => 'adminbarsearch',
			'search_id'    => 'adminbar-search',
			'search_class' => 'adminbar-input',
			'submit_class' => 'adminbar-button',
		) );

		$wp_admin_bar->add_menu( array(
			'parent' => 'top-secondary',
			'id'     => 'search',
			'title'  => $form,
			'meta'   => array(
				'class'    => 'admin-bar-search',
				'tabindex' => -1,
			)
		) );
	}

	static function get_omnisearch_form( $args = array() ) {
		$defaults = array(
			'form_id'            => null,
			'form_class'         => null,
			'search_class'       => null,
			'search_id'          => null,
			'search_value'       => isset( $_REQUEST['s'] ) ? $_REQUEST['s'] : null,
			'search_placeholder' => __( 'Search Everything', 'jetpack' ),
			'submit_class'       => 'button',
			'submit_value'       => __( 'Search', 'jetpack' ),
			'alternate_submit'   => false,
		);
		extract( array_map( 'esc_attr', wp_parse_args( $args, $defaults ) ) );

		$rand = rand();
		if( empty( $form_id ) )
			$form_id = "omnisearch_form_$rand";
		if( empty( $search_id ) )
			$search_id = "omnisearch_search_$rand";

		ob_start();
		?>

		<form action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" method="get" class="<?php echo $form_class; ?>" id="<?php echo $form_id; ?>">
			<input type="hidden" name="page" value="omnisearch" />
			<input name="s" type="search" class="<?php echo $search_class; ?>" id="<?php echo $search_id; ?>" value="<?php echo $search_value; ?>" placeholder="<?php echo $search_placeholder; ?>" />
			<?php if ( $alternate_submit ) : ?>
				<button type="submit" class="<?php echo $submit_class; ?>"><span><?php echo $submit_value; ?></span></button>
			<?php else : ?>
				<input type="submit" class="<?php echo $submit_class; ?>" value="<?php echo $submit_value; ?>" />
			<?php endif; ?>
		</form>

		<?php
		return apply_filters( 'get_omnisearch_form', ob_get_clean(), $args, $defaults );
	}

}
new Jetpack_Omnisearch;
