<?php
namespace LWS\WOOVIRTUALWALLET\Ui;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Create the backend menu and settings pages. */
class Admin
{
	public function __construct()
	{
		\lws_register_pages($this->managePages());
		\add_action('admin_enqueue_scripts', array($this , 'scripts'));

		if( \is_admin() && !(defined('DOING_AJAX') && DOING_AJAX) )
		{
			if( isset($_GET['page']) && $_GET['page'] == LWS_WOOVIRTUALWALLET_PAGE.'-gateway' )
			{
				\wp_redirect(\add_query_arg(array('page'=>'wc-settings', 'tab'=>'checkout', 'section'=>'lws_woovirtualwallet_gateway'), \admin_url('admin.php')));
			}
			else if( isset($_GET['page']) && $_GET['page'] == LWS_WOOVIRTUALWALLET_PAGE.'-users' )
			{
				\wp_redirect(\admin_url('users.php'));
			}
		}
	}

	protected function getCurrentPage()
	{
		if (isset($_REQUEST['page']) && ($current = \sanitize_text_field($_REQUEST['page'])))
			return $current;
		if (isset($_REQUEST['option_page']) && ($current = \sanitize_text_field($_REQUEST['option_page'])))
			return $current;
		return false;
	}

	protected function managePages()
	{
		$pages = array();
		$pages[LWS_WOOVIRTUALWALLET_PAGE . '-resume'] = $this->getResumePage();
		$pages[LWS_WOOVIRTUALWALLET_PAGE . '-users'] = $this->getUsersPage();
		$pages[LWS_WOOVIRTUALWALLET_PAGE . '-settings'] = $this->getSettingsPage();
		$pages[LWS_WOOVIRTUALWALLET_PAGE . '-appearance'] = $this->getAppearancePage();
		if (\LWS_WooVirtualWallet::isModeWallet())
		{
			$pages[LWS_WOOVIRTUALWALLET_PAGE . '-gateway'] = $this->getGatewayPage();
		}
		$pages[LWS_WOOVIRTUALWALLET_PAGE . '-system'] = $this->getSystemPage();
		return $pages;
	}

	protected function getResumePage()
	{
		$resumePage = array(
			'title'	    => __("WooVirtualWallet", 'woovirtualwallet-lite'),
			'id'	      => LWS_WOOVIRTUALWALLET_PAGE,
			'rights'    => 'edit_virtual_wallet_options',
			'dashicons' => '',
			'index'     => 58,
			'resume'    => true,
			'tabs'	    => array(
				'wvw_users' => array(
					'title'  => __("Users", 'woovirtualwallet-lite'),
					'id'     => 'resume_users',
				)
			)
		);
		return $resumePage;
	}

	protected function getUsersPage()
	{
		$description = array(
			__("The users page will redirect you to the standard WordPress Users page where you will have some new possibilities:", 'woovirtualwallet-lite'),
			__("See users' wallet balance", 'woovirtualwallet-lite'),
			__("See users' wallet history", 'woovirtualwallet-lite'),
			__("Export users' wallet balance", 'woovirtualwallet-lite'),
			__("Add/Remove wallet credit", 'woovirtualwallet-lite'),
		);
		$usersPage = array(
			'id' => LWS_WOOVIRTUALWALLET_PAGE . '-users',
			'rights'    => 'read_other_virtual_wallet',
			'title'     => __("Users", 'woovirtualwallet-lite'),
			'subtitle' => __("Users", 'woovirtualwallet-lite'),
			'color' => '#A8CE38',
			'image'		=> LWS_WOOVIRTUALWALLET_IMG . '/r-customers.png',
			'description' => "<p>" . $description[0] . "</p><ul>" .
			"<li><span>" . $description[1] . "</span></li><li><span>" . $description[2] . "</span></li><li><span>" . $description[3] . "</span></li>" .
			"<li><span>" . $description[4] . "</span></li></ul>",
			'tabs' => array(
				'users' => array(
					'id' => 'users',
					'title' => __("Users", 'woovirtualwallet-lite'),
					'groups' => array(
						'users' => array(
							'id' => 'users',
							'title' => __("Users", 'woovirtualwallet-lite'),
						),
					),
				),
			),
		);
		return $usersPage;
	}

