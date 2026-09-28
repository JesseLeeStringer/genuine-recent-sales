<?php
/**
 * Front-end: decide whether to show, enqueue the (properly cache-friendly) assets,
 * hand the script its config, and emit the theme as CSS variables.
 *
 * @package GenuineRecentSales
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GRS_Render {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ) );
	}

	public function maybe_enqueue() {
		$s = grs_get_settings();
		if ( ! $this->should_show( $s ) ) {
			return;
		}
		wp_enqueue_style( 'grs-toast', GRS_URL . 'assets/css/toast.css', array(), GRS_VERSION );
		wp_enqueue_script( 'grs-toast', GRS_URL . 'assets/js/toast.js', array(), GRS_VERSION, true );
		wp_localize_script( 'grs-toast', 'GRS_CFG', $this->config( $s ) );
		wp_add_inline_style( 'grs-toast', $this->css_vars( $s ) );
	}

	/**
	 * @param array $s Settings.
	 * @return bool
	 */
	private function should_show( $s ) {
		// Enabled for everyone, OR an admin previewing a disabled install via ?grs_preview=1.
		if ( empty( $s['enabled'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preview toggle for admins.
			if ( ! ( current_user_can( 'manage_woocommerce' ) && isset( $_GET['grs_preview'] ) ) ) {
				return false;
			}
		}
		// Never on transactional / personal pages.
		if ( ( function_exists( 'is_cart' ) && is_cart() )
			|| ( function_exists( 'is_checkout' ) && is_checkout() )
			|| ( function_exists( 'is_account_page' ) && is_account_page() ) ) {
			return false;
		}
		$p = (array) $s['pages'];
		if ( is_front_page() && in_array( 'home', $p, true ) ) {
			return true;
		}
		if ( function_exists( 'is_shop' ) && is_shop() && in_array( 'shop', $p, true ) ) {
			return true;
		}
		if ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() && in_array( 'category', $p, true ) ) {
			return true;
		}
		if ( function_exists( 'is_product' ) && is_product() && in_array( 'product', $p, true ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param array $s Settings.
	 * @return array
	 */
	private function config( $s ) {
		return array(
			'endpoint'    => esc_url_raw( rest_url( 'grs/v1/feed' ) ),
			'style'       => $s['style'],
			'position'    => $s['position'],
			'showSwatch'  => ! empty( $s['show_swatch'] ),
			'badge'       => grs_badge_active( $s ),
			'badgeLabel'  => __( 'Verified purchase', 'genuine-recent-sales' ),
			'kicker'      => __( 'Recently purchased', 'genuine-recent-sales' ),
			'dismissAria' => __( 'Dismiss notifications', 'genuine-recent-sales' ),
			'delay'       => (int) $s['delay'] * 1000,
			'visible'     => (int) $s['visible'] * 1000,
			'interval'    => (int) $s['interval'] * 1000,
			'cap'         => (int) $s['cap'],
			'dismissDays' => (int) $s['dismiss_days'],
			// Flags are booleans: wp_localize_script() casts every scalar to a string, so 0 would
			// arrive as the truthy "0"; false arrives as "" and true as "1". toast.js reads them
			// with flag(), which accepts true / 1 / "1" only.
			'preview'     => empty( $s['enabled'] ),
			'hideMobile'  => ! empty( $s['hide_mobile'] ),
			'mobileBp'    => (int) $s['mobile_bp'],
			'ga4'         => ! empty( $s['ga4_events'] ),
		);
	}

	/**
	 * Resolve the appearance into CSS custom properties on the toast container.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	private function css_vars( $s ) {
		$presets = array(
			//        bg          text        title       accent      line
			'cream' => array( '#FBF8F0', '#46413A', '#211E18', '#7E9469', 'rgba(120,110,90,.28)' ),
			'white' => array( '#FFFFFF', '#55565A', '#1A1A1E', '#2C6B2F', 'rgba(120,120,130,.22)' ),
			'black' => array( '#1E1F22', '#C7C8CC', '#FFFFFF', '#9CB88C', 'rgba(200,200,210,.18)' ),
		);

		if ( 'custom' === $s['appearance'] ) {
			$bg = $s['color_bg']; $text = $s['color_text']; $title = $s['color_title']; $accent = $s['color_accent']; $line = 'rgba(128,128,128,.28)';
		} elseif ( 'inherit' === $s['appearance'] ) {
			$bg = 'Canvas'; $text = 'CanvasText'; $title = 'CanvasText'; $accent = $s['color_accent']; $line = 'rgba(128,128,128,.32)';
		} else {
			$p = isset( $presets[ $s['preset'] ] ) ? $presets[ $s['preset'] ] : $presets['cream'];
			list( $bg, $text, $title, $accent, $line ) = $p;
		}

		$font = ( 'system' === $s['font'] )
			? "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif"
			: 'inherit';

		$css = sprintf(
			'#grs-sp{--grs-bg:%s;--grs-text:%s;--grs-title:%s;--grs-accent:%s;--grs-line:%s;--grs-font:%s;--grs-radius:%dpx}',
			$bg, $text, $title, $accent, $line, $font, (int) $s['rounded']
		);

		// Mobile suppression is a media query, not wp_is_mobile(): the markup is identical for every
		// visitor, so it survives a full-page cache / CDN and still resolves per-device in the browser.
		// display:none also removes the node from hit-testing, so it cannot swallow taps.
		if ( ! empty( $s['hide_mobile'] ) ) {
			$css .= sprintf(
				'@media (max-width:%dpx){#grs-sp{display:none!important}}',
				(int) $s['mobile_bp']
			);
		}

		return $css;
	}
}
