<?php
/**
 * Build the notification feed from real WooCommerce orders. Cached in a transient;
 * rebuilt on settings save or a new completed/processing order (see the main file hooks).
 *
 * @package GenuineRecentSales
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GRS_Feed {

	const LIMIT = 40; // max notifications in the feed

	/**
	 * Cached feed (array of notification rows).
	 *
	 * @return array
	 */
	public static function get() {
		$feed = get_transient( GRS_FEED_TRANSIENT );
		if ( false === $feed ) {
			$feed = self::build();
			set_transient( GRS_FEED_TRANSIENT, $feed, 30 * MINUTE_IN_SECONDS );
		}
		return $feed;
	}

	/**
	 * @return array
	 */
	public static function build() {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}
		$s = grs_get_settings();

		$orders = wc_get_orders( array(
			'limit'        => 60,
			'status'       => array_map( function ( $st ) { return 'wc-' . $st; }, (array) $s['statuses'] ),
			'orderby'      => 'date',
			'order'        => 'DESC',
			'type'         => 'shop_order',
			'return'       => 'objects',
			'date_created' => '>' . ( time() - max( 1, (int) $s['window_days'] ) * DAY_IN_SECONDS ),
		) );
		if ( ! is_array( $orders ) ) {
			return array();
		}

		$out = array();
		foreach ( $orders as $order ) {
			if ( ! is_a( $order, 'WC_Order' ) ) {
				continue;
			}
			$who = self::customer( $order, $s );
			if ( '' === $who['first'] ) {
				continue;
			}
			$ago = self::ago( $order );

			$agg = array();
			foreach ( $order->get_items( 'line_item' ) as $item ) {
				$product = $item->get_product();
				$name    = trim( wp_strip_all_tags( $item->get_name() ) );
				if ( '' === $name ) {
					continue;
				}
				$variants = GRS_Variants::extract( $item, (int) $s['variants_max'], (array) $s['variant_attrs'] );
				$sig      = $name . '|' . wp_json_encode( wp_list_pluck( $variants, 'v' ) );

				if ( ! isset( $agg[ $sig ] ) ) {
					$agg[ $sig ] = array(
						'product'  => $name,
						'variants' => $variants,
						'qty'      => 0,
						'pid'      => $product ? $product->get_id() : (int) $item->get_product_id(),
						'product_obj' => $product,
					);
				}
				$agg[ $sig ]['qty'] += max( 1, (int) $item->get_quantity() );
			}

			foreach ( $agg as $row ) {
				$out[] = array(
					'first'    => $who['first'],
					'town'     => $who['town'],
					'state'    => $who['state'],
					'product'  => $row['product'],
					'qty'      => $row['qty'],
					'variants' => $row['variants'],
					'extra'    => self::fallback( $row, $s ),
					'url'      => $row['pid'] ? get_permalink( $row['pid'] ) : '',
					'img'      => self::image( $row, $s ),
					'ago'      => $ago,
				);
				if ( count( $out ) >= self::LIMIT ) {
					break 2;
				}
			}
		}
		return $out;
	}

	/**
	 * Privacy-safe customer fields per the settings.
	 *
	 * @param WC_Order $order Order.
	 * @param array    $s     Settings.
	 * @return array{first:string,town:string,state:string}
	 */
	private static function customer( $order, $s ) {
		$first = self::tc( trim( (string) $order->get_billing_first_name() ) );
		$first = preg_replace( '/\s+[A-Z]$/', '', $first ); // drop a trailing middle initial
		if ( 'initial' === $s['show_first'] && '' !== $first ) {
			$first = mb_substr( $first, 0, 1 ) . '.';
		}
		$state = ! empty( $s['show_state'] ) ? (string) $order->get_billing_state() : '';
		return array(
			'first' => $first,
			'town'  => ! empty( $s['show_town'] ) ? self::town( (string) $order->get_billing_city(), (string) $order->get_billing_state(), (string) $order->get_billing_country() ) : '',
			'state' => $state,
		);
	}

	/**
	 * Tidy a billing city for display: consistent word casing ("Deception bay" → "Deception Bay",
	 * "McKinnon" untouched) and no state typed into the city field ("Godwin Beach - Qld" → "Godwin
	 * Beach"), which would otherwise show the state twice next to the separate state field.
	 *
	 * @param string $town    Billing city.
	 * @param string $state   Billing state code.
	 * @param string $country Billing country code.
	 * @return string
	 */
	private static function town( $town, $state, $country ) {
		$town = trim( preg_replace( '/\s+/u', ' ', $town ) );
		if ( '' !== $state ) {
			$names = array( preg_quote( $state, '/' ) );
			if ( function_exists( 'WC' ) && WC()->countries ) {
				$states = WC()->countries->get_states( $country ? $country : WC()->countries->get_base_country() );
				if ( is_array( $states ) && ! empty( $states[ $state ] ) ) {
					$names[] = preg_quote( html_entity_decode( $states[ $state ], ENT_QUOTES, 'UTF-8' ), '/' );
				}
			}
			$stripped = preg_replace( '/[\s,\-\x{2013}\x{2014}]+(?:' . implode( '|', $names ) . ')\.?$/iu', '', $town );
			if ( is_string( $stripped ) && '' !== trim( $stripped ) ) {
				$town = trim( $stripped );
			}
		}
		$town = self::tc( $town );
		// Mixed case: capitalise the first letter of each word without lower-casing the rest.
		return ucwords( $town, " -'" );
	}

	/**
	 * Fallback second line for products with no shown variants: price or category.
	 *
	 * @param array $row Aggregated row.
	 * @param array $s   Settings.
	 * @return string
	 */
	private static function fallback( $row, $s ) {
		if ( ! empty( $row['variants'] ) || 'none' === $s['fallback'] ) {
			return '';
		}
		$product = $row['product_obj'];
		if ( ! $product ) {
			return '';
		}
		if ( 'price' === $s['fallback'] ) {
			$price = wc_get_price_to_display( $product );
			return $price ? html_entity_decode( wp_strip_all_tags( wc_price( $price ) ), ENT_QUOTES ) : '';
		}
		if ( 'category' === $s['fallback'] ) {
			$terms = get_the_terms( $product->get_id(), 'product_cat' );
			if ( is_array( $terms ) && ! empty( $terms ) ) {
				return $terms[0]->name;
			}
		}
		return '';
	}

	/**
	 * Product thumbnail URL (only needed for the photo style).
	 *
	 * @param array $row Row.
	 * @param array $s   Settings.
	 * @return string
	 */
	private static function image( $row, $s ) {
		if ( 'photo' !== $s['style'] || ! $row['product_obj'] ) {
			return '';
		}
		$id = $row['product_obj']->get_image_id();
		if ( ! $id ) {
			return '';
		}
		$src = wp_get_attachment_image_src( $id, 'woocommerce_gallery_thumbnail' );
		return $src ? $src[0] : '';
	}

	/**
	 * Bucketed relative time — never precise, never "months ago".
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	private static function ago( $order ) {
		$created = $order->get_date_created();
		if ( ! $created ) {
			return '';
		}
		$days = (int) floor( ( time() - $created->getTimestamp() ) / DAY_IN_SECONDS );
		if ( $days < 1 ) { return __( '< 24 hours ago', 'genuine-recent-sales' ); }
		if ( 1 === $days ) { return __( '1 day ago', 'genuine-recent-sales' ); }
		if ( 2 === $days ) { return __( '2 days ago', 'genuine-recent-sales' ); }
		if ( 3 === $days ) { return __( '3 days ago', 'genuine-recent-sales' ); }
		return __( '4+ days ago', 'genuine-recent-sales' );
	}

	/**
	 * Title-case an ALL-CAPS or all-lower string, leave mixed case alone.
	 *
	 * @param string $sv String.
	 * @return string
	 */
	private static function tc( $sv ) {
		if ( '' !== $sv && ( $sv === strtoupper( $sv ) || $sv === strtolower( $sv ) ) ) {
			$sv = ucwords( strtolower( $sv ) );
		}
		return $sv;
	}
}
