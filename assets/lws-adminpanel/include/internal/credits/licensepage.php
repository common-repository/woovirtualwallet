<?php
namespace LWS\Adminpanel\Internal\Credits;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();
require_once LWS_ADMIN_PANEL_INCLUDES . '/internal/credits/page.php';

/** Add a special page to manage the plugin licenses. */
class LicensePage extends \LWS\Adminpanel\Internal\Credits\Page
{
	function suffixPage(string $page){return $page.'_lic';}
	function getTabId(){return 'lic';}

	/**	@param $targetPage (bool|string|array) if false, create a dedicated page; else insert in specifed page[s]. */
	static function install($file, $adminPageId, $uuid, $def=false, $targetPage=false)
	{
		$me = new self($file, $adminPageId, $targetPage, true);
		$me->setProductId($uuid);

		\add_filter('lws_adm_menu_license_url', array($me, 'getScreenUrl'), 10, 3);
		\add_filter('lws_adm_menu_license_status', array($me, 'getLicenseStatus'), 10, 3);
		/** Get all under license plugins
		 *	@param array */
		\add_filter('lws_adminpanel_licenses_status', array($me, 'filterLicenses'));
		/** Migration
		 *	@param string plugin slug */
		\add_action('lws_adminpanel_licenses_migration', array($me, 'migrateLicense'), 10, 1);
		/** after update, link to admin page */
		\add_filter('install_plugin_complete_actions', array($me, 'afterInstallActions'), 10, 3);
		\add_filter('update_plugin_complete_actions', array($me, 'afterUpdateActions'), 10, 2);

		if( isset($_REQUEST['option_page']) )
		{
			\add_filter('pre_update_option_'.$me->getLicense()->getKeyOption(), array($me, 'preUpdateKey'), 10, 3);
			\add_filter('pre_update_option_'.$me->getActionOption(), array($me, 'preUpdateAction'), 10, 3);
		}

		if( $def )
			define($def, $me->getLicense()->isRunning());
		$me->getLicense()->installUpdater();
	}

	static function installAddon($file, $masterSlug, $addonUuid, $def)
	{
		$me = new self($file, '');
		$me->setProductId($addonUuid);
		\add_filter('lws_adm_addons_'.$masterSlug, array($me, 'listAddons'));

		if( isset($_REQUEST['option_page']) )
		{
			\add_filter('pre_update_option_'.$me->getAddonActionOption(), array($me, 'preUpdateAddonAction'), 10, 3);
		}

		if( $def )
			define($def, $me->getLicense()->isRunning());
		$me->getLicense()->installUpdater();
	}

	function listAddons($list)
	{
		$list[$this->getLicense()->getSlug()] = $this;
		return $list;
	}

	/** always empty the value after usage. */
	function preUpdateAddonAction($value, $old=false, $option=false)
	{
		$license =& $this->getLicense();
		$expected = 'toggle_' . $license->getSlug();
		if( $expected == $value )
		{
			if( $license->isActive() )
			{
				$license->deactivate();
			}
			else
			{
				$opt = $license->getKeyOption();
				$key = isset($_POST[$opt]) ? \sanitize_text_field($_POST[$opt]) : false;
				if( $key )
				{
					if( $license->activate($key, $license->getKey()) )
						$license->updateKey($key);
				}
				else
				{
					\lws_admin_add_notice_once('lws_addon_toggle', __("Please set a the extension license key to activate it."), array('level'=>'error'));
				}
			}
		}
		return '';
	}

	/** always empty the value after usage. */
	function preUpdateAction($value, $old=false, $option=false)
	{
		if( 'deactivate' == $value )
		{
			$this->getLicense()->deactivate();
		}
		else if( 'trial' == $value )
		{
			$this->getLicense()->startTry();
		}
		return '';
	}

	function preUpdateKey($value, $old=false, $option=false)
	{
		$actOpt = $this->getActionOption();
		$action = isset($_POST[$actOpt]) ? !empty($_POST[$actOpt]) : false;
		if( $action )
			return $old;
		$value = \trim($value);
		if( !$this->getLicense()->activate($value, $old) )
			$value = $old;
		return $value;
	}

	function getLicenseStatus($status, $mainId, $pageId)
	{
		if( isset($this->pageQueryArgs) && $this->pageQueryArgs && isset($this->myPages) && $this->myPages )
		{
			if( isset($this->myPages[$pageId]) )
				$status = $this->_getStatus($status);
		}
		return $status;
	}

