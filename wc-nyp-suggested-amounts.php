<?php
/*
 * Plugin Name: WooCommerce Name Your Price - Suggested Amounts
 * Plugin URI: http://www.woocommerce.com/products/name-your-price/
 * Description: Propose multiple price suggestions
 * Version: 1.0.0-beta-1
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
	const VERSION = '1.0.0-beta-1';

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
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_product_meta' ) );

		// Frontend.
		add_action( 'wc_nyp_before_price_input', array( __CLASS__, 'display_amounts' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'frontend_scripts' ), 30 );

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
			wp_enqueue_script( 'wc-nyp-suggested-amounts-writepanel', self::get_plugin_url() . '/assets/js/admin/wc-nyp-suggested-amounts-writepanel.js', array( 'jquery' ), self::VERSION, true );

			$params = array(
					'save_amounts_nonce'            => wp_create_nonce( 'save-donation-amounts' ),
					'i18n_no_amounts_added'         => esc_js( __( 'No amount added', 'wc-nyp-suggested-amounts' ) ),
					'i18n_remove_amount'            => esc_js( __( 'Are you sure you want to remove this amount?', 'wc-nyp-suggested-amounts' ) ),
					'currency_format_num_decimals'  => esc_attr( wc_get_price_decimals() ),
					'currency_format_symbol'        => get_woocommerce_currency_symbol(),
					'currency_format_decimal_sep'   => esc_attr( wc_get_price_decimal_separator() ),
					'currency_format_thousand_sep'  => esc_attr( wc_get_price_thousand_separator() ),
					'currency_format'               => esc_attr( str_replace( array( '%1$s', '%2$s' ), array( '%s', '%v' ), get_woocommerce_price_format() ) ), // For accounting.js
				);

			wp_localize_script( 'wc-nyp-suggested-amounts-writepanel', 'WC_NYP_SUGGESTED_AMOUNTS_ADMIN_META_BOX', $params );
			
			// Metabox styles.
			wp_enqueue_style( 'wc-nyp-suggested-amounts-writepanel', self::get_plugin_url() . '/assets/css/admin/wc-nyp-suggested-amounts-writepanel.css', array(), self::VERSION );

		}

	}

	/**
	 * Add suggested inputs to product metabox
	 *
	 * @param  object WC_Product $product_object
	 * @param  bool $show_billing_period_options
	 * @param  mixed int|false $loop - for use in variations
	 * @return print HTML
	 * @since  2.8.0
	 */
	public static function admin_add_suggested_amounts( $product_object, $show_billing_period_options, $loop = false ) { ?>

		<?php

		$amounts = $product_object->get_meta( '_suggested_amounts' );
		if( $amounts === '' ) {
			$amounts = array();
		}

		?>
	
			<p class="form-field nyp_suggested_amounts">

				<label for="_suggested_amounts"><?php  printf( __( 'Suggested amounts (%s)', 'wc-nyp-suggested-amounts' ), get_woocommerce_currency_symbol() ); ?></label>

					<span class="add_nyp_suggested_amount" style="width: 100%">
						<span class="add_prompt dashicons dashicons-plus"></span>
						<input type="text" class="short wc_input_price" name="nyp_suggested_amount" placeholder="<?php _e( 'Add a suggested amount&hellip;', 'wc-nyp-suggested-amounts' ); ?>" />

						<?php echo wc_help_tip( __( 'Enter a suggested amount without any currency symbols and press enter.', 'wc-nyp-suggested-amounts' ) ); ?>
					</span>

					<textarea style="display: none;" id="wc_nyp_suggested_amounts_data" name="suggested_amounts"><?php echo esc_js( wp_json_encode( $amounts ) ); ?></textarea>

			</p>

			<div class="wc-metaboxes-wrapper" style="float: none;">
				<div id="wc_nyp_suggested_amounts" data-suggested-amounts="<?php echo esc_attr( wp_json_encode( $amounts ) ); ?>"  class="suggested_amounts wc-metaboxes"></div>
			</div>	

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
	 * @return void
	 * @since 1.0 (renamed in 2.0)
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

				$amount = wc_clean( $suggested_amounts[ $i ]->amount );
				$amounts[] = array( 'amount' => $amount, 'default' => 'no' );

			}

			// Save the amounts.
			if( ! empty ( $amounts ) ) {
				$product->update_meta_data( '_suggested_amounts', $amounts );
			} else {
				$product->delete_meta_data( '_suggested_amounts' );
			}

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
		
		$custom_css = "
				.nyp .suggested-amounts { margin-left: 0; margin-right: 0; list-style: none; display: flex; }
				.nyp .suggested-amounts > li { margin-left: 0; margin-right: .5em; }
				.nyp .suggested-amounts label { font-weight: normal; padding: .5em 1em; border: 1px solid; cursor: pointer; }
				.nyp .suggested-amount:checked+label{ font-weight: bold; color: var( --wc-primary-text ); background: var( --wc-primary ); border: 1px solid transparent; } 
				 .nyp .suggested-amount {
					display: none;
				}";
        wp_add_inline_style( 'woocommerce-nyp', $custom_css );

	}

	

	/*-----------------------------------------------------------------------------------*/
	/* Helper Functions */
	/*-----------------------------------------------------------------------------------*/

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

		return $product->get_meta( '_suggested_amounts', true );

	}

}
add_action( 'plugins_loaded', array( 'WC_NYP_Suggested_Amounts', 'init' ), 99 );