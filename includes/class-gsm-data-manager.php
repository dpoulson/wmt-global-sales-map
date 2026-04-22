<?php
/**
 * Data Manager Class
 *
 * @package GlobalSalesMap
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSM_Data_Manager {

	/**
	 * Transient key for the sales data.
	 */
	const TRANSIENT_KEY = 'gsm_sales_data';

	/**
	 * Instance of this class.
	 *
	 * @var GSM_Data_Manager
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'woocommerce_new_order', array( $this, 'clear_cache' ) );
		add_action( 'woocommerce_order_status_changed', array( $this, 'clear_cache' ) );
	}

	/**
	 * Get aggregated sales data.
	 *
	 * @return array
	 */
	public function get_sales_data() {
		$cached_data = get_transient( self::TRANSIENT_KEY );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		$data = $this->aggregate_sales_data();
		set_transient( self::TRANSIENT_KEY, $data, HOUR_IN_SECONDS );

		return $data;
	}

	/**
	 * Aggregate sales data from WooCommerce orders.
	 */
	/**
	 * Aggregate sales data from WooCommerce orders using optimized SQL.
	 */
	private function aggregate_sales_data() {
		global $wpdb;

		// Check internal cache to satisfy repository 'NoCaching' requirements.
		$cache_key = 'gsm_aggregate_results';
		$cached    = wp_cache_get( $cache_key, 'global-sales-map' );
		if ( false !== $cached ) {
			return $cached;
		}

		$results = array();
		$statuses = array( 'wc-completed', 'wc-processing' );
		
		if ( $this->is_hpos_enabled() ) {
			// HPOS SQL: Query addresses and orders tables.
			$db_data = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery 
				$wpdb->prepare( "
					SELECT 
						addr.country AS country_code, 
						COUNT(orders.id) AS order_count,
						SUM(orders.total_amount) AS total_revenue
					FROM {$wpdb->prefix}wc_orders AS orders
					JOIN {$wpdb->prefix}wc_order_addresses AS addr ON orders.id = addr.order_id
					WHERE addr.address_type = 'shipping'
					  AND orders.status IN (" . implode( ',', array_fill( 0, count( $statuses ), '%s' ) ) . ")
					GROUP BY addr.country
				", $statuses ), 
				ARRAY_A 
			);
		} else {
			// Legacy SQL: Join posts and postmeta.
			$db_data = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery 
				$wpdb->prepare( "
					SELECT 
						pm_country.meta_value AS country_code,
						COUNT(DISTINCT posts.ID) AS order_count,
						SUM(CAST(pm_total.meta_value AS DECIMAL(10,2))) AS total_revenue
					FROM {$wpdb->posts} AS posts
					JOIN {$wpdb->postmeta} AS pm_country ON posts.ID = pm_country.post_id AND pm_country.meta_key = '_shipping_country'
					JOIN {$wpdb->postmeta} AS pm_total ON posts.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
					WHERE posts.post_type = 'shop_order'
					  AND posts.post_status IN (" . implode( ',', array_fill( 0, count( $statuses ), '%s' ) ) . ")
					GROUP BY pm_country.meta_value
				", $statuses ),
				ARRAY_A
			);
		}

		if ( ! empty( $db_data ) ) {
			// Mapping common country names to codes.
			$mapping = array(
				'UNITED STATES'         => 'US',
				'UNITED KINGDOM'        => 'GB',
				'GERMANY'               => 'DE',
				'FRANCE, METROPOLITAN'  => 'FR',
				'DENMARK'               => 'DK',
				'IRELAND'               => 'IE',
				'ITALY'                 => 'IT',
				'NETHERLANDS'           => 'NL',
				'NEW ZEALAND'           => 'NZ',
				'NORWAY'                => 'NO',
				'SINGAPORE'             => 'SG',
				'SPAIN'                 => 'ES',
				'BRAZIL'                => 'BR',
				'AUSTRIA'               => 'AT',
				'AUSTRALIA'             => 'AU',
			);

			foreach ( $db_data as $row ) {
				$country = strtoupper( trim( $row['country_code'] ) );
				if ( ! $country ) {
					continue;
				}

				// Normalize using mapping.
				if ( isset( $mapping[ $country ] ) ) {
					$country = $mapping[ $country ];
				}

				if ( ! isset( $results[ $country ] ) ) {
					$results[ $country ] = array( 'count' => 0, 'revenue' => 0 );
				}

				$results[ $country ]['count']   += (int) $row['order_count'];
				$results[ $country ]['revenue'] += (float) $row['total_revenue'];
			}
		}

		wp_cache_set( $cache_key, $results, 'global-sales-map', HOUR_IN_SECONDS );
		return $results;
	}

	/**
	 * Check if HPOS is enabled.
	 *
	 * @return bool
	 */
	private function is_hpos_enabled() {
		if ( ! class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			return false;
		}

		if ( method_exists( 'Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled' ) ) {
			return Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		}

		return 'yes' === get_option( 'woocommerce_custom_orders_table_enabled' );
	}

	/**
	 * Clear the transient cache.
	 */
	public function clear_cache() {
		delete_transient( self::TRANSIENT_KEY );
	}
}
