<?php
/**
 * Pinterest for WooCommerce Catalog Syncing
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest;

use Automattic\WooCommerce\Pinterest\Logger;
use Automattic\WooCommerce\Pinterest\Product\Attributes\AttributeManager;

use WC_Product;
use WC_Product_Variable;
use WC_Product_Variation;

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
		'g:product_type',
		'link',
		'g:image_link',
		'g:availability',
		'g:price',
		'sale_price',
		'g:mpn',
		'g:tax',
		// 'g:shipping',
		'g:additional_image_link',
	);

	/**
	 * Shipping object. Used for caching between calls to the shipping column function.
	 *
	 * @var Shipping|null $shipping
	 */
	private static $shipping = null;

	/**
	 * Limit of characters allowed by Pinterest in the product description.
	 *
	 * @var int
	 */
	const DESCRIPTION_SIZE_CHARS_LIMIT = 10000;

	/**
	 * Max image resolution (5000px*5000px).
	 *
	 * @var int
	 */
	const IMAGE_MAX_RESOLUTION = 5000 * 5000;


	/**
	 * Returns the XML header to be printed.
	 *
	 * @return string
	 */
	public static function get_xml_header() {
		return '<?xml version="1.0"?>' . PHP_EOL . '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . PHP_EOL . "\t" . '<channel>' . PHP_EOL;
	}


	/**
	 * Returns the XML footer to be printed.
	 *
	 * @return string
	 */
	public static function get_xml_footer() {
		return "\t" . '</channel>' . PHP_EOL . '</rss>';
	}


	/**
	 * Returns the Item's XML for the given product.
	 *
	 * @param WC_Product $product  The product to print the XML for.
	 *
	 * @return string XML string
	 */
	public static function get_xml_item( $product ) {

		if ( ! self::is_product_fit_for_feed( $product ) ) {
			return '';
		}

		$xml = "\t\t<item>" . PHP_EOL;

		foreach ( apply_filters( 'pinterest_for_woocommerce_feed_item_structure', self::$feed_item_structure, $product ) as $attribute ) {
			$method_name = 'get_property_' . str_replace( ':', '_', $attribute );
			if ( method_exists( __CLASS__, $method_name ) ) {
				$att  = call_user_func_array( array( __CLASS__, $method_name ), array( $product, $attribute ) );
				$xml .= ! empty( $att ) ? "\t\t\t" . $att . PHP_EOL : '';
			}
		}

		$xml .= self::get_attributes_xml( $product, "\t\t\t" );

		$xml .= "\t\t</item>" . PHP_EOL;

		return apply_filters( 'pinterest_for_woocommerce_feed_item_xml', $xml, $product );
	}


	/**
	 * Helper method to return if a product is fit for the feed profile.
	 *
	 * @param WC_Product $product The product.
	 *
	 * @return boolean
	 */
	private static function is_product_fit_for_feed( $product ) {

		// Decide if product is fit for the feed based on price.
		$price = self::get_product_regular_price( $product );

		if ( empty( $price ) || empty( floatval( $price ) ) ) {
			return false;
		}

		return true;
	}


	/**
	 * Get the XML for all the product attributes.
	 * Will only return the attributes which have been set
	 * or are available for the product type.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @param string     $indent  Line indentation string.
	 * @return string XML string.
	 */
	private static function get_attributes_xml( $product, $indent ) {
		$attribute_manager = AttributeManager::instance();
		$attributes        = $attribute_manager->get_all_values( $product );
		$xml               = '';

		// Merge with parent's attributes if it's a variation product.
		if ( $product instanceof WC_Product_Variation ) {
			$parent_product    = wc_get_product( $product->get_parent_id() );
			$parent_attributes = $attribute_manager->get_all_values( $parent_product );
			$attributes        = array_merge( $parent_attributes, $attributes );
		}

		foreach ( $attributes as $name => $value ) {
			$property = "g:{$name}";
			$value    = esc_html( $value );
			$xml     .= "{$indent}<{$property}>{$value}</{$property}>" . PHP_EOL;
		}

		return $xml;
	}

	/**
	 * Returns the Product ID.
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
	 * Returns the item_group_id (parent id for variations).
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	private static function get_property_item_group_id( $product, $property ) {

		if ( ! $product->get_parent_id() ) {
			return;
		}

		return '<' . $property . '>' . $product->get_parent_id() . '</' . $property . '>';
	}


	/**
	 * Returns the product title.
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	private static function get_property_title( $product, $property ) {
		return '<' . $property . '><![CDATA[' . wp_strip_all_tags( $product->get_name() ) . ']]></' . $property . '>';
	}

	/**
	 * Returns the product description.
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	private static function get_property_description( $product, $property ) {

		$description = $product->get_parent_id() ? $product->get_description() : $product->get_short_description();

		if ( empty( $description ) ) {
			$description = get_the_excerpt( $product->get_id() );
		}

		if ( empty( $description ) ) {
			return;
		}

		/**
		 * Filters whether the shortcodes should be applied for product descriptions when generating the feed or be stripped out.
		 *
		 * @param bool       $apply_shortcodes Shortcodes are applied if set to `true` and stripped out if set to `false`.
		 * @param WC_Product $product          WooCommerce product object.
		 */
		$apply_shortcodes = apply_filters( 'pinterest_for_woocommerce_product_description_apply_shortcodes', false, $product );
		if ( $apply_shortcodes ) {
			// Apply active shortcodes.
			$description = do_shortcode( $description );
		} else {
			// Strip out active shortcodes.
			$description = strip_shortcodes( $description );
		}

		// Strip HTML tags from description.
		$description = wp_strip_all_tags( $description );

		// Strip [&hellip] character from description.
		$description = str_replace( '[&hellip;]', '...', $description );

		// Limit the number of characters in the description to 10000.
		if ( strlen( $description ) > self::DESCRIPTION_SIZE_CHARS_LIMIT ) {
			/* translators: %s product id */
			Logger::log( sprintf( esc_html__( 'The product [%s] has a description longer than the allowed limit.', 'pinterest-for-woocommerce' ), $product->get_id() ) );
		}
		$description = substr( $description, 0, self::DESCRIPTION_SIZE_CHARS_LIMIT );

		return '<' . $property . '><![CDATA[' . $description . ']]></' . $property . '>';
	}

	/**
	 * Returns the product taxonomies.
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	private static function get_property_g_product_type( $product, $property ) {

		$id         = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		$taxonomies = self::get_taxonomies( $id );

		if ( empty( $taxonomies ) ) {
			return;
		}

		return '<' . $property . '>' . implode( ' &gt; ', $taxonomies ) . '</' . $property . '>';
	}


	/**
	 * Returns the permalink.
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	private static function get_property_link( $product, $property ) {
		return '<' . $property . '><![CDATA[' . $product->get_permalink() . ']]></' . $property . '>';
	}

	/**
	 * Returns the URL of the main product image.
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

		$image = self::get_product_image( $image_id );

		if ( ! $image ) {
			return;
		}

		return '<' . $property . '><![CDATA[' . $image[0] . ']]></' . $property . '>';
	}


	/**
	 * Returns the availability of the product.
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	private static function get_property_g_availability( $product, $property ) {

		switch ( $product->get_stock_status() ) {
			case 'instock':
				$stock_status = 'in stock';
				break;
			case 'outofstock':
				$stock_status = 'out of stock';
				break;
			case 'onbackorder':
				$stock_status = 'preorder';
				break;
			default:
				$stock_status = $product->get_stock_status();
				break;
		}

		return '<' . $property . '>' . $stock_status . '</' . $property . '>';
	}

	/**
	 * Returns the base price, or the min base price for a variable product.
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	private static function get_property_g_price( $product, $property ) {

		$price = self::get_product_regular_price( $product );

		if ( empty( $price ) ) {
			return;
		}

		return '<' . $property . '>' . wc_format_decimal( $price, self::get_currency_decimals() ) . get_woocommerce_currency() . '</' . $property . '>';
	}

	/**
	 * Returns the sale price of the product, or the min sale price for a variable product.
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	private static function get_property_sale_price( $product, $property ) {

		if ( ! $product->get_parent_id() && method_exists( $product, 'get_variation_sale_price' ) ) {
			$regular_price = $product->get_variation_regular_price();
			$sale_price    = $product->get_variation_sale_price();
			$price         = $regular_price > $sale_price ? $sale_price : false;
		} else {
			$price = $product->get_sale_price();
		}

		if ( empty( $price ) ) {
			return;
		}

		return '<' . $property . '>' . wc_format_decimal( $price, self::get_currency_decimals() ) . get_woocommerce_currency() . '</' . $property . '>';
	}

	/**
	 * Returns the SKU in order to populate the MPN field.
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	private static function get_property_g_mpn( $product, $property ) {
		return '<' . $property . '>' . esc_xml( $product->get_sku() ) . '</' . $property . '>';
	}


	/**
	 * Returns the gallery images for the product.
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	private static function get_property_g_additional_image_link( $product, $property ) {

		$attachment_ids = $product->get_gallery_image_ids();
		$images         = array();

		if ( $attachment_ids && $product->get_image_id() ) {
			foreach ( $attachment_ids as $attachment_id ) {
				$image = self::get_product_image( $attachment_id );

				$images[] = $image ? $image[0] : false;
			}
		}

		if ( empty( $images ) ) {
			return;
		}

		return '<' . $property . '><![CDATA[' . implode( ',', $images ) . ']]></' . $property . '>';
	}

	/**
	 * Returns the product shipping information.
	 *
	 * @since 1.0.5
	 *
	 * @param WC_Product $product  The product.
	 * @param string     $property The name of the property.
	 * @return string
	 */
	private static function get_property_g_shipping( $product, $property ) {
		$currency      = get_woocommerce_currency();
		$shipping      = self::get_shipping();
		$shipping_info = $shipping->prepare_shipping_info( $product );

		if ( empty( $shipping_info ) ) {
			return '';
		}

		$shipping_nodes = array();

		/*
		 * Entry is a one or multiple XML nodes in the following format:
		 *  <g:shipping>
		 *		<g:country>...</g:country>
		 *		<g:region>...</g:region>
		 *		<g:service>...</g:service>
		 *		<g:price>...</g:price>
		 *	</g:shipping>
		 */
		foreach ( $shipping_info as $info ) {
			$shipping_nodes[] =
				'<g:shipping>' . PHP_EOL .
					"\t\t\t\t<g:country>$info[country]</g:country>" . PHP_EOL .
					( $info['state'] ? "\t\t\t\t<g:region>$info[state]</g:region>" . PHP_EOL : '' ) .
					"\t\t\t\t<g:service>$info[name]</g:service>" . PHP_EOL .
					"\t\t\t\t<g:price>$info[cost] $currency</g:price>" . PHP_EOL .
				"\t\t\t</g:shipping>";
		}

		return implode( PHP_EOL . "\t\t\t", $shipping_nodes );
	}

	/**
	 * Helper method to return the taxonomies of the product in a useful format.
	 *
	 * @param integer $product_id The product ID.
	 *
	 * @return array
	 */
	private static function get_taxonomies( $product_id ) {

		$terms = wc_get_object_terms( $product_id, 'product_cat' );

		if ( empty( $terms ) ) {
			return array();
		}

		return wp_list_pluck( $terms, 'name' );
	}

	/**
	 * Get locale currency decimals
	 */
	private static function get_currency_decimals() {
		$currencies = get_transient( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_currencies_list' );

		if ( ! $currencies ) {
			$locale_info = include WC()->plugin_path() . '/i18n/locale-info.php';

			$currencies = wp_list_pluck( $locale_info, 'num_decimals', 'currency_code' );
			set_transient( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_currencies_list', $currencies, DAY_IN_SECONDS );
		}

		return $currencies[ get_woocommerce_currency() ] ?? 2;
	}

	/** Fetch shipping object.
	 *
	 * @since 1.0.5
	 *
	 * @return Shipping
	 */
	private static function get_shipping() {
		if ( null === self::$shipping ) {
			self::$shipping = new Shipping();
			/**
			 * When we start generating lets make sure that the cart is loaded.
			 * Various shipping and tax functions are using elements of cart.
			 */
			wc_load_cart();
		}
		return self::$shipping;
	}

	/**
	 * Helper method to return the regular price of a product.
	 *
	 * @param WC_Product|WC_Product_Variable $product The product.
	 *
	 * @return string
	 */
	private static function get_product_regular_price( $product ) {
		if ( ! $product->get_parent_id() && method_exists( $product, 'get_variation_price' ) ) {
			$price = $product->get_variation_regular_price();
		} else {
			$price = $product->get_regular_price();
		}

		return $price;
	}

	/**
	 * Helper method to return an image taking into account the limit on resolution 5000x5000.
	 *
	 * @param int $image_id The ID of the image.
	 *
	 * @return array|false
	 */
	private static function get_product_image( $image_id ) {
		$image = wp_get_attachment_image_src( $image_id, 'full' );

		if ( ! $image ) {
			return false;
		}

		if ( $image[1] * $image[2] > self::IMAGE_MAX_RESOLUTION ) {

			$image = wp_get_attachment_image_src( $image_id, 'woocommerce_single' );
		}

		return $image ?? false;
	}
}
