<?php
/**
 * Plugin Name:       Genuine — Recent Sales for WooCommerce
 * Plugin URI:        https://github.com/JesseLeeStringer/genuine-recent-sales
 * Description:       Tasteful "recently purchased" notifications built from REAL WooCommerce orders — no fake data, ever. Three styles, full theming, privacy-safe, and a Verified badge you can only keep by playing it straight.
 * Version:           0.4.1
 * Requires at least: 6.2
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            Jesse Lee Stringer
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       genuine-recent-sales
 *
 * @package GenuineRecentSales
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GRS_VERSION', '0.4.1' );
define( 'GRS_FILE', __FILE__ );
define( 'GRS_DIR', plugin_dir_path( __FILE__ ) );
define( 'GRS_URL', plugin_dir_url( __FILE__ ) );
define( 'GRS_OPT', 'grs_settings' );
define( 'GRS_FEED_TRANSIENT', 'grs_feed_cache' );

/**
 * Default settings. These defaults ARE the "genuine" configuration — honest cadence,
 * real orders, a real frequency cap. grs_is_verified() checks that the live settings
 * still sit inside these honest bounds before the Verified badge is allowed.
 *
 * @return array
 */
function grs_default_settings() {
	return array(
		'enabled'        => 0,                 // Off until the store owner configures it.

		// ── Appearance ──────────────────────────────────────────────
		'appearance'     => 'preset',          // inherit | preset | custom
		'preset'         => 'cream',           // black | white | cream
		'color_bg'       => '#FBF8F0',
		'color_text'     => '#46413A',
		'color_title'    => '#211E18',
		'color_accent'   => '#7E9469',
		'font'           => 'inherit',         // inherit | system
		'style'          => 'text',            // text | swatch | photo
		'position'       => 'bottom-right',    // bottom-left | bottom-right
		'rounded'        => 9,                  // corner radius px

		// ── Where it shows ──────────────────────────────────────────
		'pages'          => array( 'home', 'shop', 'category', 'product' ),
		'hide_mobile'    => 1,                 // suppress the toast on narrow viewports
		'mobile_bp'      => 600,               // "mobile" = viewport <= N px. Enforced CLIENT-SIDE
		                                       // (CSS media query + JS width bail), never via
		                                       // wp_is_mobile(): page HTML is shared by full-page
		                                       // caches/CDNs, so a server-side device gate gets
		                                       // baked into a cached page and served to the wrong
		                                       // device class.

		// ── Content ─────────────────────────────────────────────────
		'variants_max'   => 2,                 // show up to N variation attributes
		'variant_attrs'  => array(),           // chosen attribute taxonomies; empty = auto first N
		'show_swatch'    => 1,                  // colour dot when a variant is a colour
		'fallback'       => 'none',            // simple products: none | price | category
		'verified_badge' => 0,                  // show the "Verified purchase" tick (needs Verified mode)

		// ── Privacy (what customer data appears) ────────────────────
		'show_first'     => 'name',            // name | initial (e.g. "Chris" vs "C.")
		'show_town'      => 1,
		'show_state'     => 1,

		// ── Cadence (honest defaults) ───────────────────────────────
		'delay'          => 12,                // seconds before first toast
		'visible'        => 6,                 // seconds each toast is shown
		'interval'       => 35,                // seconds between toasts
		'cap'            => 4,                 // max toasts per session
		'dismiss_days'   => 7,                 // days quiet after a dismiss

		// ── Data source ─────────────────────────────────────────────
		'window_days'    => 14,                // only orders from the last N days
		'statuses'       => array( 'completed', 'processing' ),

		// ── Analytics ───────────────────────────────────────────────
		'ga4_events'     => 1,                 // send grs_view / grs_click / grs_dismiss to the site's own
		                                       // GA4 via gtag (product name only, never customer data).
		                                       // No gtag or no GA4 config on the page = nothing is sent.
	);
}

/**
 * Merged live settings.
 *
 * @return array
 */
function grs_get_settings() {
	$s = get_option( GRS_OPT, array() );
	return wp_parse_args( is_array( $s ) ? $s : array(), grs_default_settings() );
}

/**
 * Is the current configuration still "genuine"? The Verified badge is only honoured
 * while every integrity-affecting setting stays inside honest bounds. Cosmetic settings
 * (colour, style, position, pages) are intentionally NOT checked here.
 *
 * @param array|null $s Settings (defaults to live).
 * @return bool
 */
function grs_is_verified( $s = null ) {
	$s = $s ? $s : grs_get_settings();
	$paid_only = ! array_diff( (array) $s['statuses'], array( 'completed', 'processing' ) );
	return (
		(int) $s['window_days'] >= 1 && (int) $s['window_days'] <= 30 && // not passing off stale orders as "recent"
		(int) $s['delay'] >= 5 &&                                        // not slamming visitors on arrival
		(int) $s['interval'] >= 20 &&                                    // not spamming
		(int) $s['cap'] >= 1 && (int) $s['cap'] <= 8 &&                  // a real per-session cap exists
		$paid_only                                                       // real, paid orders only
	);
}

/**
 * Should the Verified badge actually render? Only when the owner opted in AND the config is honest.
 *
 * @param array|null $s Settings.
 * @return bool
 */
function grs_badge_active( $s = null ) {
	$s = $s ? $s : grs_get_settings();
	return ! empty( $s['verified_badge'] ) && grs_is_verified( $s );
}

// ── GitHub auto-updates ─────────────────────────────────────────────
// Drop YahnisElsts/plugin-update-checker into lib/plugin-update-checker/ to enable
// one-click updates from the GitHub repo's releases (no WP.org needed).
$grs_puc = GRS_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';
if ( file_exists( $grs_puc ) ) {
	require_once $grs_puc;
	if ( class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
		\YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			'https://github.com/JesseLeeStringer/genuine-recent-sales/',
			GRS_FILE,
			'genuine-recent-sales'
		)->setBranch( 'main' );
	}
}

// ── Bootstrap ───────────────────────────────────────────────────────
// Load whichever component files exist (engine classes are added incrementally).
foreach ( array( 'settings', 'variants', 'feed', 'rest', 'render' ) as $component ) {
	$path = GRS_DIR . 'includes/class-grs-' . $component . '.php';
	if ( file_exists( $path ) ) {
		require_once $path;
	}
}

add_action( 'plugins_loaded', function () {
	// Fail quietly (with an admin notice) if WooCommerce is not active.
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__( 'Genuine — Recent Sales needs WooCommerce to be active.', 'genuine-recent-sales' );
			echo '</p></div>';
		} );
		return;
	}
	if ( class_exists( 'GRS_Settings' ) ) { new GRS_Settings(); }
	if ( class_exists( 'GRS_Rest' ) )     { new GRS_Rest(); }
	if ( class_exists( 'GRS_Render' ) )   { new GRS_Render(); }
} );

// After an update, rebuild the cached feed once so changes to how rows are built show straight away.
add_action( 'plugins_loaded', function () {
	if ( get_option( 'grs_version' ) !== GRS_VERSION ) {
		delete_transient( GRS_FEED_TRANSIENT );
		update_option( 'grs_version', GRS_VERSION, true ); // autoloaded: it is read on every request
	}
} );

// Clear the cached feed whenever settings are saved or a relevant order lands.
add_action( 'update_option_' . GRS_OPT, function () { delete_transient( GRS_FEED_TRANSIENT ); } );
add_action( 'woocommerce_order_status_completed', function () { delete_transient( GRS_FEED_TRANSIENT ); } );
add_action( 'woocommerce_order_status_processing', function () { delete_transient( GRS_FEED_TRANSIENT ); } );