	function filterLicenses($licenses=array())
	{
		$license =& $this->getLicense();
		$licenses[$license->getSlug()] = $this->_getStatus($license->getPluginInfo());
		return $licenses;
	}

	private function _getStatus($status=array())
	{
		if( !\is_array($status) )
			$status = array();
		$license =& $this->getLicense();

		$status['lite']    = $license->isLite();
		$status['active']  = $license->maybeActive();
		$status['trial']   = !$status['active'] && $license->isTrial();
		$status['expired'] = ($status['active'] ? $license->isPremiumExpired() : $license->isTrialExpired());

		if( $status['trial'] && !$status['expired'] && ($e = $license->getTrialEnding()) )
			$status['soon'] = $e->diff(\date_create(), true)->format('%a');

		$status['subscription'] = $license->getSubscriptionEnd();
		if( $status['subscription'] )
			$status['subscription'] = $status['subscription']->format('Y-m-d');

		$status['trial_available'] = $license->isTrialAvailable();

		return $status;
	}

	protected function getTab()
	{
		$license =& $this->getLicense();
		$groups = array();

		if( $license->isLite() )
		{
			if( $license->isRunning() )
				$groups = $this->addGroupUpdateInfo($groups);

			if( $license->isLiteAvailable() )
				$groups = $this->addGroupFreeVersion($groups);
		}

		if( $license->isActive() )
		{
			$groups = $this->addGroupActivePro($groups);
			if( $license->isSubscription() )
				$groups = $this->addGroupSubscription($groups);
		}
		else
		{
			$groups = $this->addGroupIdleKey($groups);
			$groups = $this->addGroupPurchaisePro($groups);

			if( !$license->isTrial() && $license->isTrialAvailable() )
				$groups = $this->addGroupTeaseTrial($groups);
		}

		$groups = $this->addGroupAddons($groups);
		$groups = $this->addGroupAddonTeasers($groups);

		\ksort($groups);
		$page = array(
			'id'     => $this->getTabId(),
			'title'  => __("License Management", 'lws-adminpanel'),
			'icon'   => 'lws-icon-key',
			'nosave' => true,
			'groups' => $groups,
		);
		return $page;
	}

	private function getAddonLicenseFieldContent($fieldId)
	{
		$license =& $this->getLicense();
		$opt     = \esc_attr($license->getKeyOption());
		$key     = \esc_attr($license->getKey());
		$value   = \esc_attr('toggle_' . $license->getSlug());
		$button  = $license->isActive() ? __("Deactivate my license", 'lws-adminpanel') : __("Activate license", 'lws-adminpanel');

		$content = <<<EOT
<div class='lws-addon-license-field'>
	<input lass='lws-input' size='30' type='text' value='{$key}' name='{$opt}'>
	<button type='submit' class='lws-button-link' value='{$value}' name='{$fieldId}'>$button</button>
</div>
EOT;
		return $content;
	}

	private function addGroupAddons($groups)
	{
		$license =& $this->getLicense();
		$addons = \apply_filters('lws_adm_addons_'.$license->getSlug(), array());
		if( $addons )
		{
			$text = array(
				__("The following extensions have been found on your system.", 'lws-adminpanel'),
				__("Here you manage the license keys for each one of them.", 'lws-adminpanel'),
			);
			if( !$license->isActive() )
				$text[] = sprintf(__("The Premium version of %s must be active for extensions to work.", 'lws-adminpanel'), $license->getName());

			$fields = array();
			foreach($addons as $slug => $addon)
			{
				$fieldId = $this->getAddonActionOption();
				$fields[$slug] = array(
					'id'    => $fieldId,
					'type'  => 'custom',
					'title' => $addon->getLicense()->getName(),
					'extra' => array(
						'content' => $addon->getAddonLicenseFieldContent($fieldId),
					),
				);
			}

			$groups['50.addons'] = array(
				'id'     => 'addons',
				'icon'   => 'lws-icon-books',
				'title'  => __("Installed Extensions", 'lws-adminpanel'),
				'color'  => '#336666',
				'class'  => 'onecol',
				'text'  => sprintf('<p>%s</p>', implode('</p><p>', $text)),
				'fields' 	=> $fields,
			);
		}
		return $groups;
	}

