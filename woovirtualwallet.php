<?php
/**
 * Plugin Name: WooVirtualWallet
 * Description: A virtual wallet for your customers
 * Plugin URI: https://plugins.longwatchstudio.com/product/woovirtualwallet/
 * Author: Long Watch Studio
 * Author URI: https://longwatchstudio.com
 * Version: 2.2.1
 * License: Copyright LongWatchStudio 2021
 * Text Domain: woovirtualwallet-lite
 * Domain Path: /languages
 * WC requires at least: 3.6.0
 * WC tested up to: 5.0
 *
 * Copyright (c) 2021 Long Watch Studio (email: contact@longwatchstudio.com). All rights reserved.
 *
 *
 */


// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** That class holds the entire plugin. */
final class LWS_WooVirtualWallet
{

	public static function init()
	{
		static $instance = false;
		if( !$instance )
		{
			$instance = new self();
			$instance->defineConstants();
			$instance->load_plugin_textdomain();

			add_action( 'lws_adminpanel_register', array($instance, 'register') );
			add_action( 'lws_adminpanel_plugins', array($instance, 'plugin') );

			add_filter( 'plugin_action_links_'.plugin_basename( __FILE__ ), array($instance, 'extensionListActions'), 10, 2 );
			add_filter( 'plugin_row_meta', array($instance, 'addLicenceLink'), 10, 4 );
			add_filter( 'lws_adminpanel_purchase_url_woovirtualwallet', array($instance, 'addPurchaseUrl'), 10, 1 );
			add_filter('lws_adm_trialend_msg', array($instance, 'getTrialEndMessage'), 10, 4);
			add_filter('lws_adm_trialstart_msg', array($instance, 'getTrialStartMessage'), 10, 3);
			foreach( array('') as $page)
				add_filter( 'lws_adminpanel_plugin_version_'.LWS_WOOVIRTUALWALLET_PAGE.$page, array($instance, 'addPluginVersion'), 10, 1 );
			add_filter( 'lws_adminpanel_documentation_url_woovirtualwallet', array($instance, 'addDocUrl'), 10, 1 );

			if( \is_admin() && !(defined('DOING_AJAX') && DOING_AJAX) )
			{
				require_once LWS_WOOVIRTUALWALLET_INCLUDES.'/updater.php';
				// piority as soon as possible, But sad bug from WP.
				// Trying to get property of non-object in ./wp-includes/post.php near line 3917: $feeds = $wp_rewrite->feeds;
				// cannot do it sooner.
				add_action('setup_theme', array('\LWS\WOOVIRTUALWALLET\Updater', 'checkUpdate'), -100);
				add_action('setup_theme', array($instance, 'forceVisitLicencePage'), 0);
			}

			$instance->install();

			register_activation_hook( __FILE__, 'LWS_WooVirtualWallet::activation' );
		}
		return $instance;
	}

	function forceVisitLicencePage()
	{
		if( \get_option('lws_woovirtualwallet_redirect_to_licence', 0) > 0 )
		{
			\update_option('lws_woovirtualwallet_redirect_to_licence', 0);
			if( \wp_redirect(\add_query_arg(array('page'=>LWS_WOOVIRTUALWALLET_PAGE . '-system', 'tab'=>'lic'), admin_url('admin.php'))) )
				exit;
		}
	}

	public function v()
	{
		static $version = '';
		if( empty($version) ){
			if( !function_exists('get_plugin_data') ) require_once(ABSPATH . 'wp-admin/includes/plugin.php');
			$data = \get_plugin_data(__FILE__, false);
			$version = (isset($data['Version']) ? $data['Version'] : '0');
		}
		return $version;
	}

