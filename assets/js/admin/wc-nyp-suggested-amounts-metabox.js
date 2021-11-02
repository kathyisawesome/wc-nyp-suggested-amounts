/* global wp, WC_NYP_SUGGESTED_AMOUNTS_ADMIN_META_BOX, accounting */
jQuery( function( $ ) {
    'use strict';

	/**
	 * Variations actions
	 */
	var wc_nyp_suggested_amounts_actions = {

		/**
		 * Initialize amounts actions
		 */
		init: function() { 

			var $product_data = $( '#wc_nyp_suggested_amounts' );

			var amounts = $( '#wc_nyp_suggested_amounts' ).data( 'suggested-amounts' );

			if ( amounts && amounts.length ) {

				for ( var index in amounts ) {
					if ( amounts.hasOwnProperty( index ) ) {
						wc_nyp_suggested_amounts_actions.render_amount( amounts[index] )
					}
				}

			}

			// Allow sorting.
			$product_data.sortable({
				items:                '.wc_nyp_suggested_amount',
				cursor:               'move',
				axis:                 'y',
				scrollSensitivity:    40,
				forcePlaceholderSize: true,
				helper:               'clone',
				opacity:              0.65,
				stop:                 function() {
				    wc_nyp_suggested_amounts_actions.trigger_needs_update();
				}
			});

			// Init TipTip.
			$( '.woocommerce-help-tip', $product_data ).tipTip({
				'attribute': 'data-tip',
				'fadeIn':    50,
				'fadeOut':   50,
				'delay':     200
			});
			
			// Events.
			$( '#_wc_nyp_use_suggested_amounts' ).on( 'change', this.toggle );
			$( '.add_nyp_suggested_amount ' ).on( 'keypress', '.wc_input_price', this.enter_amount )
			$( '#wc_nyp_suggested_amounts' ).on( 'click', '.remove_amount', this.remove_amount );
			$( '#wc_nyp_suggested_amounts' ).on( 'wc_nyp_suggested_amounts_changed', this.update_amounts );
		},


		/**
		 * Render an amount.
		 * 
		 * @param obj { amount: 99, default: no }
		 */
		render_amount: function( data ) {

			var amount = wc_nyp_suggested_amounts_unformat_price( data.amount );
			var formatted_amount = wc_nyp_suggested_amounts_format_price( amount, WC_NYP_SUGGESTED_AMOUNTS_ADMIN_META_BOX.currency_format_symbol, true);

			// Insert new amount.
			var template = wp.template( 'nyp-suggested-amount' );

			$( '#wc_nyp_suggested_amounts' ).append( template( { amount: amount, formatted_amount: formatted_amount, default: 0 } ) );

		},


		/**
		 * Add amount
		 *
		 * @return {Bool}
		 */
		enter_amount: function(e) {

        	if( e.which === 13 ){
        		
        		e.preventDefault();
        		
        		var val = $(this).val();

	            // Disable textbox to prevent multiple submit.
	            $(this).attr( 'disabled', 'disabled' );
	
	            // Do Stuff, submit, etc..
				wc_nyp_suggested_amounts_actions.add_amount( val );
				
	            // Enable the textbox again if needed.
	            $(this).removeAttr( 'disabled' ).val( '' );
	         }
	         
		},
		
		/**
		 * Add amount
		 *
		 * @return {Bool}
		 */
		add_amount: function( value, meta ) {

			if( value === undefined ) {
				value = 0;
			}

			let amount = { amount: value };

			wc_nyp_suggested_amounts_actions.render_amount( amount );

			wc_nyp_suggested_amounts_actions.trigger_needs_update();
		
			return false;
		},

		/**
		 * Remove amount
		 *
		 * @return {Bool}
		 */
		remove_amount: function() {

			if ( window.confirm( WC_NYP_SUGGESTED_AMOUNTS_ADMIN_META_BOX.i18n_remove_amount ) ) {
			
				var $amount = $(this).closest( '.wc_nyp_suggested_amount' );
				$amount.remove();
				wc_nyp_suggested_amounts_actions.trigger_needs_update();

			}

			return false;
		},


		/**
		 * Adds attribute when amounts are changed.
		 */
		trigger_needs_update: function() {
			$( '#wc_nyp_suggested_amounts' ).data( 'needs_update', 1 ).trigger( 'wc_nyp_suggested_amounts_changed' );
		},

		/**
		 * Check if have some changes before leave the page
		 *
		 * @return {Bool}
		 */
		if_needs_update: function() {
			var needs_update = $( '#wc_nyp_suggested_amounts' ).data( 'needs_update' );
			return ( needs_update == '1' ? true : false );
		},

		/**
		 * Update textarea
		 *
		 */
		update_amounts: function() {
			$( '#wc_nyp_suggested_amounts_data' ).text( JSON.stringify( wc_nyp_suggested_amounts_actions.get_amounts_fields() ) );
		},	

		/**
		 * Get amounts fields and convert to object
		 *
		 *
		 * @return {Object}
		 */
		get_amounts_fields: function() {
			var data = [];

			$( '#wc_nyp_suggested_amounts .wc_nyp_suggested_amount' ).each( function( index, element ) {
				data[index] = this.dataset;
			});

			return data;
		},

		/**
		 * Toggle display of suggested amounts UI
		 */
		toggle: function() {

			if( this.checked ) {
				$( '.form-field.nyp_suggested_amounts, #wc_nyp_suggested_amounts' ).show();
				$( '.form-field._suggested_price_field' ).hide();
			} else {
				$( '.form-field.nyp_suggested_amounts, #wc_nyp_suggested_amounts' ).hide();
				$( '.form-field._suggested_price_field' ).show();
			}

		}
					 
	};

	wc_nyp_suggested_amounts_actions.init();

	/**
	 * Helper functions
	 */
	// Format the price with accounting.js.
	function wc_nyp_suggested_amounts_format_price( price, currency_symbol, format ){

		if ( typeof currency_symbol === 'undefined' ) {
			currency_symbol = '';
		}

		if ( typeof format === 'undefined' ) {
			format = false;
		}

		var currency_format = format ? WC_NYP_SUGGESTED_AMOUNTS_ADMIN_META_BOX.currency_format : '%v';

		return accounting.formatMoney( price, {
				symbol : currency_symbol,
				decimal : WC_NYP_SUGGESTED_AMOUNTS_ADMIN_META_BOX.currency_format_decimal_sep,
				thousand: WC_NYP_SUGGESTED_AMOUNTS_ADMIN_META_BOX.currency_format_thousand_sep,
				precision : WC_NYP_SUGGESTED_AMOUNTS_ADMIN_META_BOX.currency_format_num_decimals,
				format: currency_format
		}).trim();

	}

	// Get absolute value of price and turn price into float decimal.
	function wc_nyp_suggested_amounts_unformat_price( price ){
		return Math.abs( parseFloat( accounting.unformat( price, WC_NYP_SUGGESTED_AMOUNTS_ADMIN_META_BOX.currency_format_decimal_sep ) ) );
	}
});