	private function addGroupAddonTeasers($groups)
	{
		$license =& $this->getLicense();
		$teasers = \apply_filters('lws_adm_teasers_'.$license->getSlug(), array(), $this->_getStatus($license->getPluginInfo()));
		if( $teasers )
		{
			$text = array(
				__("Make your site even greater.", 'lws-adminpanel'),
				sprintf('<a href="%s" target="_blank">%s</a>', \esc_attr($license->getRemoteUrl()), __("All these extensions are available here.", 'lws-adminpanel')),
			);
			if( !$this->getLicense()->isActive() )
				$text[] = __("Addons need an active Premium version.", 'lws-adminpanel');

			$fields = array();
			foreach($teasers as $k => $content)
			{
				$fields[$k] = array(
					'id'    => 'teaser_'.$k,
					'type'  => 'custom',
					'extra' => array(
						'gizmo'   => true,
						'content' => $content,
					),
				);
			}

			$groups['90.teasers'] = array(
				'id'     => 'teasers',
				'icon'   => 'lws-icon-show-more',
				'title'  => __("Available Extensions", 'lws-adminpanel'),
				'color'  => '#018A37',
				'class'  => 'onecol',
				'text'  => sprintf('<p>%s</p>', implode('</p><p>', $text)),
				'fields' 	=> $fields,
			);
		}
		return $groups;
	}

	/// current is activated but still only lite code exists
	private function addGroupUpdateInfo($groups)
	{
		$license = & $this->getLicense();
		$license->clearUpdateTransient();

		$url = \esc_attr(\add_query_arg('force-check', '1', \admin_url('update-core.php')));
		$link = sprintf('<a href="%s">%s</a>', $url, __("WordPress Updates")); // use WP translation
		$name = sprintf('<b>%s</b>', $license->getName());
		$text = array(
			__('<b>Your license is activated.</b>', 'lws-adminpanel'),
			sprintf(__('Look in %1$s page for a %2$s update.', 'lws-adminpanel'), $link,$name),
			__('You should have to click "<i>Check Again</i>" button to force WordPress refresh its list.', 'lws-adminpanel'),
			__('If the Plugin Update still does not appears, please wait few minutes and try again.', 'lws-adminpanel'),
		);
		if( !$license->isActive() )
			$text[0] = __('<b>Your Trial is active.</b>', 'lws-adminpanel');

		$content = sprintf("<a class='lws-button-link' href='%s' target='_blank'>%s</a>", $url, __("Check for Updates", 'lws-adminpanel'));

		$groups['00.update'] = array(
			'id'     => 'update',
			'icon'   => 'lws-icon-settings-gear',
			'title'  => __("An update is waiting for you", 'lws-adminpanel'),
			'color'  => '#a4489a',
			'class'  => 'onecol',
			'text'  => sprintf('<p>%s</p>', implode('</p><p>', $text)),
			'fields' 	=> array(
				'custom' 	=> array(
					'id'    => 'custom',
					'type'  => 'custom',
					'extra' => array(
						'gizmo'   => true,
						'content' => $content,
					),
				),
			)
		);
		return $groups;
	}

	/// current is pro, tell about maintenance
	private function addGroupSubscription($groups)
	{
		$license = & $this->getLicense();
		$expired = !$license->isSubscriptionActive();
		$link = $license->getRemoteMyAccountURL();
		$zomb = $license->isZombie();

		$text = array();
		if( $expired )
		{
			$text[] = __("Your Subscription is currently inactive.", 'lws-adminpanel');
			if( $zomb )
				$text[] = __("You can't download new versions or send requests to the support.", 'lws-adminpanel');
			else
				$text[] = __("You can't use Premium features of this plugin, download new versions or send requests to the support.", 'lws-adminpanel');
			$text[] = __("You can resume your subscription at any time to get access to updates and support.", 'lws-adminpanel');
			$content = sprintf("<a class='lws-button-link' href='%s' target='_blank'>%s</a>", \esc_attr($link), __("Resume my subscription", 'lws-adminpanel'));
		}
		else
		{
			$text[] = __("Your Subscription is currently active.", 'lws-adminpanel');
			$ending = $license->getSubscriptionEnd();
			if( $ending )
			{
				if( $zomb )
					$text[] = __("Updates and Support are available until :", 'lws-adminpanel');
				else
					$text[] = __("To avoid a service interruption, you should renew your subscription before :", 'lws-adminpanel');
				$content  = sprintf("<div class='lws-license-big-text'>%s</div>", \date_i18n(\get_option('date_format'), $ending->getTimestamp()));
				$content .= sprintf("<a class='lws-button-link' href='%s' target='_blank'>%s</a>", \esc_attr($link), __("See my subscription", 'lws-adminpanel'));
			}
			else
			{
				if( $zomb )
					$text[] = __("Your Subscription includes Updates and Support.", 'lws-adminpanel');
				else
					$text[] = __("Your Subscription includes the usage of this plugin, updates and support.", 'lws-adminpanel');
				$content = sprintf("<a class='lws-button-link' href='%s' target='_blank'>%s</a>", \esc_attr($link), __("Cancel my subscription", 'lws-adminpanel'));
			}
		}

		if( $zomb )
			$title = __("Maintenance and Updates", 'lws-adminpanel');
		else
			$title = __("Usage Permission", 'lws-adminpanel');
		$groups['12.subscript'] = array(
			'id'		=> 'subscript',
			'icon'		=> 'lws-icon-version',
			'title' 	=> $title,
			'color' 	=> $expired ? '#cc1d25' : '#018A37',
			'class'		=> 'half',
			'text'  	=> implode('</br>', $text),
			'fields' 	=> array(
				'custom' 	=> array(
					'id'    => 'custom',
					'type'  => 'custom',
					'extra' => array(
						'gizmo'   => true,
						'content' => $content,
					),
				),
			)
		);
		return $groups;
	}

