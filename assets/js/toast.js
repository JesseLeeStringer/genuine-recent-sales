/* Genuine — Recent Sales: front-end toast. Config comes from window.GRS_CFG (localized). */
( function () {
	var CFG = window.GRS_CFG;
	if ( ! CFG || ! window.fetch ) { return; }

	// wp_localize_script() sends every scalar as a STRING: 0 arrives as "0" (truthy) and false as "".
	// Read flags and numbers explicitly — `!! CFG.preview` made every live site run in preview mode.
	function flag( v ) { return v === true || v === 1 || v === '1'; }
	function num( v, d ) { var n = parseInt( v, 10 ); return isNaN( n ) ? d : n; }

	var PREVIEW = flag( CFG.preview );
	var CAP     = PREVIEW ? 24 : num( CFG.cap, 0 );
	var DELAY   = PREVIEW ? 1500 : num( CFG.delay, 12000 );
	var VISIBLE = num( CFG.visible, 6000 ) || 6000;
	var GAP     = PREVIEW ? 6500 : num( CFG.interval, 35000 );
	var BP      = num( CFG.mobileBp, 600 ) || 600;

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

	// GA4 events: grs_view / grs_click / grs_dismiss, sent only to the GA4 properties already on the
	// page through gtag — one call per GA4 (G-) destination, so nothing reaches Google Ads tags.
	// Destinations come from gtag('config','G-…') commands AND from the destinations gtag.js has
	// loaded (window.google_tag_manager), which covers a Google tag configured as GT-… (Site Kit).
	// The product name and the toast's position in the session go out — never the buyer's name or
	// town. No gtag function, or no GA4 destination on the page = no event.
	function track( name, o, seq, url ) {
		if ( ! flag( CFG.ga4 ) || PREVIEW || typeof window.gtag !== 'function' ) { return; }
		try {
			var ids = [];
			var add = function ( id ) { if ( typeof id === 'string' && /^G-/.test( id ) && ids.indexOf( id ) < 0 ) { ids.push( id ); } };
			( window.dataLayer || [] ).forEach( function ( e ) {
				if ( e && e[ 0 ] === 'config' ) { add( e[ 1 ] ); }
			} );
			if ( window.google_tag_manager ) { Object.keys( window.google_tag_manager ).forEach( add ); }
			ids.forEach( function ( id ) {
				var p = { send_to: id, grs_product: String( o.product == null ? '' : o.product ).slice( 0, 100 ), grs_seq: seq };
				if ( url ) { p.link_url = url; p.transport_type = 'beacon'; }
				window.gtag( 'event', name, p );
			} );
		} catch ( e ) {}
	}

	function line2( o ) {
		if ( o.variants && o.variants.length ) {
			var parts = o.variants.map( function ( x ) {
				var dot = ( flag( CFG.showSwatch ) && x.c ) ? '<span class="grs-vdot" style="background:' + esc( x.c ) + '"></span>' : '';
				return dot + esc( x.v );
			} );
			return '<div class="grs-v">' + parts.join( '<span class="grs-sep">·</span>' ) + '</div>';
		}
		if ( o.extra ) { return '<div class="grs-v">' + esc( o.extra ) + '</div>'; }
		return '';
	}

	function render( o ) {
		var seq   = shown + 1;
		var badge = flag( CFG.badge ) ? '<span class="grs-badge">✓ ' + esc( CFG.badgeLabel ) + '</span>' : '';
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

		var a = t.querySelector( 'a.grs-link' );
		if ( a ) {
			a.addEventListener( 'click', function () { track( 'grs_click', o, seq, a.href ); } );
			a.addEventListener( 'auxclick', function ( ev ) { if ( ev.button === 1 ) { track( 'grs_click', o, seq, a.href ); } } ); // middle-click / new tab
		}

		t.querySelector( '.grs-x' ).addEventListener( 'click', function ( ev ) {
			ev.preventDefault(); ev.stopPropagation();
			if ( ! PREVIEW ) { try { localStorage.setItem( 'grs_dismissed', String( Date.now() ) ); } catch ( e ) {} }
			track( 'grs_dismiss', o, seq );
			hide( t ); stop = true;
		} );

		root.innerHTML = ''; root.appendChild( t ); place();
		// A view is counted only once the card is actually rendered: requestAnimationFrame never runs in
		// a background tab, a card removed before its frame arrives is not counted, and a card inside a
		// container the hide-on-mobile CSS has set to display:none (after a rotation or resize) has no
		// client rects.
		requestAnimationFrame( function () { requestAnimationFrame( function () {
			if ( ! t.parentNode ) { return; }
			t.classList.add( 'grs-show' );
			if ( t.getClientRects().length && document.visibilityState !== 'hidden' ) { track( 'grs_view', o, seq ); }
		} ); } );
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
		if ( ! flag( CFG.hideMobile ) || ! window.matchMedia ) { return false; }
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
