<?php
/**
 * Pinterest For WooCommerce Catalog Syncing
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class adding Save Pin support.
 */
class ProductsXmlFeed {

	/**
	 * The default data structure of the Item to be printed in the XML feed.
	 *
	 * @var array
	 */
	private static $feed_item_structure = array(
		'g:id',
		'item_group_id',
		'title',
		'description',
		'g:product_type', // The categorization of your product based on your custom product taxonomy. Subcategories must be sent separated by “ > “. The > must be wrapped by spaces. We do not recognize any other delimiters such as comma or pipe.
		'g:google_product_category',
		'link',
		'g:image_link',
		'g:condition',
		'g:availability',
		'g:price', // <numeric> <ISO 4217>
		'sale_price', // <numeric> <ISO 4217>
		'g:ean',
		'g:mpn',
		'g:brand',
		'g:custom_label_0',
		'g:tax',
		'g:shipping',
		'g:identifier_exists',
		'g:additional_image_link',
	);

	/**
	 * Returns the XML header to be printed.
	 *
	 * @return string
	 */
	public static function get_xml_header() {
		return '<?xml version="1.0"?>
		<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">
			<channel>' . PHP_EOL;
	}

	/**
	 * Returns the XML footer to be printed.
	 *
	 * @return string
	 */
	public static function get_xml_footer() {
		return '    </channel>
		</rss>';
	}


	/**
	 * Returns the Item's XML for the given product.
	 *
	 * @param WC_Product $product The product to print the XML for.
	 *
	 * @return string
	 */
	public static function get_xml_item( $product ) {

		$xml = "\t\t\t\t<item>" . PHP_EOL;

		foreach ( apply_filters( 'pinterest_for_woocommerce_feed_item_structure', self::$feed_item_structure, $product ) as $attribute ) {
			$method_name = 'get_property_' . str_replace( ':', '_', $attribute );
			if ( method_exists( __CLASS__, $method_name ) ) {
				$xml .= "\t\t\t\t\t" . call_user_func_array( array( __CLASS__, $method_name ), array( $product, $attribute ) ) . PHP_EOL;
			}
		}

		$xml .= "\t\t\t\t</item>" . PHP_EOL;

		return apply_filters( 'pinterest_for_woocommerce_feed_item_xml', $xml, $product );
	}


	/**
	 * Undocumented function
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	private static function get_property_g_id( $product, $property ) {
		return '<' . $property . '>' . $product->get_id() . '</' . $property . '>';
	}

	/**
	 * Undocumented function
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	private static function get_property_title( $product, $property ) {
		return '<' . $property . '>' . $product->get_name() . '</' . $property . '>';
	}

	/**
	 * Undocumented function
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	private static function get_property_description( $product, $property ) {
		return '<' . $property . '>' . $product->get_short_description() . '</' . $property . '>';
	}

	/**
	 * Undocumented function
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	private static function get_property_g_product_type( $product, $property ) {
		return '<' . $property . '>' . 'Tools &gt; Mirrors' . '</' . $property . '>'; // TODO:  . ?
		// return $product->get_id();.
	}

	/**
	 * Undocumented function
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	private static function get_property_g_google_product_category( $product, $property ) {
		return '<' . $property . '>' . wc_get_product_category_list( $product->get_id(), '&gt;' ) . '</' . $property . '>';
	}

	/**
	 * Undocumented function
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	private static function get_property_link( $product, $property ) {
		return '<' . $property . '>' . $product->get_permalink() . '</' . $property . '>';
	}

	/**
	 * Undocumented function
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	private static function get_property_g_image_link( $product, $property ) {

		$image_id = $product->get_image_id();

		if ( ! $image_id ) {
			return '';
		}

		return '<' . $property . '>' . wp_get_attachment_image_src( $image_id )[0] . '</' . $property . '>';
	}

	/**
	 * Undocumented function
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	// private static function get_property_g_condition( $product, $property ) {
	// return '<' . $property . '>' . $product->get_id() . '</'. $property . '>';
	// }

	/**
	 * Undocumented function
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	private static function get_property_g_availability( $product, $property ) {

		switch ( $product->get_stock_status() ) {
			case 'in_stock':
				$stock_status = 'in stock';
				break;
			case 'out_of_stock':
				$stock_status = 'out of stock';
				break;
			case 'onbackorder':
				$stock_status = 'preorder';
				break;
			default:
				$stock_status = $product->get_stock_status();
				break;
		}

		// TODO: preorder vs backorder?

		return '<' . $property . '>' . ( $stock_status ) . '</' . $property . '>';
	}

	/**
	 * Undocumented function
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	private static function get_property_g_price( $product, $property ) {
		return '<' . $property . '>' . $product->get_price() . '</' . $property . '>';
	}

	/**
	 * Undocumented function
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	private static function get_property_sale_price( $product, $property ) {
		return '<' . $property . '>' . $product->get_sale_price() . '</' . $property . '>';
	}

	/**
	 * Undocumented function
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	// private static function get_property_g_ean( $product, $property ) {
	// return '<' . $property . '>' . $product->get_id() . '</'. $property . '>';
	// }

	/**
	 * Undocumented function
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	private static function get_property_g_mpn( $product, $property ) {
		return '<' . $property . '>' . $product->get_sku() . '</' . $property . '>';
	}

	/**
	 * Undocumented function
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	// private static function get_property_g_brand( $product, $property ) {
	// return '<' . $property . '>' . $product->get_id() . '</'. $property . '>';
	// }

	/**
	 * Undocumented function
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	// private static function get_property_g_custom_label_0( $product, $property ) {
	// return '<' . $property . '>' . $product->get_id() . '</'. $property . '>';
	// }

	/**
	 * Undocumented function
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	// private static function get_property_g_tax( $product, $property ) {
	// return '<' . $property . '>' . $product->get_id() . '</'. $property . '>';
	// }

	/**
	 * Undocumented function
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	// private static function get_property_g_shipping( $product, $property ) {
	// return '<' . $property . '>' . $product->get_id() . '</'. $property . '>';
	// }

	/**
	 * Undocumented function
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	private static function get_property_g_identifier_exists( $product, $property ) {
		return '<' . $property . '>FALSE</' . $property . '>'; // TODO: ?
	}

	/**
	 * Undocumented function
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	// private static function get_property_g_additional_image_link( $product, $property ) {
	// return '<' . $property . '>' . $product->get_id() . '</'. $property . '>';
	// }


}