	/// current was trial, but since expired, the free remains, hope for a pro
	private function addGroupIdleKey($groups)
	{
		$license = & $this->getLicense();
		$text = array(
			__("If you have a Premium License Key, please input it in the field below and click on the Activation button.", 'lws-adminpanel'),
			__("If the activation is successful, the Premium version will be activated and installed automatically.", 'lws-adminpanel'),
		);

		$content = sprintf(
			"<button type='submit' class='lws-button-link'>%s</button>",
			__("Activate my license", 'lws-adminpanel')
		);

		$groups['31.idle'] = array(
			'id'     => 'idle',
			'icon'   => 'lws-icon-key',
			'title'  => sprintf(__("%s Premium License", 'lws-adminpanel'), $license->getName()),
			'color'  => '#336666',
			'class'  => 'half',
			'text'   => implode('</br>', $text),
			'fields' => array(
				'key' => array(
					'id'    => $license->getKeyOption(),
					'type'  => 'text',
					'title' => __('License Key', 'lws-adminpanel'),
					'extra' => array(
						'size' => '30',
						'placeholder' => 'XX-XXXX-XXXX-XXXX-XXXX',
						'help' => __("Your license key has been provided to you in the Order Confirmation eMail.", 'lws-adminpanel'),
					)
				),
				'custom' => array(
					'id'    => 'custom',
					'type'  => 'custom',
					'extra' => array(
						'gizmo'   => true,
						'content' => $content,
					)
				),
			)
		);
		return $groups;
	}

	private function getAddonActionOption()
	{
		return ('lws-addon-action-'.$this->getLicense()->getSlug());
	}

	private function getActionOption()
	{
		return ('lws-license-action-'.$this->getLicense()->getSlug());
	}

	/// current activated pro
	private function addGroupActivePro($groups)
	{
		$license = & $this->getLicense();
		$text = array(
			sprintf(__("You're actually using a licensed version of %s Premium.", 'lws-adminpanel'), $license->getName()),
			__("Your license key is :", 'lws-adminpanel'),
		);

		$content = sprintf("<div class='lws-license-big-text'>%s</div>", $license->getKey());
		$content .= sprintf(
			"<button type='submit' name='%s' value='deactivate' class='lws-button-link'>%s</button>",
			$this->getActionOption(),
			__("Deactivate my license", 'lws-adminpanel')
		);

		$details = sprintf(__("If you deactivate your license, you will be reverted to the free version of %s. Your license count will be updated on our server and you'll be able to activate your license on another website.", 'lws-adminpanel'), $license->getName());
		$content .= "<div class='lws-license-small-text'>{$details}</div>";

		$groups['11.pro'] = array(
			'id'     => 'pro',
			'icon'   => 'lws-icon-key',
			'title'  => sprintf(__("%s Premium License", 'lws-adminpanel'), $license->getName()),
			'color'  => '#336666',
			'class'  => 'half',
			'text'   => implode('</br>', $text),
			'fields' => array(
				'custom' => array(
					'id'    => $this->getActionOption(),
					'type'  => 'custom',
					'extra' => array(
						'content' => $content,
					),
				),
			)
		);
		return $groups;
	}

