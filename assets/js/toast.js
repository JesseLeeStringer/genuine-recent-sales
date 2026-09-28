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
	var AUTO    = CFG.appearance === 'inherit'; // "Match page": pick a light or dark card from what is behind it
	var SIDE    = ( CFG.position === 'bottom-left' ) ? 'grs-left' : 'grs-right';
	var RETRY   = 4000; // a spot that would cover a control is re-tried this often…
	var RETRIES = 30;   // …for about two minutes per toast, then this page gives up

	var shown = 0, stop = false, queue = [], qi = 0, root = null, retries = 0, lastFocus = null, mo = null, moQueued = false, cctx = null;

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

	// ── Hit-testing helpers ────────────────────────────────────────
	// The topmost page element at a point, ignoring the toast itself.
	function topAt( x, y ) {
		var list = document.elementsFromPoint ? document.elementsFromPoint( x, y ) : [];
		for ( var i = 0; i < list.length; i++ ) {
			if ( list[ i ] !== root && ! root.contains( list[ i ] ) ) { return list[ i ]; }
		}
		return null;
	}

	// ── "Match page" (appearance = inherit) ──────────────────────
	// System colours (Canvas/CanvasText) follow color-scheme, which most themes never set, so a
	// dark site got a white card. Instead: read the opaque background actually behind the toast and
	// switch between a light and a dark card (colours live in toast.css).
	function rgb( s ) {
		if ( ! s || s === 'transparent' ) { return null; }
		var m = /^rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)(?:\s*[,/]\s*([\d.]+)(%?))?/.exec( s );
		if ( m ) {
			var a = m[ 4 ] === undefined ? 1 : parseFloat( m[ 4 ] ) / ( m[ 5 ] ? 100 : 1 );
			return [ +m[ 1 ], +m[ 2 ], +m[ 3 ], a ];
		}
		// Modern colour syntaxes (oklch(), lab(), color(srgb …) — what browsers report for colours
		// authored that way): let a 1×1 canvas convert them to sRGB. A value the canvas cannot parse
		// leaves the sentinel in place and counts as unknown.
		try {
			if ( ! cctx ) {
				var c = document.createElement( 'canvas' );
				c.width = c.height = 1;
				cctx = c.getContext( '2d', { willReadFrequently: true } );
			}
			cctx.fillStyle = 'rgba(1, 2, 3, 0.5)';
			var sentinel = cctx.fillStyle;
			cctx.fillStyle = s;
			if ( cctx.fillStyle === sentinel ) { return null; }
			cctx.clearRect( 0, 0, 1, 1 );
			cctx.fillRect( 0, 0, 1, 1 );
			var d = cctx.getImageData( 0, 0, 1, 1 ).data;
			return [ d[ 0 ], d[ 1 ], d[ 2 ], d[ 3 ] / 255 ];
		} catch ( e ) { return null; }
	}
	function lum( c ) {
		var f = function ( v ) { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 ); };
		return 0.2126 * f( c[ 0 ] ) + 0.7152 * f( c[ 1 ] ) + 0.0722 * f( c[ 2 ] );
	}
	function pageIsDark() {
		var r  = root.getBoundingClientRect();
		var el = topAt(
			Math.min( Math.max( r.left + r.width / 2, 1 ), window.innerWidth - 2 ),
			Math.min( Math.max( r.bottom - 8, 1 ), window.innerHeight - 2 )
		);
		var start = el;
		// The first opaque background of a page BAND (a section at least half the viewport wide) below
		// <body> decides — not a product photo or card that happens to sit under the toast, which on a
		// dark site would give a white toast. 0.179 = the luminance where black and white text have
		// equal contrast.
		for ( ; el && el !== document.body && el !== document.documentElement; el = el.parentElement ) {
			var c = rgb( getComputedStyle( el ).backgroundColor );
			if ( c && c[ 3 ] > 0.5 && el.getBoundingClientRect().width >= window.innerWidth * 0.5 ) { return lum( c ) < 0.179; }
		}
		// Section painted with an image, or nothing opaque: light text there means a dark page.
		if ( start ) {
			var t = rgb( getComputedStyle( start ).color );
			if ( t && lum( t ) > 0.5 ) { return true; }
		}
		var b = rgb( getComputedStyle( document.body ).backgroundColor );
		if ( ! b || b[ 3 ] <= 0.5 ) { b = rgb( getComputedStyle( document.documentElement ).backgroundColor ); }
		return !! ( b && b[ 3 ] > 0.5 && lum( b ) < 0.179 );
	}
	function scheme() {
		if ( ! AUTO || ! root ) { return; }
		try {
			var dark = pageIsDark();
			root.classList.toggle( 'grs-on-dark', dark );
			root.classList.toggle( 'grs-on-light', ! dark );
		} catch ( e ) {}
	}
	// Theme switches arrive as class/data-theme/style changes on <html>/<body>. Many themes also touch
	// those on every scroll, so coalesce to one check per frame, and only while a card is on screen.
	function schemeSoon() {
		if ( moQueued ) { return; }
		moQueued = true;
		requestAnimationFrame( function () {
			moQueued = false;
			if ( root && root.querySelector( '.grs-t' ) ) { scheme(); }
		} );
	}
	// No more toasts on this page (dismissed, cap reached, queue done, or never a clear spot): stop
	// watching the page.
	function finish() {
		if ( mo ) { mo.disconnect(); mo = null; }
		window.removeEventListener( 'resize', place );
		window.removeEventListener( 'scroll', onScroll );
		clearTimeout( scrollTimer );
	}

	// ── Keep clear of the page's controls ────────────────────────
	// A toast must never sit over a button, a form control, a filter or a navigation link — at a
	// laptop height a bottom corner is often exactly where a hero's call-to-action or a shop's filter
	// sidebar lives. Ordinary text links and whole-card product links are fine to cover briefly.
	var KEEP = 'button,input:not([type="hidden"]),select,textarea,summary,[role="button"],[role="checkbox"],[role="radio"],[role="tab"],[role="switch"],' +
		'a.button,a[class*="button"],a[class*="btn"],.elementor-button,.wp-block-button__link,.add_to_cart_button';
	// Filter / facet widgets from WooCommerce and the common filter plugins.
	var FILTERS = '.woocommerce-widget-layered-nav,.widget_layered_nav,.widget_layered_nav_filters,.widget_rating_filter,.widget_price_filter,' +
		'.wc-block-product-filters,.wp-block-woocommerce-product-filters,.wc-block-active-filters,' +
		'.facetwp-facet,.searchandfilter,.wpc-filters-main-wrap,.wpc-filters-open-button-container,.wpc-filters-open-widget,' +
		'.jet-smart-filters,.berocket_single_filter_widget,.wcpf-filter,.yith-wcan-filters';
	// A home-made filter panel: an ancestor with a class token that has "filter(s)"/"facet(s)" as one of
	// its words ("filter-panel", "category-filters", "sgp26-facet", "filters-area"). WordPress and
	// WooCommerce write taxonomy classes ("category-oil-filters", "product_brand-ryco-filters") only
	// on <body> and on post/product wrappers (which carry post-<id> / type-<type> / hentry), so those
	// elements are never taken as a panel. Nor is a wrapper that holds results (products, posts).
	var FILTER_WORD = /^(?:filters?|facets?|filterbar|filtering)$/i;
	var POST_WRAP   = /^(?:post-\d+|type-[\w-]+|hentry)$/;
	var RESULTS     = '.products,li.product,article,.hentry,.e-loop-item,[class^="type-"],[class*=" type-"]';
	function panelVerdict( n ) {
		var cl = n.classList, filterish = false, i;
		for ( i = 0; i < cl.length; i++ ) { if ( POST_WRAP.test( cl[ i ] ) ) { return false; } }
		for ( i = 0; i < cl.length && ! filterish; i++ ) {
			var parts = cl[ i ].split( /[-_]/ );
			for ( var j = 0; j < parts.length; j++ ) { if ( FILTER_WORD.test( parts[ j ] ) ) { filterish = true; break; } }
		}
		if ( ! filterish ) { return null; }                 // not a panel — keep looking further up
		return ! n.querySelector( RESULTS );                 // a panel, unless it wraps the results
	}
	function inFilterPanel( el, memo ) {
		for ( var n = el.parentElement; n && n !== document.body && n !== document.documentElement; n = n.parentElement ) {
			var v = memo.has( n ) ? memo.get( n ) : panelVerdict( n );
			memo.set( n, v );
			if ( v !== null ) { return v; }
		}
		return false;
	}
	// Links only count as controls when they navigate the site or filter it; ordinary text links and
	// whole-card product/post links are fine to cover briefly.
	function linkIsControl( el, memo ) {
		return !! el.closest( 'nav,[role="navigation"],' + FILTERS ) || inFilterPanel( el, memo );
	}
	function coversControl() {
		var box = root.getBoundingClientRect(); // the card's own transform does not move its container
		if ( ! box.width || ! box.height ) { return false; }
		var area = box.width * box.height, els, memo = new Map(); // panel verdicts cached per ancestor for this pass
		try { els = document.querySelectorAll( KEEP + ',a[href]' ); } catch ( e ) { return false; }
		for ( var i = 0; i < els.length; i++ ) {
			var el = els[ i ];
			if ( root.contains( el ) ) { continue; }
			var r = el.getBoundingClientRect();
			if ( r.width < 1 || r.height < 1 ) { continue; }
			var ix = Math.min( r.right, box.right ) - Math.max( r.left, box.left );
			var iy = Math.min( r.bottom, box.bottom ) - Math.max( r.top, box.top );
			if ( ix < 6 || iy < 6 ) { continue; }
			try {
				// Buttons and form controls always count, whatever their size (a wide button, a big
				// textarea). A plain link counts only if it is small (not a whole card) and navigational.
				if ( ! el.matches( KEEP ) && ( r.width * r.height > area * 1.5 || ! linkIsControl( el, memo ) ) ) { continue; }
			} catch ( e ) { continue; }
			// Only count it if it is really on top there (not behind an overlay, not in a closed menu).
			var top = topAt( Math.max( r.left, box.left ) + ix / 2, Math.max( r.top, box.top ) + iy / 2 );
			if ( top && ( top === el || el.contains( top ) ) ) { return true; }
		}
		return false;
	}
	// The check above runs when a toast appears. If the visitor then scrolls a control under a toast
	// that is still showing, let it go early once scrolling stops, instead of sitting on the control.
	var scrollTimer = null;
	function onScroll() {
		clearTimeout( scrollTimer );
		scrollTimer = setTimeout( function () {
			var t = root && root.querySelector( '.grs-t.grs-show' );
			if ( ! t || t.grsGone || ( t.grsHold && t.grsHold() ) || ! coversControl() ) { return; }
			t.grsGone = true;
			releaseFocus( t, t.grsKb && t.grsKb() );
			hide( t );
			setTimeout( function () { if ( ! stop ) { step(); } }, GAP );
		}, 150 );
	}
	function setSide( side ) {
		root.classList.remove( 'grs-left', 'grs-right' );
		root.classList.add( side );
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

	// Build the card and show it. Returns false (and shows nothing) when every corner it could use
	// would cover one of the page's controls.
	function render( o ) {
		var seq   = shown + 1;
		lastFocus = null;
		var badge = flag( CFG.badge ) ? '<span class="grs-badge">✓ ' + esc( CFG.badgeLabel ) + '</span>' : '';
		var who   = esc( o.first ) + ( o.town ? ' from ' + esc( o.town ) : '' ) + ( o.state ? ', ' + esc( o.state ) : '' );
		var thumb = ( CFG.style === 'photo' && o.img ) ? '<span class="grs-thumb"><img src="' + esc( o.img ) + '" alt="" loading="lazy"></span>' : '';
		var body  = '<div class="grs-body">' +
			'<div class="grs-k"><span class="grs-dot"></span>' + esc( CFG.kicker ) + badge + '</div>' +
			'<div class="grs-p" title="' + esc( o.product ) + '"><span class="grs-q">' + ( parseInt( o.qty, 10 ) || 1 ) + '×</span> ' + esc( o.product ) + '</div>' +
			line2( o ) +
			'<div class="grs-a"><span class="grs-who">' + who + '</span>' +
				( o.ago ? ' <span class="grs-sep" aria-hidden="true">·</span> <span class="grs-ago">' + esc( o.ago ) + '</span>' : '' ) +
			'</div>' +
			'</div>';
		var open  = o.url ? '<a class="grs-link" href="' + esc( o.url ) + '">' : '<div class="grs-link">';
		var close = o.url ? '</a>' : '</div>';

		var t = document.createElement( 'div' );
		t.className = 'grs-t';
		t.innerHTML = open + thumb + body + close +
			'<button class="grs-x" type="button" aria-label="' + esc( CFG.dismissAria ) + '">×</button>';

		root.innerHTML = ''; root.appendChild( t );
		setSide( SIDE ); place();
		if ( coversControl() ) {
			setSide( SIDE === 'grs-left' ? 'grs-right' : 'grs-left' ); place();
			if ( coversControl() ) {
				setSide( SIDE ); place();
				root.removeChild( t );
				return false;
			}
		}
		scheme();

		var a = t.querySelector( 'a.grs-link' );
		if ( a ) {
			a.addEventListener( 'click', function () { track( 'grs_click', o, seq, a.href ); } );
			a.addEventListener( 'auxclick', function ( ev ) { if ( ev.button === 1 ) { track( 'grs_click', o, seq, a.href ); } } ); // middle-click / new tab
		}
		// Keyboard focus is judged when it ARRIVES: focus that came from a mouse click (no :focus-visible
		// at that moment) stays "mouse focus" even if Chrome later draws a ring after a keypress.
		var kbFocus = false, heldSince = 0;
		t.addEventListener( 'focusin', function ( ev ) {
			// Browsers without :focus-visible: assume keyboard (older Safari never mouse-focuses links).
			try { kbFocus = ev.target.matches( ':focus-visible' ); } catch ( e ) { kbFocus = true; }
			// Remember where keyboard focus came from (only keyboard entries), to hand it back later.
			if ( kbFocus && ev.relatedTarget && ! t.contains( ev.relatedTarget ) ) { lastFocus = ev.relatedTarget; }
		} );
		t.addEventListener( 'focusout', function ( ev ) {
			if ( ! ev.relatedTarget || ! t.contains( ev.relatedTarget ) ) { kbFocus = false; }
		} );
		// Hold the card open while a MOUSE is over it (a touch leaves a sticky :hover behind, so it
		// does not count) or while it has KEYBOARD focus — for at most a minute, as a safety net.
		var hovering = false;
		t.addEventListener( 'pointerenter', function ( ev ) { if ( ev.pointerType === 'mouse' ) { hovering = true; } } );
		t.addEventListener( 'pointerleave', function () { hovering = false; } );
		t.grsHold = function () {
			var held = hovering || ( kbFocus && t.contains( document.activeElement ) );
			if ( ! held ) { heldSince = 0; return false; }
			if ( ! heldSince ) { heldSince = Date.now(); }
			return Date.now() - heldSince < 60000;
		};

		t.querySelector( '.grs-x' ).addEventListener( 'click', function ( ev ) {
			ev.preventDefault(); ev.stopPropagation();
			if ( ! PREVIEW ) { try { localStorage.setItem( 'grs_dismissed', String( Date.now() ) ); } catch ( e ) {} }
			track( 'grs_dismiss', o, seq );
			releaseFocus( t, kbFocus );
			hide( t ); stop = true; finish();
		} );
		t.grsKb = function () { return kbFocus; };

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
		return true;
	}

	// Before a card is hidden (dismiss, auto-hide, scroll release): never leave focus inside a card that
	// is about to be aria-hidden and removed. Keyboard focus goes back where it came from, without
	// scrolling the page; otherwise it is simply released.
	function releaseFocus( t, kb ) {
		var ae = document.activeElement;
		if ( ! ae || ! t.contains( ae ) ) { return; }
		if ( kb && lastFocus && lastFocus !== document.body && document.contains( lastFocus ) && lastFocus.focus ) {
			try { lastFocus.focus( { preventScroll: true } ); } catch ( e ) {}
		}
		if ( t.contains( document.activeElement ) ) { document.activeElement.blur(); }
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

	// Auto-hide after `ms` — but never while a mouse is over the card or it holds keyboard focus;
	// check again shortly instead. Then wait the gap and move on to the next toast.
	function hideLater( t, ms ) {
		setTimeout( function () {
			if ( t.grsGone || ! t.parentNode || ! t.classList.contains( 'grs-show' ) && t.getAttribute( 'aria-hidden' ) === 'true' ) { return; } // dismissed, or let go early after a scroll
			if ( t.grsHold && t.grsHold() ) { hideLater( t, 1500 ); return; }
			releaseFocus( t, t.grsKb && t.grsKb() );
			hide( t );
			setTimeout( function () { if ( ! stop ) { step(); } }, GAP );
		}, ms );
	}

	// Narrow viewports: bail before anything is created or fetched. The CSS carries the same
	// breakpoint, so a resize or rotation after load is covered too.
	function hiddenOnMobile() {
		if ( ! flag( CFG.hideMobile ) || ! window.matchMedia ) { return false; }
		return window.matchMedia( '(max-width:' + BP + 'px)' ).matches;
	}

	function step() {
		if ( stop || ( CAP > 0 && shown >= CAP ) ) { finish(); return; }
		if ( qi >= queue.length ) { if ( PREVIEW ) { qi = 0; } else { finish(); return; } }
		if ( ! render( queue[ qi ] ) ) {
			// Every corner would cover a control right now. Try again shortly — visitors scroll — without
			// using up this toast or the session cap.
			if ( ++retries <= RETRIES ) { setTimeout( step, RETRY ); } else { finish(); }
			return;
		}
		retries = 0;
		qi++;
		hideLater( root.querySelector( '.grs-t' ), VISIBLE );
	}

	// Sit clear of any known floating widget (chat / cart / back-to-top) sharing the toast's corner.
	var AVOID = '#launcher,.zEWidget-launcher,#intercom-container,.intercom-lightweight-app,#drift-widget,.drift-frame-controller,.crisp-client,#tawkchat-container,.tawk-min-container,#chat-widget-container,.fb_dialog,#fkcart-floating-toggler,[class*="cart-float"],[id*="cart-float"],[class*="back-to-top"],[id*="back-to-top"],[class*="scroll-to-top"]';
	function place() {
		if ( ! root ) { return; }
		var mobile = window.matchMedia( '(max-width:' + BP + 'px)' ).matches;
		var lift = mobile ? 12 : 24;
		root.style.bottom = lift + 'px';
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
		scheme();
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
		root.className = SIDE + ( CFG.style === 'photo' ? ' grs-has-photo' : '' ) + ( AUTO ? ' grs-auto' : '' );
		root.setAttribute( 'aria-live', 'polite' );
		document.body.appendChild( root );
		window.addEventListener( 'resize', place );
		window.addEventListener( 'scroll', onScroll, { passive: true } );
		// Re-match the page when the site switches its own light/dark theme.
		if ( AUTO && window.MutationObserver ) {
			mo = new MutationObserver( schemeSoon );
			mo.observe( document.documentElement, { attributes: true, attributeFilter: [ 'class', 'data-theme', 'style' ] } );
			mo.observe( document.body, { attributes: true, attributeFilter: [ 'class', 'data-theme', 'style' ] } );
		}

		fetch( CFG.endpoint, { credentials: 'omit' } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( data ) {
				if ( ! data || ! data.length ) { finish(); return; }
				queue = shuffle( data.slice() );
				setTimeout( step, DELAY );
			} )
			.catch( function () { finish(); } );
	}

	if ( document.readyState !== 'loading' ) { init(); } else { document.addEventListener( 'DOMContentLoaded', init ); }
} )();
