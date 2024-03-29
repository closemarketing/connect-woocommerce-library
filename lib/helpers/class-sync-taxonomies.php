<?php
/**
 * Sync Products
 *
 * @package    WordPress
 * @author     David Perez <david@close.technology>
 * @copyright  2023 Closemarketing
 * @version    1.0
 */

namespace CLOSE\WooCommerce\Library\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Sync Products.
 *
 * @since 1.0.0
 */
class TAX {
	/**
	 * Create global attributes in WooCommerce
	 *
	 * @param array   $attributes Attributes array.
	 * @param boolean $for_variation Is for variation.
	 * @return array
	 */
	public static function make_attributes( $attributes, $for_variation = true ) {
		$position          = 0;
		$attributes_return = array();
		foreach ( $attributes as $attr_name => $attr_values ) {
			$attribute = new \WC_Product_Attribute();
			$attribute->set_id( 0 );
			$attribute->set_position( $position );
			$attribute->set_visible( true );
			$attribute->set_variation( $for_variation );

			$attribute_labels = wp_list_pluck( wc_get_attribute_taxonomies(), 'attribute_label', 'attribute_name' );
			$attribute_name   = array_search( $attr_name, $attribute_labels, true );

			if ( ! $attribute_name ) {
				$attribute_name = wc_sanitize_taxonomy_name( $attr_name );
			}

			$attribute_id = wc_attribute_taxonomy_id_by_name( $attribute_name );

			if ( ! $attribute_id ) {
				$attribute_id = self::create_global_attribute( $attr_name );
			}
			$slug          = wc_sanitize_taxonomy_name( $attr_name );
			$taxonomy_name = wc_attribute_taxonomy_name( $slug );

			$attribute->set_name( $taxonomy_name );
			$attribute->set_id( $attribute_id );
			$attribute->set_options( $attr_values );

			$attributes_return[] = $attribute;
			$position++;
		}
		return $attributes_return;
	}

	/**
	 * Create a new global attribute.
	 *
	 * @param string $raw_name Attribute name (label).
	 * @return int Attribute ID.
	 */
	private static function create_global_attribute( $raw_name ) {
		$slug = wc_sanitize_taxonomy_name( $raw_name );

		$attribute_id = wc_create_attribute(
			array(
				'name'         => $raw_name,
				'slug'         => $slug,
				'type'         => 'select',
				'order_by'     => 'menu_order',
				'has_archives' => false,
			)
		);

		$taxonomy_name = wc_attribute_taxonomy_name( $slug );
		register_taxonomy(
			$taxonomy_name,
			apply_filters( 'woocommerce_taxonomy_objects_' . $taxonomy_name, array( 'product' ) ),
			apply_filters(
				'woocommerce_taxonomy_args_' . $taxonomy_name,
				array(
					'labels'       => array(
						'name' => $raw_name,
					),
					'hierarchical' => true,
					'show_ui'      => false,
					'query_var'    => true,
					'rewrite'      => false,
				)
			)
		);

		delete_transient( 'wc_attribute_taxonomies' );

		return $attribute_id;
	}

	/**
	 * Set Terms Taxonomy
	 *
	 * @param string       $taxonomy Taxonomy name.
	 * @param array|string $terms Terms to set.
	 * @param int          $post_id Post id.
	 *
	 * @return void
	 */
	public static function set_terms_taxonomy( $taxonomy, $terms, $post_id ) {
		$terms     = is_array( $terms ) ? $terms : array( $terms );
		$terms_ids = self::find_categories_ids( $terms, $taxonomy );
		wp_set_object_terms( $post_id, $terms_ids, $taxonomy );
	}