	/// current is free version
	private function addGroupFreeVersion($groups)
	{
		$license = & $this->getLicense();
		$teaser = \apply_filters('lws_adm_license_trial_teaser_texts', __("This version contains only a few features of the premium version.", 'lws-adminpanel'), $license->getSlug());
		$text = array(
			sprintf(__("You're actually using the free version of %s.", 'lws-adminpanel'), $license->getName()),
			$teaser,
			sprintf(__("If you're happy with %s Standard features, <b>please consider reviewing it</b> on wordpress.org", 'lws-adminpanel'), $license->getName()),
		);

		if( $license->isTrialAvailable() )
			$text[] = __("If you want to gain access to more features, you can try the premium version for free for 30 days.", 'lws-adminpanel');

		$content = sprintf(
			"<a class='lws-button-link' href='%s' target='_blank'>%s</a>",
			\esc_attr(sprintf('https://wordpress.org/support/plugin/%s/reviews/#new-post', $license->getSlug())),
			__("Review on wordpress.org", 'lws-adminpanel')
		);

		$groups['21.free'] = array(
			'id'     => 'free',
			'icon'   => 'lws-icon-free',
			'title'  => __("Free Version", 'lws-adminpanel'),
			'color'  => '#016087',
			'class'  => 'half',
			'text'   => implode('</br>', $text),
			'fields' => array(
				'custom' 	=> array(
					'id'    => 'custom',
					'type'  => 'custom',
					'extra' => array(
						'gizmo'   => true,
						'content' => $content,
					),
				),
			)
		);
		return $groups;
	}

	/// current is free version, never try more
	private function addGroupTeaseTrial($groups)
	{
		$license = & $this->getLicense();
		$teaser = \apply_filters('lws_adm_license_trial_teaser_texts', __("This version contains only a few features of the premium version.", 'lws-adminpanel'), $license->getSlug());
		$text = sprintf(
			'%s</br>%s<ul><b><li>%s</li><li>%s</li><li>%s</li></b></ul>',
			$teaser,
			sprintf(__("Try %s Premium for free for 30 days.", 'lws-adminpanel'), $license->getName()),
			sprintf(__("Instant access to all %s Premium features.", 'lws-adminpanel'), $license->getName()),
			__("No payment required.", 'lws-adminpanel'),
			__("No registration required.", 'lws-adminpanel')
		);

		$content = sprintf(
			"<button type='submit' name='%s' value='trial' class='lws-button-link'>%s</button>",
			$this->getActionOption(),
			__("Start the free trial", 'lws-adminpanel')
		);

		$groups['32.lite2trial'] = array(
			'id'     => 'lite2trial',
			'icon'   => 'lws-icon-free',
			'title'  => sprintf(__("Free %s Premium Trial", 'lws-adminpanel'), $license->getName()),
			'color'  => '#018A07',
			'class'  => 'half',
			'text'   => $text,
			'fields' => array(
				'custom' 	=> array(
					'id'    => $this->getActionOption(),
					'type'  => 'custom',
					'extra' => array(
						'content' => $content,
					)
				),
			)
		);
		return $groups;
	}

	/// current is trial version
	private function addGroupPurchaisePro($groups)
	{
		$license = & $this->getLicense();
		$text = array();
		$content = '';

		if( $t = $license->isTrial() )
			$text[] = sprintf(__("You're actually trying %s Premium for free for a limited period of time. You will be reminded that your trial period is about to end 5 days and 3 days before it ends.", 'lws-adminpanel'), $license->getName());
		else
			$text[] = sprintf(__("You're actually trying %s Free with limited features.", 'lws-adminpanel'), $license->getName());
		$text[] = sprintf(__("If you like %s, you can purchase a license on our website by clicking on the button below.", 'lws-adminpanel'), $license->getName());

		if( $t && $ending = $license->getTrialEnding() )
		{
			$diff = $ending->diff(\date_create(), true)->format('%a');
			$text[] = '';
			$text[] = __("Your trial will expire in :", 'lws-adminpanel');
			$remainings = sprintf("%d %s", $diff, __("Days", 'lws-adminpanel'));
			$content = "<div class='lws-license-big-text'>{$remainings}</div>";
		}

		$content .= sprintf(
			"<a class='lws-button-link' href='%s' target='_blank'>%s</a>",
			\esc_attr(\apply_filters('lws_adm_license_product_page_url', $license->getPluginURI(), $license->getSlug())),
			sprintf(__("Purchase %s Premium", 'lws-adminpanel'), $license->getName())
		);

		$details = __("Premium Version is a paid service. This service features the plugin, the support and regular updates. You can cancel your subscription at any time by changing your preferences on your account on plugins.longwatchstudio.com. Cancelling your subscription will remove the access to premium features, support and updates.", 'lws-adminpanel');
		$content .= "<div class='lws-license-small-text'>{$details}</div>";

		$groups['22.trial2pro'] = array(
			'id'		=> 'trial2pro',
			'icon'		=> 'lws-icon-free',
			'title' 	=> sprintf(__("Purchase %s Premium", 'lws-adminpanel'), $license->getName()),
			'color' 	=> '#018A07',
			'class'		=> 'half',
			'text'  	=> implode('</br>', $text),
			'fields' 	=> array(
				'custom' 	=> array(
					'id'    => 'custom',
					'type'  => 'custom',
					'extra' => array(
						'gizmo'   => true,
						'content' => $content,
					)
				),
			)
		);
		return $groups;
	}

