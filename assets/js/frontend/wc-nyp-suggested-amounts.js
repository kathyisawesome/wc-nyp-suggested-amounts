/**
 * Suggested Amounts add to cart script.
 */
( function( $ ) {

	$( document ).on( 'wc-nyp-initialized', function( e, nypForm ) {
		
        nypForm.nypProducts.map( (nypProduct) => {

            let $input = nypProduct.$el.find( '.nyp-input' );
            let $selected_amounts = nypProduct.$el.find( '.suggested-amounts__amount input[type=radio]' );
            
            $selected_amounts.on( 'change', function() {
                var selected_amount = $selected_amounts.filter( ':checked' ).val();
    
                if ( 'custom' === selected_amount ) {
                    $input.val( '' ).trigger( 'focus' );
                } else {
                    $input.val( woocommerce_nyp_format_price( selected_amount ) ).trigger( 'change' );
                }
            } );
    
            $selected_amounts.filter( ':checked' ).trigger( 'change' );
    
            $input.on( 'focus', function() {
                $selected_amounts.filter( 'input[value=custom]' ).prop( 'checked', true );
            } );

        } );
	});

} ) ( jQuery );