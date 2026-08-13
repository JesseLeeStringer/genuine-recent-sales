<?php
/**
 * Read the variation attributes a customer actually chose, straight off the order item.
 * This is the generic replacement for the store-specific option plugins.
 *
 * @package GenuineRecentSales
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GRS_Variants {

	/**
	 * Up to $max chosen attributes for an order line item.
	 *
	 * @param WC_Order_Item_Product $item   Order item.
	 * @param int                   $max    Max attributes.
	 * @param array                 $chosen Specific attribute taxonomies to prefer (empty = auto).
	 * @return array<int,array{v:string,c:string}> List of {v: value, c: swatch hex or ''}.
	 */
	public static function extract( $item, $max, $chosen = array() ) {
		if ( $max <= 0 || ! is_object( $item ) || ! method_exists( $item, 'get_formatted_meta_data' ) ) {
			return array();
		}
		$rows = array();
		foreach ( $item->get_formatted_meta_data( '_', false ) as $m ) {
			$key   = isset( $m->key ) ? (string) $m->key : '';
			$label = isset( $m->display_key ) ? trim( wp_strip_all_tags( $m->display_key ) ) : '';
			$value = isset( $m->display_value ) ? trim( wp_strip_all_tags( $m->display_value ) ) : '';
			if ( '' === $value || '' === $label ) {
				continue;
			}
			$rows[] = array( 'key' => $key, 'label' => $label, 'value' => $value );
		}

		// If the owner picked specific attributes, keep only those, in their order.
		if ( ! empty( $chosen ) ) {
			$ordered = array();
			foreach ( $chosen as $ck ) {
				$want = sanitize_title( str_replace( 'pa_', '', $ck ) );
				foreach ( $rows as $r ) {
					if ( $r['key'] === $ck || sanitize_title( $r['label'] ) === $want ) {
						$ordered[] = $r;
						break;
					}
				}
			}
			$rows = $ordered;
		}

		$rows = array_slice( $rows, 0, $max );

		$out = array();
		foreach ( $rows as $r ) {
			$out[] = array(
				'v' => $r['value'],
				'c' => self::swatch( $r['label'] . ' ' . $r['key'], $r['value'] ),
			);
		}
		return $out;
	}

	/**
	 * A swatch hex for a colour-like attribute value, else '' (no dot).
	 * Deliberately conservative: only fires when the attribute reads as a colour.
	 *
	 * @param string $context label + key, to decide if this is a colour attribute.
	 * @param string $value   The chosen value.
	 * @return string
	 */
	public static function swatch( $context, $value ) {
		if ( ! preg_match( '/colou?r|finish|shade/i', $context ) ) {
			return '';
		}
		$k = strtolower( $value );
		$map = array(
			'black' => '#232323', 'jet' => '#232323', 'white' => '#efefe9', 'ivory' => '#efefe9',
			'silver' => '#c8cacc', 'grey' => '#8f8f88', 'gray' => '#8f8f88', 'gunmetal' => '#4a4d50',
			'red' => '#b02a2a', 'crimson' => '#9e2333', 'blue' => '#2f4d7e', 'navy' => '#26324d',
			'green' => '#3f6b45', 'olive' => '#5b5f3a', 'yellow' => '#d8b53f', 'gold' => '#b08a2e',
			'orange' => '#c8712c', 'bronze' => '#8f5a3c', 'copper' => '#a5673f', 'brass' => '#a3812f',
			'purple' => '#5f3f77', 'pink' => '#c77',        'brown' => '#6b4a2f', 'tan' => '#c8b48c',
			'chrome' => '#d0d3d6', 'clear' => '#e9e9e2',
		);
		foreach ( $map as $name => $hex ) {
			if ( false !== strpos( $k, $name ) ) {
				return $hex;
			}
		}
		return '';
	}
}