	private function setProductId($uuid)
	{
		$this->uuid = $uuid;
	}

	private function &getLicense()
	{
		if( !isset($this->license) ){
			require_once dirname(__FILE__).'/license.php';
			$this->license = new \LWS\Adminpanel\Internal\Credits\License($this->file, $this->uuid);
		}
		return $this->license;
	}

	function migrateLicense($slug)
	{
		$license =& $this->getLicense();
		if( $license->getSlug() == $slug )
		{
			$token = $license->getKey();
			if( !$token )
				return;
			if( $license->isRunning() )
				return;

			$ending = \get_site_option('lws-license-end-'.$slug);
			if( $ending )
			{
				$ending = \date_create($ending);
				if( !$ending || $ending < \date_create()->setTime(0,0) )
					return;
				if( !$license->isTrialAvailable() )
					return;

				error_log("Go migrate license to trial $slug for key: ".$license->getKey());
				if( $license->startTry(false, $ending) )
				{
					error_log(sprintf("Trial for %s with key [%s] succeed.", $slug, $license->getKey()));
				}
				else
				{
					\lws_admin_add_notice(
						'lws_lic_udt_error_'.$slug,
						implode('<br/>', array(
							sprintf(__("The Trial License found for the plugin <b>%s</b> cannot be migrated to the new system.", 'lws-adminpanel'), $license->getName()),
							__("If you are sure your license should still be valid, try to restart the trial manually or contact the support of the plugin.", 'lws-adminpanel'),
						)),
						array('level'=>'error')
					);
				}
			}
			else
			{
				error_log("Go migrate license to pro $slug for key: ".$license->getKey());
				if( $license->activate($token, false, false, '4') )
				{
					error_log(sprintf("License for %s with key [%s] succeed.", $slug, $license->getKey()));
				}
				else
				{
					\lws_admin_add_notice(
						'lws_lic_udt_error_'.$slug,
						implode('<br/>', array(
							sprintf(__("The license found for the plugin <b>%s</b> cannot be migrated to the new system.", 'lws-adminpanel'), $license->getName()),
							__("If you are sure your license should still be valid, try to reactivate it manually or contact the support of the plugin.", 'lws-adminpanel'),
						)),
						array('level'=>'error')
					);
				}
			}
		}
	}

	/** Add backlink to plugin license page after plugin install.
	 *	In addition to 'Go to Plugins page' */
	function afterInstallActions($actions, $api, $plugin)
	{
		$license =& $this->getLicense();
		if( $license->getBasename() == $plugin )
		{
			$args = false;
			if( isset($this->pageQueryArgs) && $this->pageQueryArgs )
				$args = $this->pageQueryArgs;
			if( !$args && isset($this->myPages) && $this->myPages )
				$args = array('page' => \reset($this->myPages));

			if( $args )
			{
				$actions['go_to_lws'] = sprintf(
					'<a href="%s" target="_parent">%s</a>',
					\add_query_arg($args, \admin_url('admin.php')),
					sprintf(__('Go to <b>%s</b> Settings', 'lws-adminpanel'), $license->getName())
				);
			}
		}
		return $actions;
	}

	/** Add backlink to plugin main admin page after plugin update.
	 *	In addition to 'Go to Plugins page' */
	function afterUpdateActions($actions, $plugin)
	{
		$license =& $this->getLicense();
		if( $license->getBasename() == $plugin )
		{
			$url = $this->getMainScreenUrl();
			if( $url )
			{
				$actions['go_to_lws'] = sprintf(
					'<a href="%s" target="_parent">%s</a>',
					$url,
					sprintf(__('Go to <b>%s</b> Settings', 'lws-adminpanel'), $license->getName())
				);
			}
		}
		return $actions;
	}
}