	protected function getSettingsPage()
	{
		$description = array(
			__("The settings page gives access to the following settings :", 'woovirtualwallet-lite'),
			__("Order status needed to get wallet credit", 'woovirtualwallet-lite'),
			__("Prevent products that give wallet credit to be bought with the wallet", 'woovirtualwallet-lite'),
		);
		$gatewaygroup = array();
		if (\LWS_WooVirtualWallet::isModeWallet())
		{
			$gatewaygroup = array(
				'id'     => 'gateway',
				'title'  => __("Payment Gateway", 'woovirtualwallet-lite'),
				'icon'	 => 'lws-icon-wallet-44',
				'fields' => array(
					'gateway_link' => array(
						'id'    => 'lws_woovirtualwallet_gateway_link',
						'title' => __("Payment Gateway", 'woovirtualwallet-lite'),
						'type'  => 'custom',
						'extra' => array(
							'content' => sprintf(__("<a href='%s' target='_blank'> Setup WooVirtualWallet as a Payment Gateway</a>", 'woovirtualwallet-lite'), \esc_attr(\add_query_arg(array('page' => 'wc-settings', 'tab' => 'checkout', 'section' => 'lws_woovirtualwallet_gateway'), \admin_url('admin.php')))),
							'help' => __("You need to set up WooVirtualWallet as a WooCommerce Payment Gateway. You can do this by clicking the link below", 'woovirtualwallet-lite'),
						)
					)
				),
			);
		}
		$settingsPage = array(
			'id' => LWS_WOOVIRTUALWALLET_PAGE . '-settings',
			'rights'    => 'edit_virtual_wallet_options',
			'title'     => __("Settings", 'woovirtualwallet-lite'),
			'color'			=> '#7AC943',
			'image'			=> LWS_WOOVIRTUALWALLET_IMG . '/r-features.png',
			'description' => "<p>" . $description[0] . "</p><ul>" .
			"<li><span>" . $description[1] . "</span></li><li><span>" . $description[2] . "</span></li></ul>",
			'tabs' => array(
				'settings' => array(
					'id'     => 'settings',
					'title'  => __("Settings", 'woovirtualwallet-lite'),
					'icon'	=> 'lws-icon-setup-preferences',
					'groups' => array(
						'purchase' => $this->getPurchaseGroup(),
						'users' => array(
							'id'    => 'users',
							'icon'	=> 'lws-icon-users-wm',
							'title' => __("Customers Status", 'woovirtualwallet-lite'),
							'text'	=> __("See and edit the customers' wallets", 'woovirtualwallet-lite'),
							'fields' => array(
								'users_link' => array(
									'id'    => 'lws_woovirtualwallet_users_link',
									'title' => __("Customers Wallet Status", 'woovirtualwallet-lite'),
									'type'  => 'custom',
									'extra' => array(
										'content' => sprintf(__("<a href='%s' target='_blank'>Check/Edit the users wallet money</a>", 'woovirtualwallet-lite'), \esc_attr(\admin_url('users.php'))),
										'help' => __("You can see and edit the wallet amount of your customers by going to the users page or clicking the link down below.", 'woovirtualwallet-lite'),
									)
								)
							),
						),
						'gateway' => $gatewaygroup
					),
				),

			),
		);

		return $settingsPage;
	}