	/**
	 * Finds product categories ids from array of names given
	 *
	 * @param array  $product_cat_names Array of names.
	 * @param string $taxonomy_name Name of taxonomy.
	 * @return string IDS of categories.
	 */
	private static function find_categories_ids( $product_cat_names, $taxonomy_name = 'product_cat' ) {
		$level    = 0;
		$cats_ids = array();

		foreach ( $product_cat_names as $product_cat_name ) {
			$product_cat_name = is_array( $product_cat_name ) ? $product_cat_name['value'] : $product_cat_name;
			$cat_slug         = sanitize_title( $product_cat_name );
			$product_cat      = get_term_by( 'slug', $cat_slug, $taxonomy_name );

			if ( $product_cat ) {
				// Finds the category.
				$cats_ids[ $level ] = $product_cat->term_id;
			} else {
				$parent_prod_id = 0;
				if ( $level > 0 ) {
					$parent_prod_id = $cats_ids[ $level - 1 ];
				}
				// Creates the category.
				$term = wp_insert_term(
					$product_cat_name,
					$taxonomy_name,
					array(
						'slug'   => $cat_slug,
						'parent' => $parent_prod_id,
					)
				);
				if ( ! is_wp_error( $term ) ) {
					$cats_ids[ $level ] = $term['term_id'];
				}
			}
			$level++;
		}

		return $cats_ids;
	}

	/**
	 * Get categories ids
	 *
	 * @param array   $settings Settings of the plugin.
	 * @param string  $item_type Type of the product.
	 * @param boolean $is_new_product Is new.
	 * @return array
	 */
	public static function get_categories_ids( $settings, $item_type, $is_new_product ) {
		$categories_ids = array();
		// Category API.
		$category_newp = isset( $settings['catnp'] ) ? $settings['catnp'] : 'yes';
		$category_sep  = isset( $settings['catsep'] ) ? $settings['catsep'] : '';

		if ( ( ! empty( $item_type ) && 'yes' === $category_newp && $is_new_product ) ||
			( ! empty( $item_type ) && 'no' === $category_newp && false === $is_new_product )
		) {
			$category_array = array();
			if ( $category_sep && strpos( $item_type, $category_sep ) ) {
				$category_array = explode( $category_sep, $item_type );
			} else {
				$category_array[] = $item_type;
			}
			$categories_ids = self::find_categories_ids( $category_array );
		}
		return $categories_ids;
	}

	/**
	 * Assigns the array to a taxonomy, and creates missing term
	 *
	 * @param string $post_id Post id of actual post id.
	 * @param array  $taxonomy_slug Slug of taxonomy.
	 * @param array  $category_array Array of category.
	 * @return void
	 */
	private static function assign_product_term( $post_id, $taxonomy_slug, $category_array ) {
		$parent_term      = '';
		$term_levels      = count( $category_array );
		$term_level_index = 1;

		foreach ( $category_array as $category_name ) {
			$category_name = self::sanitize_text( $category_name );
			$search_term   = term_exists( $category_name, $taxonomy_slug );

			if ( 0 === $search_term || null === $search_term ) {
				// Creates taxonomy.
				$args_term = array(
					'slug' => sanitize_title( $category_name ),
				);
				if ( $parent_term ) {
					$args_term['parent'] = $parent_term;
				}
				$search_term = wp_insert_term(
					$category_name,
					$taxonomy_slug,
					$args_term
				);
			}
			if ( $term_level_index === $term_levels ) {
				wp_set_object_terms( $post_id, (int) $search_term['term_id'], $taxonomy_slug );
			}

			// Next iteration for child.
			$parent_term = $search_term['term_id'];
			$term_level_index++;
		}
	}

	/**
	 * Internal function to sanitize text
	 *
	 * @param string $text Text to sanitize.
	 * @return string Sanitized text.
	 */
	private static function sanitize_text( $text ) {
		$text = str_replace( '>', '&gt;', $text );
		return $text;
	}

	/**
	 * Return all custom taxonomies
	 *
	 * @return array
	 */
	public static function get_all_custom_taxonomies() {
		$taxonomies        = get_taxonomies( array( 'public' => true ), 'objects' );
		$custom_taxonomies = array();
		foreach ( $taxonomies as $taxonomy ) {
			if ( in_array( $taxonomy->name, array( 'product_type', 'product_shipping_class' ), true ) ) {
				continue;
			}
			$custom_taxonomies[ 'tax|' . $taxonomy->name ] = $taxonomy->label;
		}
		return $custom_taxonomies;
	}
}
