<?php
/**
 * Plugin Name: We Make Things: Global Sales Map
 * Plugin URI:  https://we-make-things.co.uk/global-sales-map/
 * Description: A privacy-first, interactive world heatmap for WooCommerce sales analytics.
 * Version:     1.3.4
 * Author:      We Make Things
 * Author URI:  https://we-make-things.co.uk/
 * Text Domain: wmt-global-sales-map
 * Domain Path: /languages
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 *
 * @package GlobalSalesMap
 */

if (!defined('ABSPATH')) {
	exit;
}

// Define constants.
define('GSM_VERSION', '1.3.4');
define('GSM_PATH', plugin_dir_path(__FILE__));
define('GSM_URL', plugin_dir_url(__FILE__));

register_activation_hook(__FILE__, array('Global_Sales_Map', 'activate'));

/**
 * Main Plugin Class
 */
class Global_Sales_Map
{

	/**
	 * Instance of this class.
	 *
	 * @var Global_Sales_Map
	 */
	private static $instance = null;

	/**
	 * Get instance of this class.
	 */
	public static function get_instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct()
	{
		add_action('plugins_loaded', array($this, 'init'));
	}

	/**
	 * Initialize the plugin.
	 */
	public function init()
	{
		if (!class_exists('WooCommerce')) {
			add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
			return;
		}

		$this->includes();
		$this->hooks();
	}

	/**
	 * Include required files.
	 */
	private function includes()
	{
		require_once GSM_PATH . 'includes/class-gsm-data-manager.php';
		require_once GSM_PATH . 'includes/class-gsm-assets.php';
		require_once GSM_PATH . 'includes/class-gsm-settings.php';
	}

	/**
	 * Register hooks.
	 */
	private function hooks()
	{
		// Initialize classes.
		GSM_Data_Manager::get_instance();
		GSM_Assets::get_instance();
		GSM_Settings::get_instance();

		// Register shortcode.
		add_shortcode('global_sales_map', array($this, 'render_map_shortcode'));
	}

	/**
	 * Render the map shortcode.
	 */
	public function render_map_shortcode($atts)
	{
		return GSM_Assets::get_instance()->render_map_container();
	}

	/**
	 * WooCommerce missing notice.
	 */
	public function woocommerce_missing_notice()
	{
		echo '<div class="error"><p>' . esc_html__('Global Sales Map requires WooCommerce to be installed and active.', 'wmt-global-sales-map') . '</p></div>';
	}

	/**
	 * Activation hook.
	 */
	public static function activate()
	{
		delete_transient('gsm_sales_data');
	}
}

// Start the plugin.
Global_Sales_Map::get_instance();