	protected function getAppearancePage()
	{
		$description = array(
			__("The appearance page will let you customize the appearance of Widgets and shortcodes.", 'woovirtualwallet-lite'),
			__("You will also find there the description and documentation to use the available shortcodes.", 'woovirtualwallet-lite'),
		);
		$appearancePage = array(
			'id' => LWS_WOOVIRTUALWALLET_PAGE . '-appearance',
			'rights'    => 'read_other_virtual_wallet',
			'title'     => __("Appearance", 'woovirtualwallet-lite'),
			'color'			=> '#4CBB41',
			'image'			=> LWS_WOOVIRTUALWALLET_IMG . '/r-appearance.png',
			'description' => "<p>" . $description[0] . "</p><p>" . $description[1] . "</p>",
			'tabs' => array(
				'widgets' => array(
					'id'     => 'widgets',
					'title'  => __("Widgets", 'woovirtualwallet-lite'),
					'icon'	=> 'lws-icon-components',
					'groups' => array(
						'balance' => array(
							'id'     => 'balance',
							'title'  => __("Wallet Balance", 'woovirtualwallet-lite'),
							'icon'	=> 'lws-icon-wallet-44',
							'fields' => array(
								'balance' => array(
									'id' => 'lws_woovirtualwallet_widget_balance',
									'type' => 'stygen',
									'extra' => array(
										'purpose'  => 'filter',
										'template' => 'lws_wvw_balance',
										'html'     => false,
										'css'      => LWS_WOOVIRTUALWALLET_CSS . '/templates/balance.css',
										'subids'   => array(
											'lws_woovirtualwallet_wallet_widget_header' => "WooVirtualWallet - balance Widget - Header",
										),
										'help'    => sprintf(
											__("Use the shortcode %s to show the current customer virtual wallet balance", 'woovirtualwallet-lite'),
											"<span class='lws-group-descr-copy lws_ui_value_copy'><div class='lws-group-descr-copy-text content' tabindex='0'>[wallet_balance]</div><div class='lws-group-descr-copy-icon lws-icon lws-icon-copy copy'></div></span>"
										) . "<br/><br/>" .
											sprintf(
												__("Use the shortcode %s to show the current customer virtual wallet balance without styling", 'woovirtualwallet-lite'),
												"<span class='lws-group-descr-copy lws_ui_value_copy'><div class='lws-group-descr-copy-text content' tabindex='0'>[wallet_simple_balance]</div><div class='lws-group-descr-copy-icon lws-icon lws-icon-copy copy'></div></span>"
											),
									)
								),
							)
						),
						'history' => array(
							'id'     => 'history',
							'title'  => __("Wallet History", 'woovirtualwallet-lite'),
							'icon'	=> 'lws-icon-calendar',
							'fields' => array(
								'history' => array(
									'id' => 'lws_woovirtualwallet_widget_history',
									'type' => 'stygen',
									'extra' => array(
										'purpose'  => 'filter',
										'template' => 'lws_wvw_history',
										'html'     => false,
										'css'      => LWS_WOOVIRTUALWALLET_CSS . '/templates/history.css',
										'subids'   => array(
											'lws_woovirtualwallet_history_widget_header' => "WooVirtualWallet - history Widget - Header",
										),
										'help'    => sprintf(
											__("Use the shortcode %s to show the current customer virtual wallet history", 'woovirtualwallet-lite'),
											"<span class='lws-group-descr-copy lws_ui_value_copy'><div class='lws-group-descr-copy-text content' tabindex='0'>[wallet_history]</div><div class='lws-group-descr-copy-icon lws-icon lws-icon-copy copy'></div></span>"
										),
									)
								),
							)
						),
					)
				),
				'shortcodes' => array(
					'id'     => 'shortcodes',
					'title'  => __("Shortcodes", 'woovirtualwallet-lite'),
					'icon'	=> 'lws-icon-shortcode',
					'groups' => array(
						'shortcodes' => array(
							'id'     => 'shortcodes',
							'title'  => __("Shortcodes", 'woovirtualwallet-lite'),
							'icon'	=> 'lws-icon-shortcode',
							'text'	=> __("In this section, you will find various shortcodes you can use on your website.", 'woovirtualwallet-lite'),
							'fields' => array(
								'simplebalance' => array(
									'id' => 'lws_woovirtualwallet_sc_simple_balance',
									'title' => __("Simple Wallet Balance", 'woovirtualwallet-lite'),
									'type' => 'shortcode',
									'extra' => array(
										'shortcode' => '[wallet_simple_balance raw="true"]',
										'description' =>  __("This simple shortcode is used to display the current user's wallet balance with no decoration.", 'woovirtualwallet-lite') . "<br/>" .
										__("This is very convenient if you want to display the balance within a phrase for example.", 'woovirtualwallet-lite'),
										'options'   => array(
											array(
												'option' => 'raw',
												'desc' => __("(Optional) If set, the result will be a simple text. If not, the result will be embedded in a stylable DOM element", 'woovirtualwallet-lite'),
											),
										),
									)
								),
								'balance' => array(
									'id' => 'lws_woovirtualwallet_sc_balance',
									'title' => __("Wallet Balance", 'woovirtualwallet-lite'),
									'type' => 'shortcode',
									'extra' => array(
										'shortcode' => '[wallet_balance header="Your Wallet Balance"]',
										'description' =>  __("This shortcode is used to display the current user's wallet balance.", 'woovirtualwallet-lite') . "<br/>" .
										__("It is equivalent to the balance widget.", 'woovirtualwallet-lite'),
										'options'   => array(
											array(
												'option' => 'header',
												'desc' => __("(Optional) If set, it will change the default header above the balance", 'woovirtualwallet-lite'),
											),
										),
									)
								),
								'history' => array(
									'id' => 'lws_woovirtualwallet_sc_history',
									'title' => __("Wallet History", 'woovirtualwallet-lite'),
									'type' => 'shortcode',
									'extra' => array(
										'shortcode' => '[wallet_history header="Last operations on your Virtual Wallet"]',
										'description' =>  __("This simple shortcode is used to display the current user's wallet history.", 'woovirtualwallet-lite') . "<br/>" .
											__("It is equivalent to the history widget.", 'woovirtualwallet-lite'),
										'options'   => array(
											array(
												'option' => 'header',
												'desc' => __("(Optional) If set, it will change the default header above the history", 'woovirtualwallet-lite'),
											),
										),
									)
								),
							)
						),
					)
				)
			),
		);
		return $appearancePage;
	}

