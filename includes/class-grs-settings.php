<?php
/**
 * Admin settings page for Genuine — Recent Sales.
 *
 * @package GenuineRecentSales
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GRS_Settings {

	const PAGE = 'grs-settings';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_grs_reset', array( $this, 'handle_reset' ) );
	}

	public function menu() {
		$parent = class_exists( 'WooCommerce' ) ? 'woocommerce' : 'options-general.php';
		add_submenu_page(
			$parent,
			__( 'Recent Sales', 'genuine-recent-sales' ),
			__( 'Recent Sales', 'genuine-recent-sales' ),
			'manage_woocommerce',
			self::PAGE,
			array( $this, 'render' )
		);
	}

	public function register() {
		register_setting(
			'grs_group',
			GRS_OPT,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => grs_default_settings(),
			)
		);
	}

	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE ) ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_script( 'grs-admin', GRS_URL . 'assets/admin/settings.js', array( 'jquery', 'wp-color-picker' ), GRS_VERSION, true );
		wp_enqueue_style( 'grs-admin', GRS_URL . 'assets/admin/settings.css', array(), GRS_VERSION );
	}

	/**
	 * Sanitize every field by type and clamp the numeric ranges.
	 *
	 * @param array $in Raw input.
	 * @return array
	 */
	public function sanitize( $in ) {
		$d   = grs_default_settings();
		$in  = is_array( $in ) ? $in : array();
		$out = array();

		$out['enabled']        = empty( $in['enabled'] ) ? 0 : 1;

		$out['appearance']     = in_array( $in['appearance'] ?? '', array( 'inherit', 'preset', 'custom' ), true ) ? $in['appearance'] : $d['appearance'];
		$out['preset']         = in_array( $in['preset'] ?? '', array( 'black', 'white', 'cream' ), true ) ? $in['preset'] : $d['preset'];
		foreach ( array( 'color_bg', 'color_text', 'color_title', 'color_accent' ) as $c ) {
			$hex        = sanitize_hex_color( $in[ $c ] ?? '' );
			$out[ $c ]  = $hex ? $hex : $d[ $c ];
		}
		$out['font']           = in_array( $in['font'] ?? '', array( 'inherit', 'system' ), true ) ? $in['font'] : $d['font'];
		$out['style']          = in_array( $in['style'] ?? '', array( 'text', 'swatch', 'photo' ), true ) ? $in['style'] : $d['style'];
		$out['position']       = in_array( $in['position'] ?? '', array( 'bottom-left', 'bottom-right' ), true ) ? $in['position'] : $d['position'];
		$out['rounded']        = max( 0, min( 24, (int) ( $in['rounded'] ?? $d['rounded'] ) ) );

		$allowed_pages         = array( 'home', 'shop', 'category', 'product' );
		$out['pages']          = array_values( array_intersect( $allowed_pages, (array) ( $in['pages'] ?? array() ) ) );
		$out['hide_mobile']    = empty( $in['hide_mobile'] ) ? 0 : 1;
		$out['mobile_bp']      = max( 320, min( 1200, (int) ( $in['mobile_bp'] ?? $d['mobile_bp'] ) ) );

		$out['variants_max']   = max( 0, min( 3, (int) ( $in['variants_max'] ?? $d['variants_max'] ) ) );
		$out['variant_attrs']  = array_map( 'sanitize_key', (array) ( $in['variant_attrs'] ?? array() ) );
		$out['show_swatch']    = empty( $in['show_swatch'] ) ? 0 : 1;
		$out['fallback']       = in_array( $in['fallback'] ?? '', array( 'none', 'price', 'category' ), true ) ? $in['fallback'] : $d['fallback'];
		$out['verified_badge'] = empty( $in['verified_badge'] ) ? 0 : 1;

		$out['show_first']     = in_array( $in['show_first'] ?? '', array( 'name', 'initial' ), true ) ? $in['show_first'] : $d['show_first'];
		$out['show_town']      = empty( $in['show_town'] ) ? 0 : 1;
		$out['show_state']     = empty( $in['show_state'] ) ? 0 : 1;

		$out['delay']          = max( 0, min( 120, (int) ( $in['delay'] ?? $d['delay'] ) ) );
		$out['visible']        = max( 2, min( 30, (int) ( $in['visible'] ?? $d['visible'] ) ) );
		$out['interval']       = max( 3, min( 300, (int) ( $in['interval'] ?? $d['interval'] ) ) );
		$out['cap']            = max( 0, min( 50, (int) ( $in['cap'] ?? $d['cap'] ) ) );
		$out['dismiss_days']   = max( 0, min( 90, (int) ( $in['dismiss_days'] ?? $d['dismiss_days'] ) ) );

		$out['window_days']    = max( 1, min( 365, (int) ( $in['window_days'] ?? $d['window_days'] ) ) );
		$valid_status          = array( 'completed', 'processing', 'on-hold' );
		$out['statuses']       = array_values( array_intersect( $valid_status, (array) ( $in['statuses'] ?? array() ) ) );
		if ( empty( $out['statuses'] ) ) {
			$out['statuses'] = $d['statuses'];
		}

		$out['ga4_events']     = empty( $in['ga4_events'] ) ? 0 : 1;

		// The badge can never be "on" while the config is dishonest.
		if ( $out['verified_badge'] && ! grs_is_verified( $out ) ) {
			$out['verified_badge'] = 0;
			add_settings_error( GRS_OPT, 'grs_badge_off', __( 'The Verified badge was switched off because your cadence or data-window settings fall outside genuine limits.', 'genuine-recent-sales' ), 'warning' );
		}
		return $out;
	}

	public function handle_reset() {
		check_admin_referer( 'grs_reset' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( '-1' );
		}
		update_option( GRS_OPT, grs_default_settings() );
		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE, 'grs_reset' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	// ── Render ──────────────────────────────────────────────────────

	public function render() {
		$s        = grs_get_settings();
		$verified = grs_is_verified( $s );
		?>
		<div class="wrap grs-wrap">
			<h1><?php esc_html_e( 'Genuine — Recent Sales', 'genuine-recent-sales' ); ?></h1>
			<p class="grs-tagline"><?php esc_html_e( 'Recently-purchased notifications built from your real WooCommerce orders. No fake data, ever.', 'genuine-recent-sales' ); ?></p>

			<?php settings_errors(); ?>

			<?php if ( isset( $_GET['grs_reset'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings reset to genuine defaults.', 'genuine-recent-sales' ); ?></p></div>
			<?php endif; ?>

			<div class="grs-trust <?php echo $verified ? 'is-verified' : 'is-custom'; ?>">
				<strong><?php echo $verified ? esc_html__( '✓ Genuine mode', 'genuine-recent-sales' ) : esc_html__( '● Custom mode', 'genuine-recent-sales' ); ?></strong>
				<span>
					<?php
					echo $verified
						? esc_html__( 'Honest cadence and real, recent, paid orders — the Verified badge is available.', 'genuine-recent-sales' )
						: esc_html__( 'Your cadence or data window is outside genuine limits, so the Verified badge is unavailable.', 'genuine-recent-sales' );
					?>
				</span>
				<a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=grs_reset' ), 'grs_reset' ) ); ?>"><?php esc_html_e( 'Reset to genuine defaults', 'genuine-recent-sales' ); ?></a>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( 'grs_group' ); ?>
				<?php $n = GRS_OPT; ?>

				<h2 class="title"><?php esc_html_e( 'General', 'genuine-recent-sales' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->row_checkbox( __( 'Enable notifications', 'genuine-recent-sales' ), "{$n}[enabled]", $s['enabled'], __( 'Show the toast to visitors. Leave off while you set things up.', 'genuine-recent-sales' ) );
					$this->row_pages( $s );
					$this->row_checkbox( __( 'Hide on mobile', 'genuine-recent-sales' ), "{$n}[hide_mobile]", $s['hide_mobile'], __( 'Do not show the toast on narrow screens. Recommended: a bottom-pinned card spans the full width on a phone and sits over footer links and sticky bars.', 'genuine-recent-sales' ) );
					$this->row_number( __( 'Mobile breakpoint (px)', 'genuine-recent-sales' ), "{$n}[mobile_bp]", $s['mobile_bp'], 320, 1200, __( 'Viewports this wide or narrower count as mobile. Applied in the browser, so it stays correct behind a full-page cache or CDN.', 'genuine-recent-sales' ) );
					$this->row_select( __( 'Position', 'genuine-recent-sales' ), "{$n}[position]", $s['position'], array( 'bottom-right' => __( 'Bottom right', 'genuine-recent-sales' ), 'bottom-left' => __( 'Bottom left', 'genuine-recent-sales' ) ) );
					$this->row_select( __( 'Style', 'genuine-recent-sales' ), "{$n}[style]", $s['style'], array( 'text' => __( 'Text only', 'genuine-recent-sales' ), 'swatch' => __( 'Text + colour swatch', 'genuine-recent-sales' ), 'photo' => __( 'Text + product photo', 'genuine-recent-sales' ) ) );
					?>
				</table>

				<h2 class="title"><?php esc_html_e( 'Appearance', 'genuine-recent-sales' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->row_select(
						__( 'Colour mode', 'genuine-recent-sales' ),
						"{$n}[appearance]",
						$s['appearance'],
						array(
							'preset'  => __( 'Preset (Black / White / Cream)', 'genuine-recent-sales' ),
							'custom'  => __( 'Custom colours', 'genuine-recent-sales' ),
							'inherit' => __( 'Inherit theme (experimental)', 'genuine-recent-sales' ),
						),
						__( 'Inherit reliably picks up your theme font, but not its colours — Preset or Custom give the dependable result.', 'genuine-recent-sales' ),
						'grs-appearance-row'
					);
					$this->row_select( __( 'Preset', 'genuine-recent-sales' ), "{$n}[preset]", $s['preset'], array( 'cream' => __( 'Cream', 'genuine-recent-sales' ), 'white' => __( 'White', 'genuine-recent-sales' ), 'black' => __( 'Black', 'genuine-recent-sales' ) ), '', 'grs-when-preset' );
					$this->row_color( __( 'Background colour', 'genuine-recent-sales' ), "{$n}[color_bg]", $s['color_bg'], 'grs-when-custom' );
					$this->row_color( __( 'Text colour', 'genuine-recent-sales' ), "{$n}[color_text]", $s['color_text'], 'grs-when-custom' );
					$this->row_color( __( 'Title colour', 'genuine-recent-sales' ), "{$n}[color_title]", $s['color_title'], 'grs-when-custom' );
					$this->row_color( __( 'Accent colour', 'genuine-recent-sales' ), "{$n}[color_accent]", $s['color_accent'], 'grs-when-custom' );
					$this->row_select( __( 'Font', 'genuine-recent-sales' ), "{$n}[font]", $s['font'], array( 'inherit' => __( 'Inherit theme font', 'genuine-recent-sales' ), 'system' => __( 'Clean system font', 'genuine-recent-sales' ) ) );
					$this->row_number( __( 'Corner radius (px)', 'genuine-recent-sales' ), "{$n}[rounded]", $s['rounded'], 0, 24 );
					?>
				</table>

				<h2 class="title"><?php esc_html_e( 'What it shows', 'genuine-recent-sales' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->row_number( __( 'Variant attributes to show', 'genuine-recent-sales' ), "{$n}[variants_max]", $s['variants_max'], 0, 3, __( 'e.g. Size and Colour. Max 3.', 'genuine-recent-sales' ) );
					$this->row_attrs( $s );
					$this->row_checkbox( __( 'Colour swatch dot', 'genuine-recent-sales' ), "{$n}[show_swatch]", $s['show_swatch'], __( 'Show a small colour dot when a variant is a colour.', 'genuine-recent-sales' ) );
					$this->row_select( __( 'Simple products (no variants)', 'genuine-recent-sales' ), "{$n}[fallback]", $s['fallback'], array( 'none' => __( 'Show nothing extra', 'genuine-recent-sales' ), 'price' => __( 'Show the price', 'genuine-recent-sales' ), 'category' => __( 'Show the category', 'genuine-recent-sales' ) ), __( 'SKU is intentionally not offered — it means nothing to a shopper.', 'genuine-recent-sales' ) );
					?>
				</table>

				<h2 class="title"><?php esc_html_e( 'Privacy', 'genuine-recent-sales' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->row_select( __( 'Customer name', 'genuine-recent-sales' ), "{$n}[show_first]", $s['show_first'], array( 'name' => __( 'First name (Chris)', 'genuine-recent-sales' ), 'initial' => __( 'Initial only (C.)', 'genuine-recent-sales' ) ), __( 'Surname, email, address and order value are never shown or exposed.', 'genuine-recent-sales' ) );
					$this->row_checkbox( __( 'Show town', 'genuine-recent-sales' ), "{$n}[show_town]", $s['show_town'], '' );
					$this->row_checkbox( __( 'Show state / region', 'genuine-recent-sales' ), "{$n}[show_state]", $s['show_state'], '' );
					?>
				</table>

				<h2 class="title"><?php esc_html_e( 'Cadence & Trust', 'genuine-recent-sales' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->row_number( __( 'First toast after (seconds)', 'genuine-recent-sales' ), "{$n}[delay]", $s['delay'], 0, 120 );
					$this->row_number( __( 'Each toast visible (seconds)', 'genuine-recent-sales' ), "{$n}[visible]", $s['visible'], 2, 30 );
					$this->row_number( __( 'Gap between toasts (seconds)', 'genuine-recent-sales' ), "{$n}[interval]", $s['interval'], 3, 300 );
					$this->row_number( __( 'Max per session', 'genuine-recent-sales' ), "{$n}[cap]", $s['cap'], 0, 50, __( '0 = unlimited (this drops Genuine mode).', 'genuine-recent-sales' ) );
					$this->row_number( __( 'Quiet after dismiss (days)', 'genuine-recent-sales' ), "{$n}[dismiss_days]", $s['dismiss_days'], 0, 90 );
					$this->row_number( __( 'Only orders from the last (days)', 'genuine-recent-sales' ), "{$n}[window_days]", $s['window_days'], 1, 365, __( 'Over 30 days stops counting as "recent" and drops Genuine mode.', 'genuine-recent-sales' ) );
					$this->row_statuses( $s );
					$this->row_verified_badge( $s, $verified );
					?>
				</table>

				<h2 class="title"><?php esc_html_e( 'Analytics', 'genuine-recent-sales' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->row_checkbox( __( 'Google Analytics 4 events', 'genuine-recent-sales' ), "{$n}[ga4_events]", $s['ga4_events'], __( 'Send grs_view, grs_click and grs_dismiss events to the GA4 property already on your site (a gtag.js G- tag, or a Google tag such as Site Kit adds). Carries the product name only — never customer details. Does nothing if the page has no GA4 tag; Tag Manager-only setups are not supported.', 'genuine-recent-sales' ) );
					?>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	// ── Field helpers ───────────────────────────────────────────────

	private function row( $label, $html, $rowclass = '' ) {
		printf( '<tr%s><th scope="row">%s</th><td>%s</td></tr>', $rowclass ? ' class="' . esc_attr( $rowclass ) . '"' : '', esc_html( $label ), $html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $html built from escaped helpers below.
	}

	private function desc( $text ) {
		return $text ? '<p class="description">' . esc_html( $text ) . '</p>' : '';
	}

	private function row_checkbox( $label, $name, $val, $desc ) {
		$html = sprintf( '<label><input type="checkbox" name="%s" value="1" %s> %s</label>', esc_attr( $name ), checked( 1, $val, false ), esc_html( $desc ) );
		$this->row( $label, $html );
	}

	private function row_number( $label, $name, $val, $min, $max, $desc = '' ) {
		$html = sprintf( '<input type="number" class="small-text" name="%s" value="%s" min="%d" max="%d">', esc_attr( $name ), esc_attr( $val ), (int) $min, (int) $max ) . $this->desc( $desc );
		$this->row( $label, $html );
	}

	private function row_select( $label, $name, $val, $opts, $desc = '', $rowclass = '' ) {
		$html = '<select name="' . esc_attr( $name ) . '">';
		foreach ( $opts as $k => $lbl ) {
			$html .= sprintf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( $val, $k, false ), esc_html( $lbl ) );
		}
		$html .= '</select>' . $this->desc( $desc );
		$this->row( $label, $html, $rowclass );
	}

	private function row_color( $label, $name, $val, $rowclass ) {
		$html = sprintf( '<input type="text" class="grs-color" name="%s" value="%s" data-default-color="%s">', esc_attr( $name ), esc_attr( $val ), esc_attr( $val ) );
		$this->row( $label, $html, $rowclass );
	}

	private function row_pages( $s ) {
		$pages = array(
			'home'     => __( 'Homepage', 'genuine-recent-sales' ),
			'shop'     => __( 'Shop', 'genuine-recent-sales' ),
			'category' => __( 'Category / archive pages', 'genuine-recent-sales' ),
			'product'  => __( 'Product pages', 'genuine-recent-sales' ),
		);
		$html = '<fieldset>';
		foreach ( $pages as $k => $lbl ) {
			$html .= sprintf( '<label style="display:block;margin:.2em 0"><input type="checkbox" name="%s[pages][]" value="%s" %s> %s</label>', esc_attr( GRS_OPT ), esc_attr( $k ), checked( in_array( $k, (array) $s['pages'], true ), true, false ), esc_html( $lbl ) );
		}
		$html .= '</fieldset>' . $this->desc( __( 'Cart, checkout, account and blog/policy pages are always excluded.', 'genuine-recent-sales' ) );
		$this->row( __( 'Show on', 'genuine-recent-sales' ), $html );
	}

	private function row_statuses( $s ) {
		$opts = array(
			'completed'  => __( 'Completed', 'genuine-recent-sales' ),
			'processing' => __( 'Processing', 'genuine-recent-sales' ),
			'on-hold'    => __( 'On hold (unpaid — drops Genuine mode)', 'genuine-recent-sales' ),
		);
		$html = '<fieldset>';
		foreach ( $opts as $k => $lbl ) {
			$html .= sprintf( '<label style="display:block;margin:.2em 0"><input type="checkbox" name="%s[statuses][]" value="%s" %s> %s</label>', esc_attr( GRS_OPT ), esc_attr( $k ), checked( in_array( $k, (array) $s['statuses'], true ), true, false ), esc_html( $lbl ) );
		}
		$html .= '</fieldset>';
		$this->row( __( 'Order statuses', 'genuine-recent-sales' ), $html );
	}

	private function row_attrs( $s ) {
		$taxes = function_exists( 'wc_get_attribute_taxonomies' ) ? wc_get_attribute_taxonomies() : array();
		if ( empty( $taxes ) ) {
			$this->row( __( 'Which attributes', 'genuine-recent-sales' ), $this->desc( __( 'No global attributes found — the first variants on each order will be shown automatically.', 'genuine-recent-sales' ) ) );
			return;
		}
		$html = '<fieldset>';
		foreach ( $taxes as $t ) {
			$key   = 'pa_' . $t->attribute_name;
			$html .= sprintf( '<label style="display:inline-block;margin:.2em 1em .2em 0"><input type="checkbox" name="%s[variant_attrs][]" value="%s" %s> %s</label>', esc_attr( GRS_OPT ), esc_attr( $key ), checked( in_array( $key, (array) $s['variant_attrs'], true ), true, false ), esc_html( $t->attribute_label ) );
		}
		$html .= '</fieldset>' . $this->desc( __( 'Leave all unticked to auto-show the first variants on each order.', 'genuine-recent-sales' ) );
		$this->row( __( 'Which attributes', 'genuine-recent-sales' ), $html );
	}

	private function row_verified_badge( $s, $verified ) {
		$disabled = $verified ? '' : ' disabled';
		$html     = sprintf(
			'<label><input type="checkbox" name="%s[verified_badge]" value="1" %s%s> %s</label>%s',
			esc_attr( GRS_OPT ),
			checked( 1, $s['verified_badge'], false ),
			$disabled,
			esc_html__( 'Show a small "Verified purchase" tick on each toast', 'genuine-recent-sales' ),
			$this->desc( $verified ? __( 'Available because your configuration is genuine.', 'genuine-recent-sales' ) : __( 'Unavailable — bring cadence and data window back within genuine limits (or reset) to use it.', 'genuine-recent-sales' ) )
		);
		$this->row( __( 'Verified badge', 'genuine-recent-sales' ), $html );
	}
}
