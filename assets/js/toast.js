/* Genuine — Recent Sales: front-end toast. Config comes from window.GRS_CFG (localized). */
( function () {
	var CFG = window.GRS_CFG;
	if ( ! CFG || ! window.fetch ) { return; }

	var PREVIEW = !! CFG.preview;
	var CAP     = PREVIEW ? 24 : ( CFG.cap || 0 );
	var DELAY   = PREVIEW ? 1500 : CFG.delay;
	var VISIBLE = CFG.visible || 6000;
	var GAP     = PREVIEW ? 6500 : CFG.interval;
	var BP      = CFG.mobileBp || 600;

	var shown = 0, stop = false, queue = [], qi = 0, root = null;

	function esc( s ) {
		return String( s == null ? '' : s ).replace( /[&<>"]/g, function ( m ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ m ];
		} );
	}
	function shuffle( a ) {
		for ( var i = a.length - 1; i > 0; i-- ) { var j = Math.floor( Math.random() * ( i + 1 ) ); var x = a[ i ]; a[ i ] = a[ j ]; a[ j ] = x; }
		return a;
	}

	function line2( o ) {
		if ( o.variants && o.variants.length ) {
			var parts = o.variants.map( function ( x ) {
				var dot = ( CFG.showSwatch && x.c ) ? '<span class="grs-vdot" style="background:' + esc( x.c ) + '"></span>' : '';
				return dot + esc( x.v );
			} );
			return '<div class="grs-v">' + parts.join( '<span class="grs-sep">·</span>' ) + '</div>';
		}
		if ( o.extra ) { return '<div class="grs-v">' + esc( o.extra ) + '</div>'; }
		return '';
	}

	function render( o ) {
		var badge = CFG.badge ? '<span class="grs-badge">✓ ' + esc( CFG.badgeLabel ) + '</span>' : '';
		var who   = esc( o.first ) + ( o.town ? ' from ' + esc( o.town ) : '' ) + ( o.state ? ', ' + esc( o.state ) : '' );
		var thumb = ( CFG.style === 'photo' && o.img ) ? '<span class="grs-thumb"><img src="' + esc( o.img ) + '" alt="" loading="lazy"></span>' : '';
		var body  = '<div class="grs-body">' +
			'<div class="grs-k"><span class="grs-dot"></span>' + esc( CFG.kicker ) + badge + '</div>' +
			'<div class="grs-p"><span class="grs-q">' + ( parseInt( o.qty, 10 ) || 1 ) + '×</span> ' + esc( o.product ) + '</div>' +
			line2( o ) +
			'<div class="grs-a"><span class="grs-who">' + who + '</span>' + ( o.ago ? '<span class="grs-sep">·</span> ' + esc( o.ago ) : '' ) + '</div>' +
			'</div>';
		var open  = o.url ? '<a class="grs-link" href="' + esc( o.url ) + '">' : '<div class="grs-link">';
		var close = o.url ? '</a>' : '</div>';

		var t = document.createElement( 'div' );
		t.className = 'grs-t';
		t.innerHTML = open + thumb + body + close +
			'<button class="grs-x" type="button" aria-label="' + esc( CFG.dismissAria ) + '">×</button>';

		t.querySelector( '.grs-x' ).addEventListener( 'click', function ( ev ) {
			ev.preventDefault(); ev.stopPropagation();
			if ( ! PREVIEW ) { try { localStorage.setItem( 'grs_dismissed', String( Date.now() ) ); } catch ( e ) {} }
			hide( t ); stop = true;
		} );

		root.innerHTML = ''; root.appendChild( t ); place();
		requestAnimationFrame( function () { requestAnimationFrame( function () { t.classList.add( 'grs-show' ); } ); } );
		setTimeout( place, 1500 ); setTimeout( place, 4000 );
		shown++;
		if ( ! PREVIEW ) { try { sessionStorage.setItem( 'grs_shown', String( shown ) ); } catch ( e ) {} }
	}

	// Drop the class to fade out, then take the node OUT of the DOM once the transition ends.
	// Leaving it parked at opacity:0 leaves an invisible, still-clickable card over the page.
	function hide( t ) {
		if ( ! t ) { return; }
		t.classList.remove( 'grs-show' );
		t.setAttribute( 'aria-hidden', 'true' );
		setTimeout( function () {
			if ( t.parentNode && ! t.classList.contains( 'grs-show' ) ) { t.parentNode.removeChild( t ); }
		}, 600 );
	}

	// Narrow viewports: bail before anything is created or fetched. The CSS carries the same
	// breakpoint, so a resize or rotation after load is covered too.
	function hiddenOnMobile() {
		if ( ! CFG.hideMobile || ! window.matchMedia ) { return false; }
		return window.matchMedia( '(max-width:' + BP + 'px)' ).matches;
	}

	function step() {
		if ( stop ) { return; }
		if ( CAP > 0 && shown >= CAP ) { return; }
		if ( qi >= queue.length ) { if ( PREVIEW ) { qi = 0; } else { return; } }
		render( queue[ qi++ ] );
		setTimeout( function () {
			hide( root.querySelector( '.grs-t' ) );
			setTimeout( function () { if ( ! stop ) { step(); } }, GAP );
		}, VISIBLE );
	}

	// Sit clear of any known floating widget (chat / cart / back-to-top) sharing the toast's corner.
	var AVOID = '#launcher,.zEWidget-launcher,#intercom-container,.intercom-lightweight-app,#drift-widget,.drift-frame-controller,.crisp-client,#tawkchat-container,.tawk-min-container,#chat-widget-container,.fb_dialog,#fkcart-floating-toggler,[class*="cart-float"],[id*="cart-float"],[class*="back-to-top"],[id*="back-to-top"],[class*="scroll-to-top"]';
	function place() {
		if ( ! root ) { return; }
		var mobile = window.matchMedia( '(max-width:' + BP + 'px)' ).matches;
		var lift = mobile ? 12 : 24;
		var tr = root.getBoundingClientRect();
		try {
			document.querySelectorAll( AVOID ).forEach( function ( el ) {
				if ( el === root || root.contains( el ) ) { return; }
				var r = el.getBoundingClientRect();
				if ( ! r.width || ! r.height || ( window.innerHeight - r.bottom ) > 240 ) { return; }
				if ( r.right < tr.left || r.left > tr.right ) { return; }
				lift = Math.max( lift, Math.round( window.innerHeight - r.top ) + 12 );
			} );
		} catch ( e ) {}
		root.style.bottom = lift + 'px';
	}

	function init() {
		if ( hiddenOnMobile() ) { return; }
		if ( ! PREVIEW ) {
			try {
				var d = localStorage.getItem( 'grs_dismissed' );
				if ( d && CFG.dismissDays > 0 && ( Date.now() - parseInt( d, 10 ) < CFG.dismissDays * 864e5 ) ) { return; }
			} catch ( e ) {}
			try { shown = parseInt( sessionStorage.getItem( 'grs_shown' ) || '0', 10 ) || 0; } catch ( e ) {}
			if ( CAP > 0 && shown >= CAP ) { return; }
		}
		root = document.createElement( 'div' );
		root.id = 'grs-sp';
		root.className = ( CFG.position === 'bottom-left' ) ? 'grs-left' : 'grs-right';
		if ( CFG.style === 'photo' ) { root.className += ' grs-has-photo'; }
		root.setAttribute( 'aria-live', 'polite' );
		document.body.appendChild( root );
		window.addEventListener( 'resize', place );

		fetch( CFG.endpoint, { credentials: 'omit' } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( data ) {
				if ( ! data || ! data.length ) { return; }
				queue = shuffle( data.slice() );
				setTimeout( step, DELAY );
			} )
			.catch( function () {} );
	}

	if ( document.readyState !== 'loading' ) { init(); } else { document.addEventListener( 'DOMContentLoaded', init ); }
} )();