	protected function getGatewayPage()
	{
		$description = array(
			__("Set up the wallet payment gateway with lots of different options:", 'woovirtualwallet-lite'),
			__("Enable/Disable the gateway", 'woovirtualwallet-lite'),
			__("Edit the gateway's title and description", 'woovirtualwallet-lite'),
			__("Edit the gateway's instructions and logged off message", 'woovirtualwallet-lite'),
			__("Show the customer's wallet balance or not", 'woovirtualwallet-lite'),
		);
		$gatewayPage = array(
			'id' => LWS_WOOVIRTUALWALLET_PAGE . '-gateway',
			'rights'    => 'edit_virtual_wallet_options',
			'title'     => __("Gateway", 'woovirtualwallet-lite'),
			'color' => '#00b1a7',
			'image'		=> LWS_WOOVIRTUALWALLET_IMG . '/r-gateway.png',
			'description' => "<p>" . $description[0] . "</p><ul>" .
			"<li><span>" . $description[1] . "</span></li><li><span>" . $description[2] . "</span></li><li><span>" . $description[3] . "</span></li>" .
			"<li><span>" . $description[4] . "</span></li></ul>",
		);
		return $gatewayPage;
	}


	protected function getPurchaseGroup()
	{
		$group = array(
			'id'	=> 'purchase',
			'title'	=> __("Wallet Rules", 'woovirtualwallet-lite'),
			'icon'	=> 'lws-icon-settings-gear',
			'text'	=> __("Set the general settings and behavior of the virtual wallet", 'woovirtualwallet-lite'),
			'fields' => array(
				'order_state' => array(
					'id'    => 'lws_woovirtualwallet_order_events_on_completed_status_only',
					'title' => __("Add purchased wallet money on 'Complete' order only", 'woovirtualwallet-lite'),
					'type'  => 'box',
					'extra' => array(
						'class' => 'lws_checkbox',
						'help' => __("Edit a product to give wallet money to a customer purchasing it. Default order status to apply it is 'processing'.", 'woovirtualwallet-lite')
						. '<br/>'
						. __("If you want to use the 'completed' order status instead (recommanded), check the below box", 'woovirtualwallet-lite')
					)
				),
			)
		);

		if( \LWS_WooVirtualWallet::isModeWallet() )
		{
			$group['fields']['forbids'] = array(
				'id'    => 'lws_woovirtualwallet_forbids_bought_wallet_with_wallet',
				'title' => __("Wallet credit can't be bought with the wallet", 'woovirtualwallet-lite'),
				'type'  => 'box',
				'extra' => array(
					'class' => 'lws_checkbox',
					'default'  => 'on',
					'tooltips' => __("The <i>Virtual Wallet</i> payment method cannot be used if the customer's order contains a product that feeds the <i>Virtual Wallet</i>.", 'woovirtualwallet-lite'),
				)
			);
		}

		return $group;
	}

