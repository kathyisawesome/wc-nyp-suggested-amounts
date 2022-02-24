<?php
/*
 * Plugin Name: WooCommerce Name Your Price - Suggested Amounts
 * Plugin URI: http://www.woocommerce.com/products/name-your-price/
 * Description: Propose multiple price suggestions
 * Version: 1.0.0-beta-2
 * Author: Kathy Darling
 * Author URI: http://kathyisawesome.com
 * Requires at least: 5.8.0
 * Tested up to: 5.0.0
 * WC requires at least: 5.0.0    
 * WC tested up to: 5.8.0   
 *
 * Text Domain: wc-nyp-suggested-amounts
 * Domain Path: /languages/
 *
 * Copyright: © 2021 Kathy Darling.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_NYP_Suggested_Amounts {

	/**
	 * Plugin version
	 */
	const VERSION = '1.0.0-beta-2';

	/**
	 * Plugin Path
	 *
	 * @var string $path
	 */
	private static $plugin_path = '';

	/**
	 * Plugin URL
	 *
	 * @var string $url
	 */
	private static $plugin_url = '';

	/**
	 * Attach hooks and filters.
	 */
	public static function init() {

		if( ! did_action( 'wc_nyp_loaded' ) ) {
			return false;
		}

		// Add admin meta.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_scripts' ) );
		add_action( 'wc_nyp_options_pricing', array( __CLASS__, 'admin_add_suggested_amounts' ), 20, 2 );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_product_meta' ), 20 );

		// Frontend.
		add_action( 'wc_nyp_after_price_label', array( __CLASS__, 'display_amounts' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'frontend_scripts' ), 30 );
		add_filter( 'wc_nyp_get_posted_price', array( __CLASS__, 'posted_price' ), 10, 3 );

	}

	/*-----------------------------------------------------------------------------------*/
	/* Admin */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * Load the product metabox script.
	 */
	public static function admin_scripts() {
		
		global $post;

		// Get admin screen id.
		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';

		// WooCommerce product admin page.
		if ( 'product' === $screen_id ) {

			wp_enqueue_script( 'accounting' );
			wp_enqueue_script( 'wc-nyp-suggested-amounts-metabox', self::get_plugin_url() . '/assets/js/admin/wc-nyp-suggested-amounts-metabox.js', array( 'jquery' ), self::get_version(), true );

			$params = array(
					'save_amounts_nonce'            => wp_create_nonce( 'save-donation-amounts' ),
					'i18n_no_amounts_added'         => esc_js( __( 'No amount added', 'wc-nyp-suggested-amounts' ) ),
					'i18n_remove_amount'            => esc_js( __( 'Are you sure you want to remove this amount?', 'wc-nyp-suggested-amounts' ) ),
					'currency_format_num_decimals'  => esc_attr( wc_get_price_decimals() ),
					'currency_format_symbol'        => get_woocommerce_currency_symbol(),
					'currency_format_decimal_sep'   => esc_attr( wc_get_price_decimal_separator() ),
					'currency_format_thousand_sep'  => esc_attr( wc_get_price_thousand_separator() ),
					'currency_format'               => esc_attr( str_replace( array( '%1$s', '%2$s' ), array( '%s', '%v' ), get_woocommerce_price_format() ) ), // For accounting.js
					'trim_zeroes'                   => apply_filters( 'woocommerce_price_trim_zeros', false ) && wc_get_price_decimals() > 0,
				);

			wp_localize_script( 'wc-nyp-suggested-amounts-metabox', 'WC_NYP_SUGGESTED_AMOUNTS_ADMIN_META_BOX', $params );
			
			// Metabox styles.
			wp_enqueue_style( 'wc-nyp-suggested-amounts-metabox', self::get_plugin_url() . '/assets/css/admin/wc-nyp-suggested-amounts-metabox'. $suffix . '.css', array(), self::get_version() );

			wp_style_add_data( 'wc-nyp-suggested-amounts-metabox', 'rtl', 'replace' );

			if ( $suffix ) {
				wp_style_add_data( 'wc-nyp-suggested-amounts-metabox', 'suffix', '.min' );
			}

		}	

	}

	/**
	 * Add suggested inputs to product metabox
	 *
	 * @param  object WC_Product $product_object
	 * @param  bool $show_billing_period_options
	 * @param  mixed int|false $loop - for use in variations
	 */
	public static function admin_add_suggested_amounts( $product_object, $show_billing_period_options, $loop = false ) { ?>

		<?php

		$use_suggested = wc_string_to_bool( $product_object->get_meta( '_wc_nyp_use_suggested_amounts', true ) );

		woocommerce_wp_checkbox( 
			array(
				'id'            => 'wc_nyp_use_suggested_amounts',
				'wrapper_class' => 'toggle',
				'class'         => 'wc_nyp_use_suggested_amounts',
				'label'         => esc_html__( 'Suggest multiple amounts', 'wc-nyp-suggested-amounts' ),
				'value'	        => wc_bool_to_string( $use_suggested ),
				'description'   => '<label for="wc_nyp_use_suggested_amounts" class="wc-nyp-input-toggle"></label>',
			)
		);

		$amounts = $product_object->get_meta( '_wc_nyp_suggested_amounts' );
		if( $amounts === '' ) {
			$amounts = array();
		}
		?>
	
			<p class="form-field wc_nyp_add_suggested_amounts" style="<?php echo esc_attr( $use_suggested ? '' : 'display:none' ); ?>">

				<label for="wc_nyp_add_suggested_amount"><?php  printf( esc_html__( 'Suggested Amounts (%s)', 'wc-nyp-suggested-amounts' ), get_woocommerce_currency_symbol() ); ?></label>

					<span class="add_prompt dashicons dashicons-plus"></span>
					<input type="text" class="wc_input_price" name="wc_nyp_add_suggested_amount" placeholder="<?php _e( 'Add a suggested amount&hellip;', 'wc-nyp-suggested-amounts' ); ?>" />
					<?php echo wc_help_tip( esc_html__( 'Enter a suggested amount without any currency symbols and press enter.', 'wc-nyp-suggested-amounts' ) ); ?>
					
					<textarea style="display: none;" id="wc_nyp_suggested_amounts_data" name="suggested_amounts"><?php echo esc_js( wp_json_encode( $amounts ) ); ?></textarea>

			</p>

	
			<fieldset id="wc_nyp_suggested_amounts" data-suggested-amounts="<?php echo esc_attr( wp_json_encode( $amounts ) ); ?>"  class="form-field suggested_amounts wc-metaboxes wc-metaboxes-wrapper" style="<?php echo esc_attr( $use_suggested ? '' : 'display:none' ); ?>"></fieldset>
			
			<script type="text/html" id="tmpl-nyp-suggested-amount">
				<div class="wc_nyp_suggested_amount wc-metabox closed" data-amount="{{ data.amount }}" data-default="{{ data.default }}">
					<h3>
						<a href="#" class="remove_amount delete" rel="icon"><?php esc_html_e( 'Remove', 'wc-nyp-suggested-amounts' ); ?></a>
						<div class="tips sort" data-tip="<?php esc_attr_e( 'Drag and drop rows to re-order', 'wc-nyp-suggested-amounts' ); ?>"></div>
						<strong>{{ data.formatted_amount }}</strong>
					</h3>
				</div>
			</script>

	<?php

	}

	/**
	 * Save extra meta info
	 *
	 * @param object $product
	 */
	public static function save_product_meta( $product ) {

		if ( isset( $_POST['suggested_amounts'] ) ) {

			$suggested_amounts = json_decode( wp_unslash( $_POST['suggested_amounts'] ) );

			if ( ! array( $suggested_amounts ) || empty ( $suggested_amounts ) ) {
				return;
			}

			$max_loop = max( array_keys( $suggested_amounts ) );

			$amounts = array();

			for ( $i = 0; $i <= $max_loop; $i++ ) {
				if ( empty( $suggested_amounts[ $i ] ) && property_exists( $suggested_amounts[ $i ], 'amount' ) ) {
					continue;
				}

				$amount = wc_format_decimal( wc_clean( wp_unslash( $suggested_amounts[ $i ]->amount ) ) );

				// This runs after NYP so min and max should exist in meta.
				$maximum = $product->get_meta( '_maximum_price', true );
				$minimum = $product->get_meta( '_min_price', true );

				if ( '' !== $maximum && $amount > $maximum ) {
					$error_notice = esc_html__( 'Your suggested amounts cannot be higher than the current maximum price. Please review your prices.', 'wc-nyp-suggested-amounts' );
					WC_Admin_Meta_Boxes::add_error( $error_notice );
					continue;
				} else if ( '' !== $minimum && $amount < $minimum ) {
					$error_notice = esc_html__( 'Your suggested amounts cannot be lower than the current minimum price. Please review your prices.', 'wc-nyp-suggested-amounts' );
					WC_Admin_Meta_Boxes::add_error( $error_notice );
					continue;
				}

				$amounts[] = array( 'amount' => $amount, 'default' => 'no' );

			}

			// Save the amounts.
			if( ! empty ( $amounts ) ) {
				$product->update_meta_data( '_wc_nyp_suggested_amounts', $amounts );
			} else {
				$product->delete_meta_data( '_wc_nyp_suggested_amounts' );
			}

		}

		if ( isset( $_POST['wc_nyp_use_suggested_amounts'] ) ) {
			$product->update_meta_data( '_wc_nyp_use_suggested_amounts', 'yes' );
		} else {
			$product->delete_meta_data( '_wc_nyp_use_suggested_amounts' );
		}

	}

	/*-----------------------------------------------------------------------------------*/
	/* Front-end */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * Display the amounts on the front end.
	 * 
	 * @param WC_Product $product
	 * @param string $suffix
	 */
	public static function display_amounts( $product, $suffix ) {
		global $product;
		
		$suggested_amounts = self::get_suggested_amounts( $product );

		if ( ! empty( $suggested_amounts ) && is_array( $suggested_amounts ) ) {

			echo '<ul class="suggested-amounts">';

			foreach( $suggested_amounts as $i => $suggested_amount ) {
				echo '<li><input type="radio" id="suggested-amount' . $suffix . '-' . $i .'" name="suggested-amount' . $suffix . '" value="' . esc_attr( $suggested_amount["amount"] ) . '" class="suggested-amount"/>
				<label for="suggested-amount' . $suffix . '-' . $i .'">'  . wc_price( $suggested_amount['amount'] ) . '</label></li>';
			}

			echo '<li><input type="radio" id="suggested-amount' . $suffix . '-custom" name="suggested-amount' . $suffix . '" value="custom" class="suggested-amount"/>
				<label for="suggested-amount' . $suffix . '-custom">' .  esc_html__( "Custom", "wc-nyp-suggested-amounts" ) . '</label></li>';

			echo '</ul>';
		}

	}

	/**
	 * Link buttons to NYP field.
	 */
	public static function frontend_scripts() {
		
		wp_add_inline_script( 'woocommerce-nyp', 'jQuery(document).ready(function($){

			$( ".nyp" ).each( function( i ) {
				let $input = $( this ).find( ".nyp-input" );
				let $selected_amounts = $( this ).find( ".suggested-amount" );

				$selected_amounts.on( "change", function() {
					let selected_amount = $selected_amounts.filter( ":checked" ).val();

					if ( "custom" === selected_amount ) {
						$input.val( "" ).trigger( "focus" );
					} else {
						$input.val( woocommerce_nyp_format_price( selected_amount ) ).trigger( "change" );
					}
				} );
			} );

		} );' );

		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		// Frontend styles.
		wp_enqueue_style( 'wc-nyp-suggested-amounts-frontend', self::get_plugin_url() . '/assets/css/admin/wc-nyp-suggested-amounts-frontend'. $suffix . '.css', array( 'woocommerce-nyp' ), self::get_version() );

		wp_style_add_data( 'wc-nyp-suggested-amounts-frontend', 'rtl', 'replace' );

		if ( $suffix ) {
			wp_style_add_data( 'wc-nyp-suggested-amounts-frontend', 'suffix', '.min' );
		}

	}

	/**
	 * Change the posted price for graceful degradation.
	 * 
	 * @param   mixed obj|int $product
	 * @param   string        $suffix - needed for composites and bundles
	 * @return  string
	 */
	public static function posted_price( $posted_price, $product, $suffix ) {
		if ( isset( $_REQUEST['suggested-amount' . $suffix ] ) && 'custom' !== wp_unslash( $_REQUEST['suggested-amount' . $suffix ] ) ) {
			$posted_price = WC_Name_Your_Price_Helpers::standardize_number( sanitize_text_field( wp_unslash( $_REQUEST['suggested-amount' . $suffix ] ) ) ); 
		}
		return $posted_price;
	}

	/*-----------------------------------------------------------------------------------*/
	/* Helper Functions */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * Get plugin version
	 */
	public static function get_version() {
		return ! defined( 'WC_NYP_DEBUG' ) || ! WC_NYP_DEBUG ? self::VERSION : time();
	}

	/**
	 * Get plugin path
	 */
	public static function get_plugin_path() {
		if( self::$plugin_path === '' ) {
			self::$plugin_path = untrailingslashit( plugin_dir_path(__FILE__) );
		}
		return self::$plugin_path;
	}

	/**
	 * Get plugin URL
	 */
	public static function get_plugin_url() {
		if( self::$plugin_url === '' ) {
			self::$plugin_url = untrailingslashit( plugin_dir_url(__FILE__) );
		}
		return self::$plugin_url;
	}

	/**
	 * Get suggested amounts
	 * @param  WC_Product $product
	 * @return array.
	 */
	public static function get_suggested_amounts( $product ) {
		$amounts = array();

		if( ! $product ) {
			return $amounts;
		}

		return $product->get_meta( '_wc_nyp_suggested_amounts', true );

	}

}
add_action( 'plugins_loaded', array( 'WC_NYP_Suggested_Amounts', 'init' ), 99 );