/* Genuine — Recent Sales: admin settings */
jQuery( function ( $ ) {
	$( '.grs-color' ).wpColorPicker();

	var $mode = $( 'select[name="grs_settings[appearance]"]' );

	function sync() {
		var v = $mode.val();
		$( '.grs-when-preset' ).toggle( v === 'preset' );
		$( '.grs-when-custom' ).toggle( v === 'custom' );
		$( '.grs-when-accent' ).toggle( v === 'custom' || v === 'inherit' ); // the accent applies in Match page too
	}

	$mode.on( 'change', sync );
	sync();
} );