	public function getSystemPage()
	{
		$description = array(
			__("Manage your license and different system related options :", 'woovirtualwallet-lite'),
			__("License :", 'woovirtualwallet-lite'),
			__("Manage your license and subscription", 'woovirtualwallet-lite'),
		);
		$description = <<<EOT
<p>{$description[0]}</p>
<ul>
	<li><span><strong>{$description[1]}</strong> {$description[2]}</span></li>
</ul>
EOT;

		$systemPage = array(
			'title'    		=> __("System", 'woovirtualwallet-lite'),
			'subtitle' 		=> __("System", 'woovirtualwallet-lite'),
			'id'       		=> LWS_WOOVIRTUALWALLET_PAGE . '-system',
			'rights'   		=> 'edit_virtual_wallet_options',
			'color'			=> '#7958A5',
			'image'			=> LWS_WOOVIRTUALWALLET_IMG . '/r-system.png',
			'description'	=> $description,
			'tabs' => array()
		);

		if( LWS_WOOVIRTUALWALLET_PAGE . '-system' == $this->getCurrentPage() )
		{
			$systemPage['tabs'] = array(
				'data_management' => array(
					'id'     => 'data_management',
					'title'  => __("Data Management", 'woovirtualwallet-lite'),
					'icon'   => 'lws-icon-components',
					'groups' => array(
						'admin' => array(
							'id'    => 'admin',
							'title' => __("Administration", 'woovirtualwallet-lite'),
							'icon'	=> 'lws-icon-a-time',
							'fields'=> array(
								'period' => array(
									'id'    => 'lws_woovirtualwallet_admin_users_popup_period',
									'title' => __("Displayed period in user history", 'woovirtualwallet-lite'),
									'type'  => 'duration',
									'extra' => array(
										'default' => 'P3M',
									)
								),
							)
						),
						'delete' => array(
							'id'    => 'delete',
							'title' => __("Delete all data", 'woovirtualwallet-lite'),
							'icon'  => 'lws-icon-delete-forever',
							'text'  => __("Remove all Wallets settings and user's credits.", 'woovirtualwallet-lite')
							. '<br/>' . __("Use it with care since this action is <b>not undoable</b>.", 'woovirtualwallet-lite'),
							'fields' => array(
								'trigger_delete' => array(
									'id' => 'trigger_delete_all_woovirtualwallet',
									'title' => __("Delete All Data", 'woovirtualwallet-lite'),
									'type' => 'button',
									'extra' => array(
										'callback' => array($this, 'deleteAllData')
									),
								),
							)
						),
					)
				)
			);
		}

		return $systemPage;
	}

	public function scripts($hook)
	{
		// Force the menu icon with lws-icons font
		\wp_enqueue_style('wvw-menu-icon', LWS_WOOVIRTUALWALLET_CSS . '/menu-icon.css', array(), LWS_WOOVIRTUALWALLET_VERSION);
	}

	protected function insertArray(&$array, $insert, $offset, $preserveKeys=true)
	{
		$array = array_slice($array, 0, $offset, $preserveKeys) + $insert + array_slice($array, $offset, null, $preserveKeys);
	}

	function deleteAllData($btnId, $data=array())
	{
		if( $btnId != 'trigger_delete_all_woovirtualwallet' ) return false;

		if( !(isset($data['del_conf']) && \wp_verify_nonce($data['del_conf'], 'deleteAllData')) )
		{
			$label = __("If you really want to reset all WooVirtualWallet data, check this box and click on <i>'%s'</i> again.", 'woovirtualwallet-lite');
			$label = sprintf($label, __("Delete All Data", 'woovirtualwallet-lite'));
			$warn = __("This operation is not undoable!", 'woovirtualwallet-lite');
			$tips = __("Consider making a backup of your database before continue.", 'woovirtualwallet-lite');

			$nonce = \esc_attr(\wp_create_nonce('deleteAllData'));
			$str = <<<EOT
<p>
	<input type='checkbox' class='lws-ignore-confirm' id='del_conf' name='del_conf' value='{$nonce}' autocomplete='off'>
	<label for='del_conf'>{$label} <b style='color: red;'>{$warn}</b><br/>{$tips}</label>
</p>
EOT;
			return $str;
		}

		$wpInstalling = \wp_installing();
		\wp_installing(true); // should force no cache
		\do_action('lws_woovirtualwallet_before_delete_all', $data);
		error_log("[WooVirtualWallet] Delete everything");

		global $wpdb;
		// clean options
		$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'lws_woovirtualwallet_%'");
		// user meta
		$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'lws_woovirtualwallet_%'");
		// post meta
		$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'lws_woovirtualwallet_%'");
		// clean db tables
		$wpdb->query("DROP TABLE IF EXISTS {$wpdb->base_prefix}lws_wvw_history");

		// mails
		$prefix = 'lws_mail_'.'woovirtualwallet'.'_attribute_';
		delete_option($prefix.'headerpic');
		delete_option($prefix.'footer');

		\update_option('lws_woovirtualwallet_redirect_to_licence', -100);
		\do_action('lws_woovirtualwallet_after_delete_all', $data);
		\wp_installing($wpInstalling);
		return __("WooVirtualWallet install has been cleaned up.", 'woovirtualwallet-lite');
	}
}