	/** Load translation file
	 * If called via a hook like this
	 * @code
	 * add_action( 'plugins_loaded', array($instance,'load_plugin_textdomain'), 1 );
	 * @endcode
	 * Take care no text is translated before. */
	function load_plugin_textdomain() {
		load_plugin_textdomain( 'woovirtualwallet-lite', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Define the plugin constants
	 *
	 * @return void
	 */
	private function defineConstants()
	{
		define( 'LWS_WOOVIRTUALWALLET_VERSION', $this->v() );
		define( 'LWS_WOOVIRTUALWALLET_FILE', __FILE__ );
		define( 'LWS_WOOVIRTUALWALLET_DOMAIN', 'woovirtualwallet-lite' );
		define( 'LWS_WOOVIRTUALWALLET_PAGE', 'woovirtualwallet' );

		define( 'LWS_WOOVIRTUALWALLET_PATH', dirname( LWS_WOOVIRTUALWALLET_FILE ) );
		define( 'LWS_WOOVIRTUALWALLET_INCLUDES', LWS_WOOVIRTUALWALLET_PATH . '/include' );
		define( 'LWS_WOOVIRTUALWALLET_SNIPPETS', LWS_WOOVIRTUALWALLET_PATH . '/snippets' );
		define( 'LWS_WOOVIRTUALWALLET_ASSETS',   LWS_WOOVIRTUALWALLET_PATH . '/assets' );

		define( 'LWS_WOOVIRTUALWALLET_URL', 		plugins_url( '', LWS_WOOVIRTUALWALLET_FILE ) );
		define( 'LWS_WOOVIRTUALWALLET_JS',  		plugins_url( '/js', LWS_WOOVIRTUALWALLET_FILE ) );
		define( 'LWS_WOOVIRTUALWALLET_CSS', 		plugins_url( '/css', LWS_WOOVIRTUALWALLET_FILE ) );
		define( 'LWS_WOOVIRTUALWALLET_IMG', 		plugins_url( '/img', LWS_WOOVIRTUALWALLET_FILE ) );

		global $wpdb;
		$wpdb->lwsUserWalletHistory = $wpdb->base_prefix . 'lws_wvw_history';
	}

	public function extensionListActions($links, $file)
	{
		$label = __('Settings'); // use standart wp sentence, no text domain
		$url = add_query_arg(array('page'=>LWS_WOOVIRTUALWALLET_PAGE), admin_url('admin.php'));
		array_unshift($links, "<a href='$url'>$label</a>");
		$label = __('Help'); // use standart wp sentence, no text domain
		$url = esc_attr($this->addDocUrl(''));
		$links[] = "<a href='$url'>$label</a>";
		return $links;
	}

	public function addLicenceLink($links, $file, $data, $status)
	{
		if( (!defined('LWS_WOOVIRTUALWALLET_ACTIVATED') || !LWS_WOOVIRTUALWALLET_ACTIVATED) && plugin_basename(__FILE__)==$file)
		{
			$label = __('Add Licence Key', 'woovirtualwallet-lite');
			$url = add_query_arg(array('page'=>LWS_WOOVIRTUALWALLET_PAGE, 'tab'=>'license'), admin_url('admin.php'));
			$links[] = "<a href='$url'>$label</a>";
		}
		return $links;
	}

	public function addPurchaseUrl($url)
	{
		return __("https://plugins.longwatchstudio.com/product/woovirtualwallet/", 'woovirtualwallet-lite');
	}

	public function addPluginVersion($url)
	{
		return $this->v();
	}

	public function addDocUrl($url)
	{
		return __("https://plugins.longwatchstudio.com/docs/woovirtualwallet/", 'woovirtualwallet-lite');
	}

	function register()
	{
		require_once LWS_WOOVIRTUALWALLET_INCLUDES . '/ui/admin.php';
		new \LWS\WOOVIRTUALWALLET\Ui\Admin();
	}

	public function plugin()
	{
		\lws_plugin(__FILE__, LWS_WOOVIRTUALWALLET_PAGE, md5(\get_class() . 'update'), 'LWS_WOOVIRTUALWALLET_ACTIVATED', LWS_WOOVIRTUALWALLET_PAGE.'-system');
	}

	/** Add elements we need on this plugin to work */
	public static function activation()
	{
		require_once dirname(__FILE__).'/include/updater.php';
		\LWS\WOOVIRTUALWALLET\Updater::checkUpdate();
	}

	/** autoload WooVirtualWallet core and collection classes. */
	public function autoload($class)
	{
		$domain = 'LWS\WOOVIRTUALWALLET\\';
		if( 0 === strpos($class, $domain) )
		{
			$rest = substr($class, strlen($domain));
			$publicNamespaces = array(
				'Core'
			);
			$publicClasses = array(
				'Ui\Widgets\Widget'
			);

			if( in_array(explode('\\', $rest, 2)[0], $publicNamespaces) || in_array($rest, $publicClasses) )
			{
				$basename = str_replace('\\', '/', strtolower($rest));
				$filepath = LWS_WOOVIRTUALWALLET_INCLUDES . '/' . $basename . '.php';
				if( file_exists($filepath) )
				{
					@include_once $filepath;
					return true;
				}
			}
		}
	}

	/**	Is WooCommerce installed and activated.
	 *	Could be sure only after hook 'plugins_loaded'.
	 *	@return is WooCommerce installed and activated. */
	static public function isWC()
	{
//		return in_array('woocommerce/woocommerce.php', \apply_filters('active_plugins', \get_option('active_plugins')));
		return function_exists('wc');
	}

	private function install()
	{
		spl_autoload_register(array($this, 'autoload'));

		\add_action('setup_theme', array($this, 'installUsersColumns'));
		\add_action('plugins_loaded', array($this, 'installProductOptions')); // very soon since order checkout is involved

		require_once LWS_WOOVIRTUALWALLET_INCLUDES . '/ajax.php';
		\LWS\WOOVIRTUALWALLET\Ajax::install();

		require_once LWS_WOOVIRTUALWALLET_INCLUDES . '/ui/widgets/balance.php';
		\LWS\WOOVIRTUALWALLET\Ui\Widgets\Balance::install();

		require_once LWS_WOOVIRTUALWALLET_INCLUDES . '/ui/widgets/history.php';
		\LWS\WOOVIRTUALWALLET\Ui\Widgets\History::install();

		\add_filter('woocommerce_payment_gateways', function($gateways){
			if( !isset($gateways['woovirtualwallet']) && \LWS_WooVirtualWallet::isModeWallet() )
				$gateways['woovirtualwallet'] = '\LWS\WOOVIRTUALWALLET\Core\Gateway';
			return $gateways;
		}, 11);

		\add_action('wp_enqueue_scripts', array($this, 'enqueueSymbolStyle'));
		\add_action('admin_enqueue_scripts', array($this, 'enqueueSymbolStyle'));
	}

	function installUsersColumns()
	{
		/// @see \LWS\WOOVIRTUALWALLET\Ui\Users::readOptions()
		$usersColumns = array();
		if( self::isModeWallet() )
			$usersColumns['wallet'] = array();

		$usersColumns = \apply_filters('lws_woovirtualwallet_users_wallet_columns', $usersColumns);
		if( $usersColumns )
		{
			require_once LWS_WOOVIRTUALWALLET_INCLUDES . '/ui/users.php';
			foreach( $usersColumns as $options )
				new \LWS\WOOVIRTUALWALLET\Ui\Users($options);
		}
	}

	function installProductOptions()
	{
		/// @see \LWS\WOOVIRTUALWALLET\Ui\Product::readOptions()
		$optionsSets = array();
		if( self::isModeWallet() )
			$optionsSets['wallet'] = array();

		$optionsSets = \apply_filters('lws_woovirtualwallet_product_wallet_options_sets', $optionsSets);
		if( $optionsSets )
		{
			require_once LWS_WOOVIRTUALWALLET_INCLUDES . '/ui/product.php';
			foreach( $optionsSets as $options )
				new \LWS\WOOVIRTUALWALLET\Ui\Product($options);
		}
	}

	static function getMode($refresh=false)
	{
		static $mode = false;
		if( false === $mode || $refresh )
		{
			$mode = \apply_filters('lws_woovirtualwallet_mode', 'wallet');
		}
		return $mode;
	}

	static function isModeWallet()
	{
		$mode = self::getMode();
		return ('wallet' == $mode || !$mode);
	}

	static function isModeGems()
	{
		$mode = self::getMode();
		return ('gems' == $mode || !$mode);
	}

	function enqueueSymbolStyle()
	{
		\wp_enqueue_style('lws-wvw-currency-symbol', LWS_WOOVIRTUALWALLET_CSS.'/wallet.css', array(), LWS_WOOVIRTUALWALLET_VERSION);
	}

	static function helper()
	{
		static $hlp = false;
		if( false === $hlp )
		{
			$classname = \apply_filters('lws_woovirtualwallet_helper_classname', '\LWS\WOOVIRTUALWALLET\Core\Helper');
			$hlp = new $classname();
		}
		return $hlp;
	}

	function getTrialEndMessage($msg, $slug, $date, $link)
	{
		if ('woovirtualwallet' != $slug)
			return $msg;
		$msg  = "<h2>" . __('Your WooVirtualWallet Premium trial period is about to expire', 'woovirtualwallet-lite') . "</h2>";
		$msg .= "<p>" . sprintf(__('Thank you for trying WooVirtualWallet Premium. The trial period will end on <b>%s</b>', 'woovirtualwallet-lite'), $date) . "</p>";
		$msg .= "<h4><b>" . sprintf(__('If you want to keep using all Pro Features, please purchase a %s License', 'woovirtualwallet-lite'), $link) . "</b></h4>";
		$msg .= "<p>" . __('Premium Version features include :', 'woovirtualwallet-lite') . "</p>";
		$msg .= "<ul style='list-style-type:square;padding-left:20px;'><li>" . __('Partial Payment', 'woovirtualwallet-lite') . "</li>";
		$msg .= "<li>" . __('Gmes Mode', 'woovirtualwallet-lite') . "</li>";
		$msg .= "<li>" . __('Gems Products', 'woovirtualwallet-lite') . "</li>";
		$msg .= "<li>" . __('Gems to Discounts', 'woovirtualwallet-lite') . "</li>";
		$msg .= "<li>" . __('Lots of widgets and shortcodes', 'woovirtualwallet-lite') . "</li>";
		$msg .= "<li>" . __('And a lot more ...', 'woovirtualwallet-lite') . "</li>";
		$msg .= "</ul>";
		$msg .= "<p>" . __('Purchase WooVirtualWallet Premium today.', 'woovirtualwallet-lite') . "</p>";
		return $msg;
	}

	function getTrialStartMessage($msg, $slug, $date)
	{
		if ('woovirtualwallet' != $slug)
			return $msg;
		$msg  = "<h2>" . __('Welcome to your WooVirtualWallet Premium trial', 'woovirtualwallet-lite') . "</h2>";
		$msg .= "<p>" . sprintf(__('Thank you for trying WooVirtualWallet Premium. The trial period will end on <b>%s</b>', 'woovirtualwallet-lite'), $date) . "</p>";
		$msg .= "<p>" . __('Premium Version features include :', 'woovirtualwallet-lite') . "</p>";
		$msg .= "<ul style='list-style-type:square;padding-left:20px;'><li>" . __('Partial Payment', 'woovirtualwallet-lite') . "</li>";
		$msg .= "<li>" . __('Gmes Mode', 'woovirtualwallet-lite') . "</li>";
		$msg .= "<li>" . __('Gems Products', 'woovirtualwallet-lite') . "</li>";
		$msg .= "<li>" . __('Gems to Discounts', 'woovirtualwallet-lite') . "</li>";
		$msg .= "<li>" . __('Lots of widgets and shortcodes', 'woovirtualwallet-lite') . "</li>";
		$msg .= "<li>" . __('And a lot more ...', 'woovirtualwallet-lite') . "</li>";
		$msg .= "</ul>";
		$msg .= "<p>" . __('Try all these premium features and create the perfect loyalty program for your website', 'woovirtualwallet-lite') . "</p>";
		$msg .= "<h4><b>" . __('At the end of your trial, consider purchasing a WooVirtualWallet License', 'woovirtualwallet-lite') . "</b></h4>";
		return $msg;
	}
}

LWS_WooVirtualWallet::init();
{
	if( \file_exists($asset = (dirname(__FILE__) . '/assets/lws-adminpanel/lws-adminpanel.php')) )
		include_once $asset;
	if( \file_exists($asset = (dirname(__FILE__) . '/modules/woovirtualwallet-pro/woovirtualwallet-pro.php')) )
		include_once $asset;
}
