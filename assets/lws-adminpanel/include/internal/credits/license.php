<?php
namespace LWS\Adminpanel\Internal\Credits;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();


/** License helper. */
class License
{
	const API_VERSION = '1.1'; //use latest available API
	const CHECK_INTERVAL = 'P1DT6H';
	private $trialWarnings = array(5, 3);

	function __construct($file, $uuid)
	{
		$this->file = $file;
		$this->uuid = $uuid;
	}

	private function getSiteUrl()
	{
		$url = (defined('WP_SITEURL') && WP_SITEURL) ? WP_SITEURL : \get_option('siteurl');
		return \preg_replace('@^https?://@i', '', $url);
	}

	function getRemoteUrl($path='')
	{
		$url = 'https://plugins.longwatchstudio.com/';
		if( defined('LWS_DEV') && LWS_DEV )
			$url = \is_string(LWS_DEV) ? LWS_DEV : \site_url();

		if( $path && \is_string($path) )
			$url = (\rtrim($url, '/') . '/' . \ltrim($path, '/'));

		$url = \add_query_arg(array('lang'=>\get_locale()), $url);
		return $url;
	}

	function isRunning()
	{
		if( $this->isActive() )
		{
			if( $this->isZombie() )
				return true;
			else
				return !$this->isSubscription() || $this->isSubscriptionActive();
		}
		else
			return $this->isTrial();
	}

	/// true premium
	function isActive()
	{
		if( !($key = $this->getKey()) ) return false;
		if( !($value = $this->getGlobalOption($this->getId(), '')) ) return false;
		if( !($value = $this->recurringCheck($value)) ) return false;
		return !$this->isExpired($value, true);
	}

	function maybeActive()
	{
		if( !($key = $this->getKey()) ) return false;
		if( !($value = $this->getGlobalOption($this->getId(), '')) ) return false;
		return true;
	}

	private function getActionKey()
	{
		$k = 'woo_sl_action';
		if( !$this->maybeActive() && $this->isTrial() )
			$k = 'lwswcsl_action';
		return $k;
	}

	function isPremiumExpired()
	{
		return $this->isExpired($this->getGlobalOption($this->getId(), ''), true);
	}

	/** If trial, expiration leads to free version.
	 *	If pro, expiration deny new updates */
	function isExpired($value, $lastChance=false)
	{
		if( !$value )
			return true;
		if( 'inf' == $value )
			return false;
		if( !\is_numeric($value) )
			return true;
		if( $d = \date_create()->setTimestamp($value)->setTime(0,0) )
		{
			if( \date_create()->setTime(0,0) <= $d )
				return false;
			elseif( $lastChance )
			{
				$ts = $d->getTimestamp();
				if( $this->getGlobalOption($this->getId('lwslastchance_')) != $ts )
				{
					$this->updateGlobalOption($this->getId('lwslastchance_'), $ts);
					return !$this->check(false);
				}
			}
		}
		return true;
	}

	/** Is the Pro/Trial version installed or only the free one.
	 *	Don't care about activation or not. */
	function isLite()
	{
		if( !isset($this->lite) )
		{
			$this->lite = !\apply_filters('lws-ap-release-'.$this->getSlug(), '');
		}
		return $this->lite;
	}

	/** Only if downloaded from WordPress and trial exists but never started */
	function isTrialAvailable()
	{
		if( !$this->isLite() )
			return false;
		if( $this->isTrialConsumed() )
			return false;
		return \apply_filters('lws_adm_license_trial_version_exists', true, $this->getSlug());
	}

	function isLiteAvailable()
	{
		return \apply_filters('lws_adm_license_free_version_exists', true, $this->getSlug());
	}

	function isSubscriptionActive()
	{
		$support = $this->getGlobalOption($this->getId('lwssupport_'));
		if( !$support )
			return false;
		else if( \is_numeric($support) )
			return \time() <= $support;
		else
			return \in_array($support, array('active', 'pending-cancel'));
	}

	/** @return false|DateTime */
	function getSubscriptionEnd()
	{
		$support = $this->getGlobalOption($this->getId('lwssupport_'));
		if( $support && \is_numeric($support) )
			return \date_create()->setTimestamp($support);
		return false;
	}

	function isZombie()
	{
		return ('on' == $this->getGlobalOption($this->getId('lwszombie_'), ''));
	}

	function isSubscription()
	{
		return \boolval($this->getGlobalOption($this->getId('lwssupport_'), ''));
	}

	function isTrial()
	{
		if( !($value = $this->getGlobalOption($this->getId('lwstrial_'), '')) ) return false;
		return !$this->isExpired($value);
	}

	function isTrialExpired()
	{
		$trial = $this->getGlobalOption($this->getId('lwstrial_'), false);
		if( false === $trial )
			return false;
		return $this->isExpired($trial);
	}

	function isTrialConsumed()
	{
		if( false !== $this->getGlobalOption($this->getId('lwstrial_'), false) )
			return true;
		if( false !== $this->getGlobalOption($this->getId(), false) )
			return true;
		return false;
	}

	/** @return false or DateTime instance */
	function getTrialEnding()
	{
		$ts = $this->getGlobalOption($this->getId('lwstrial_'), 0);
		if( $ts && \is_numeric($ts) )
			return \date_create()->setTimestamp($ts);
		return false;
	}

	function startTry($update=true, $forceDate=false)
	{
		$this->ignoreSavingConfirmation();
		if( $this->isTrialConsumed() )
		{
			$this->notice(__("Seems like your trial period has been already consumed.", 'lws-adminpanel'));
			return false;
		}

		$args = array(
			'lwswcsl_action'     => 'activate',
			'product_unique_id' => $this->uuid,
			'domain'            => $this->getSiteUrl(),
		);
		$requestUri = \add_query_arg($args, $this->getRemoteUrl());
		$data       = \wp_remote_get($requestUri);

		if( \is_wp_error($data) || !\in_array(\intval($data['response']['code']), array(200, 301, 302)) )
		{
			$this->notice(__("There was a problem establishing a connection to the trial server.", 'lws-adminpanel'));
			return false;
		}

		$dataBody = \json_decode($data['body']);
		$this->log('starttry', $dataBody);
		if( \is_array($dataBody) )
			$dataBody = \end($dataBody);
		if( \is_object($dataBody) && isset($dataBody->status) )
		{
			/// s100 first time key activation
			/// s101 key already activated for domain
			/// s205 key is active and valid for domain
			if( $dataBody->status == 'success' && \in_array($dataBody->status_code, array('s100', 's101', 's205')) && isset($dataBody->trial_status, $dataBody->trial_expire) )
			{
				$txt = array(__("Update to the premium Trial is now available.", 'lws-adminpanel'));
				if( isset($dataBody->message) )
					$txt[] = sprintf('<div class="lws-license-small-text">%s</div>', $this->serverMessage($dataBody->message, $dataBody->status_code));
				$this->notice(implode('</br>', $txt), 'success');

				$d = \absint($forceDate ? $forceDate->getTimestamp() : $dataBody->trial_expire);
				$e = \date_i18n(\get_option('date_format'), $d);
				if( $d > \time() ){
					$notice = \apply_filters('lws_adm_trialstart_msg', '', $this->getSlug(), $e, $d);
					if (!$notice)
						$notice = sprintf(__('The Trial for <i>%2$s</i> will expire the <b>%1$s</b>.', 'lws-adminpanel'), $e, $this->getName());
					$this->notice($notice, 'warning', '-e', false);
				}
				else
					$this->notice(sprintf(__('The Trial for <i>%2$s</i> already expired the <b>%1$s</b>.', 'lws-adminpanel'), $e, $this->getName()), 'error', '-e');

				$this->updateGlobalOption($this->getId('lwstrial_'), $d ? $d : 0);

				if( $update && $this->isLite() )
				{
					// page will be redirected after option saved, go to update if required
					\add_filter('wp_redirect', array($this, 'redirectToUpdate'), 50, 2);
				}
				return true;
			}
			else
			{
				$txt = array(__("There was a problem activating the Trial. You may retry later.", 'lws-adminpanel'));
				if( isset($dataBody->message) )
					$txt[] = $this->serverMessage($dataBody->message, $dataBody->status_code);
				$this->notice(implode('</br>', $txt));
			}
		}
		else
		{
			$this->notice(__("There was a problem establishing a connection to the license service.", 'lws-adminpanel'));
		}

		return false;
	}

	/** @return false or DateTime instance */
	function getLicenseEnding($asTimestamp=false)
	{
		$ts = $this->getGlobalOption($this->getId(), '');
		if( \is_numeric($ts) )
		{
			$d = \date_create()->setTimestamp($ts);
			if( $d )
				return $asTimestamp ? $d->getTimestamp() : $d;
		}
		return false;
	}

	function getKeyOption()
	{
		return 'lws-license-key-' . $this->getSlug();
	}

	function updateGlobalOption($name, $value)
	{
		return \update_network_option(\get_main_network_id(), $name, $value);
	}

	function getGlobalOption($name, $default=false)
	{
		return \get_network_option(\get_main_network_id(), $name ,$default);
	}

	function getKey()
	{
		$option = $this->getKeyOption();
		$key = $this->getGlobalOption($option);
		if( !$key && \is_multisite() )
		{
			global $wpdb;
			$key = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM $wpdb->options WHERE option_name=%s", $option));
			if( $key )
				$this->updateGlobalOption($option, $key);
		}
		return $key;
	}

	function updateKey($value)
	{
		return $this->updateGlobalOption($this->getKeyOption(), $value);
	}

	function getName()
	{
		$name = $this->getPluginInfo()['Name'];
		if( !$name )
			$name = $this->getSlug();
		return $name;
	}

	function getPluginURI()
	{
		$uri = $this->getPluginInfo()['PluginURI'];
		if( !$uri )
			$uri = $this->getPluginInfo()['AuthorURI'];
		return $uri;
	}

	function getPluginVersion()
	{
		return $this->getPluginInfo()['Version'];
	}

	function getPluginAuthor()
	{
		return $this->getPluginInfo()['Author'];
	}

	function getBasename()
	{
		if( !isset($this->basename) )
		{
			$this->basename = \plugin_basename($this->file);
		}
		return $this->basename;
	}

	function getSlug()
	{
		if( !isset($this->slug) )
		{
			$this->slug = \strtolower(\basename($this->getBasename(), '.php'));
		}
		return $this->slug;
	}

	function getPluginInfo()
	{
		if( !isset($this->plugin) )
		{
			if( !\function_exists('\get_plugin_data') )
				require_once(ABSPATH . 'wp-admin/includes/plugin.php');

			$this->plugin = \get_plugin_data($this->file, false);
			$this->plugin = array_merge(array(
				'Name'      => '',
				'Version'   => '',
				'Author'    => '',
				'AuthorURI' => '',
				'PluginURI' => '',
			), $this->plugin);
		}
		return $this->plugin;
	}

	private function getMinifiedSlug($slug)
	{
		if( !isset($this->minifiedSlug) )
		{
			$this->minifiedSlug = '';
			$l = strlen($slug);
			for( $i=0 ; $i<$l ; $i+=3 )
				$this->minifiedSlug .= $slug[$i];
		}
		return $this->minifiedSlug;
	}

	private function getId($prefix='lwslic_')
	{
		$slug = $this->getSlug();
		return $prefix.$this->getMinifiedSlug($slug).'_'.substr(\md5(\implode('.', array(
			DB_HOST,
			DB_NAME,
			$this->getGlobalOption('initial_db_version'),
			$slug,
			'lws',
		))), 0, 16);
	}

	/**	@param $active bool|DateTime
	 *	@return bool|DateTime */
	private function recurringCheck($active)
	{
		if( !\is_admin() || (defined('DOING_AJAX') && DOING_AJAX) )
			return $active;

		if( $active && self::CHECK_INTERVAL && $this->uuid )
		{
			$k = $this->getId('lwschk_');
			$d = $this->getGlobalOption($k);
			if( $d )
			{
				if( \time() > $d )
				{
					$this->updateGlobalOption($k, \date_create()->add(new \DateInterval(self::CHECK_INTERVAL))->getTimestamp());
					if( !($active = $this->check(true)) )
						$this->updateGlobalOption($k, false);
				}
			}
			else
			{
				$this->updateGlobalOption($k, \date_create()->add(new \DateInterval(self::CHECK_INTERVAL))->getTimestamp());
			}
		}
		return $active;
	}

	function deactivate($key=false, $obsoleteKey=false)
	{
		if( false === $key )
			$key = $this->getKey();

		$args = array(
			'woo_sl_action'     => 'deactivate',
			'licence_key'       => $key,
			'product_unique_id' => $this->uuid,
			'domain'            => $this->getSiteUrl(),
		);
		$requestUri = \add_query_arg($args, $this->getRemoteUrl());
		$data       = \wp_remote_get($requestUri);

		if( \is_wp_error($data) || !\in_array(\intval($data['response']['code']), array(200, 301, 302)) )
		{
			$this->notice(__("There was a problem establishing a connection to the license server.", 'lws-adminpanel'));
			return false;
		}

		$dataBody = \json_decode($data['body']);
		$this->log('deactivate', $dataBody);
		if( \is_array($dataBody) )
			$dataBody = \end($dataBody);
		if( \is_object($dataBody) && isset($dataBody->status) )
		{
			if( $dataBody->status == 'success' )
			{
				$level = $obsoleteKey ? 'info' : 'success';
				$txt = array();
				if( $obsoleteKey )
					$txt[] = sprintf(__("The previews license key (%s) has been deactivated.", 'lws-adminpanel'), $key);

				if( isset($dataBody->message) )
					$txt[] = sprintf('<div class="lws-license-small-text">%s</div>', $this->serverMessage($dataBody->message, $dataBody->status_code));
				else
					$txt[] = __("Licence Key Successfully Unassigned.", 'lws-adminpanel');

				$this->notice(implode('</br>', $txt), $level);
				$this->updateGlobalOption($this->getId(), '');
				$this->updateGlobalOption($this->getId('lwschk_'), false);
				return true;
			}
			else
			{
				$level = $obsoleteKey ? 'warning' : 'error';
				$txt = array();
				if( $obsoleteKey )
					$txt[] = sprintf(__("There was a problem deactivating the previews license key (%s).", 'lws-adminpanel'), $key);
				else{
					$txt[] = __("There was a problem deactivating the license.", 'lws-adminpanel');
					$this->updateGlobalOption($this->getId(), '');
					$this->updateGlobalOption($this->getId('lwschk_'), false);
				}
				if( isset($dataBody->message) )
					$txt[] = $this->serverMessage($dataBody->message, $dataBody->status_code);
				$this->notice(implode('</br>', $txt), $level);
			}
		}
		else
		{
			$this->notice(__("There was a problem establishing a connection to the license service.", 'lws-adminpanel'));
		}
		return false;
	}

	/** @return bool|DateTime */
	function check($silentError=true)
	{
		$args = array(
			'woo_sl_action'     => 'status-check',
			'licence_key'       => $this->getKey(),
			'product_unique_id' => $this->uuid,
			'domain'            => $this->getSiteUrl(),
		);
		$requestUri = \add_query_arg($args, $this->getRemoteUrl());
		$data       = \wp_remote_get($requestUri);

		if( \is_wp_error($data) || !\in_array(\intval($data['response']['code']), array(200, 301, 302)) )
		{
			$detail = \is_wp_error($data) ? $data->get_error_message() : $data['response']['code'];
			error_log("There was a problem establishing a connection to the license server. ".$detail);
			return $silentError;
		}

		$dataBody = \json_decode($data['body']);
		$this->log('status-check', $dataBody);
		if( \is_array($dataBody) )
			$dataBody = \end($dataBody);
		if( \is_object($dataBody) && isset($dataBody->status) )
		{
			if( 'success' == $dataBody->status && 's205' == $dataBody->status_code )
			{
				$d = (isset($dataBody->licence_expire) && $dataBody->licence_expire) ? \date_create($dataBody->licence_expire) : false;
				if( $d && $d->getTimestamp() != $this->getLicenseEnding(true) )
				{
					$e = \date_i18n(\get_option('date_format'), $d->getTimestamp());
					if( $d->getTimestamp() < \time() ){
						$this->notice(sprintf(__('The license <b>%3$s</b> for <i>%2$s</i> expired the <b>%1$s</b>.', 'lws-adminpanel'), $e, $this->getName(), $this->getKey()), 'error', '-e', false);
					}else{
						$this->clearNotice('error', '-e');
						$this->notice(sprintf(__('The license <b>%3$s</b> for <i>%2$s</i> will expire the <b>%1$s</b>.', 'lws-adminpanel'), $e, $this->getName(), $this->getKey()), 'warning', '-e', false);
					}
				}
				$this->readSubscription($dataBody);

				$this->updateGlobalOption($this->getId(), $d ? $d->getTimestamp() : 'inf');
				return true;
			}
			else
			{
				$this->readSubscription($dataBody);
				$this->updateGlobalOption($this->getId(), '');
				$dataBody->slug = $this->getSlug();
				$dataBody->license = $this->getkey();
				error_log('License check: '.json_encode($dataBody,  JSON_PRETTY_PRINT|JSON_INVALID_UTF8_IGNORE|JSON_PARTIAL_OUTPUT_ON_ERROR));
				return false;
			}
		}
		else
		{
			error_log("There was a problem establishing a connection to the license service.");
		}
		return $silentError;
	}

	function activate($value, $old=false, $update=true, $z=false)
	{
		if( !$value )
			$value = $old;
		if( $old && $old != $value && $this->isActive() )
			$this->deactivate($old, true);

		$args = array(
			'woo_sl_action'     => 'activate',
			'licence_key'       => $value,
			'product_unique_id' => $this->uuid,
			'domain'            => $this->getSiteUrl(),
		);
		$requestUri = \add_query_arg($args, $this->getRemoteUrl());
		$data       = \wp_remote_get($requestUri);

		if( \is_wp_error($data) || !\in_array(\intval($data['response']['code']), array(200, 301, 302)) )
		{
			$this->notice(__("There was a problem establishing a connection to the license server.", 'lws-adminpanel'));
			return false;
		}

		$dataBody = \json_decode($data['body']);
		$this->log('activate', $dataBody);
		if( \is_array($dataBody) )
			$dataBody = \end($dataBody);
		if( \is_object($dataBody) && isset($dataBody->status) )
		{
			/// s100 first time key activation
			/// s101 key already activated for domain
			/// s205 key is active and valid for domain
			if( $dataBody->status == 'success' && \in_array($dataBody->status_code, array('s100', 's101', 's205')) && isset($dataBody->licence_status) )
			{
				$txt = array(__("Update to the premium version is now available.", 'lws-adminpanel'));
				if( isset($dataBody->message) )
					$txt[] = sprintf('<div class="lws-license-small-text">%s</div>', $this->serverMessage($dataBody->message, $dataBody->status_code));
				$this->notice(implode('</br>', $txt), 'success');

				$d = (isset($dataBody->licence_expire) && $dataBody->licence_expire) ? \date_create($dataBody->licence_expire) : false;
				if( $d )
				{
					$e = \date_i18n(\get_option('date_format'), $d->getTimestamp());
					if( $d->getTimestamp() > \time() )
						$this->notice(sprintf(__('The license <b>%3$s</b> for <i>%2$s</i> will expire the <b>%1$s</b>.', 'lws-adminpanel'), $e, $this->getName(), $value), 'warning', '-e', false);
					else
						$this->notice(sprintf(__('The license <b>%3$s</b> for <i>%2$s</i> already expired the <b>%1$s</b>.', 'lws-adminpanel'), $e, $this->getName(), $value), 'error', '-e');
				}
				if( '4' === $z )
					$dataBody->zombie = 'on';
				$this->readSubscription($dataBody);

				$this->updateGlobalOption($this->getId(), $d ? $d->getTimestamp() : 'inf');
				\lws_admin_delete_notice('trial-ends-'.$this->getSlug());

				if( $update && $this->isLite() )
				{
					// page will be redirected after option saved, go to update if required
					\add_filter('wp_redirect', array($this, 'redirectToUpdate'), 50, 2);
				}
				return true;
			}
			else
			{
				$txt = array(sprintf(__("There was a problem activating the license (%s).", 'lws-adminpanel'), $value));
				if( isset($dataBody->message) )
					$txt[] = $this->serverMessage($dataBody->message, $dataBody->status_code);
				$this->notice(implode('</br>', $txt));
			}
		}
		else
		{
			$this->notice(__("There was a problem establishing a connection to the license service.", 'lws-adminpanel'));
		}

		return false;
	}

	private function notice($msg, $level='error', $suffix='', $once=true)
	{
		$k = ('lws_lic_udt_'.$level.'_'.$this->getSlug().$suffix);
		if( $msg )
		{
			\lws_admin_add_notice($k, $msg, array('level' => $level, 'once'=>$once));
			$this->ignoreSavingConfirmation();
		}
		else
			\lws_admin_delete_notice($k);
	}

	private function clearNotice($levels='error', $suffix='')
	{
		if( !is_array($levels) )
			$levels = array($levels);
		foreach( $levels as $level )
			\lws_admin_delete_notice('lws_lic_udt_'.$level.'_'.$this->getSlug().$suffix);
	}

	/** @see https://woosoftwarelicense.com/documentation/explain-api-status-codes/ */
	private function serverMessage($msg, $code)
	{
		switch($code)
		{
			case 'e002': return __("Invalid licence key.", 'lws-adminpanel');
			case 'e110': return __("Invalid licence key or licence not active for domain.", 'lws-adminpanel');
			case 'e112': return __("You had reached the maximum number of domains for this key.", 'lws-adminpanel');
			case 'e301': return __("Licence Key does not match this product.", 'lws-adminpanel');
			case 's201': return __("Licence Key Successfully Unassigned.", 'lws-adminpanel');
			case 's203': return __("Licence Key Is Unassigned.", 'lws-adminpanel');
			case 's205': return __("Licence key Is Active and Valid for Domain.", 'lws-adminpanel');
		}
		return $msg;
	}

	/// keep them in database in case of debug
	private function log($action, $data)
	{
		$k = 'lws_last_license_' . $action;
		$d = \get_option($k, array());
		$data = array(
			'log_date' => \date('Y-m-d H:i:s'),
			'data' => $data,
		);
		\update_option($k, \array_merge(\is_array($d) ? $d : array(), array($this->getSlug() => $data)));
	}

	/** remove any 'Settings saved.' notice. */
	private function ignoreSavingConfirmation()
	{
		\add_filter('pre_set_transient_settings_errors', function(){\lws_admin_delete_notice('lws_ap_page');}, 20);
	}

	private function getTransientUpdateKey()
	{
		$request = \add_query_arg($this->prepareRequest('plugin_update', true), $this->getRemoteUrl());
		$transientKey = ($this->getSlug() . '-lwsudt-' . \md5($request));
		return $transientKey;
	}

	public function clearUpdateTransient()
	{
		\delete_site_transient($this->getTransientUpdateKey());
	}

	public function checkForUpdate($plugins)
	{
		if( !(\is_object($plugins) && isset($plugins->response)) )
			return $plugins;

		$path = $this->getBasename();
		if( isset($plugins->response[$path]) )
			unset($plugins->response[$path]);

		$noUpdate = false;
		if( isset($plugins->no_update, $plugins->no_update[$path]) )
		{
			$noUpdate = $plugins->no_update[$path];
			unset($plugins->no_update[$path]);
		}

		//check if cached
		$transientKey = $this->getTransientUpdateKey();
		$data = \get_site_transient($transientKey);
		if( false === $data )
		{
			$request = \add_query_arg($this->prepareRequest('plugin_update'), $this->getRemoteUrl());
			global $wp_version;
			$agent = ('WordPress/' . $wp_version . '; ' . get_bloginfo('url'));
			$data = \wp_remote_get($request, array('timeout' => 20, 'user-agent'  => $agent));

			if( \is_wp_error($data) || !\in_array(\intval($data['response']['code']), array(200, 301, 302)) )
			{
				// server call fail
				return $plugins;
			}

			\set_site_transient($transientKey, $data, 60 * 60 * 4 ); // expiration = 4h
		}

		$dataBody = \json_decode($data['body']);
		$this->log('plugin_update', $dataBody);

		if( \is_array($dataBody) && $dataBody )
		{
			$dataBody = \end($dataBody);
			$response = (\is_object($dataBody) && isset($dataBody->message)) ? $dataBody->message : false;

			if( \is_object($response) && $response ) // Feed the update data into WP updater
			{
				$response = $this->postprocessResponse($response);
				$plugins->response[$path] = $response;
				return $plugins;
			}
		}

		if( $noUpdate )
			$plugins->no_update[$path] = $noUpdate;
		return $plugins;
	}

	/** get plugin info for wp 'plugin_api' hook
	 *	@param $def false
	 *	@param $action 'plugin_information'
	 *	@param $args object instance */
	public function checkRemoteInfo($def, $action, $args)
	{
		if( !(\is_object($args) && isset($args->slug) && $this->getSlug() == $args->slug) )
			return $def;

		global $wp_version;
		$request = \add_query_arg($this->prepareRequest($action), $this->getRemoteUrl());
		$data = wp_remote_get($request, array(
			'timeout'     => 20,
			'user-agent'  => 'WordPress/' . $wp_version . '; ' . \get_bloginfo('url'),
		));

		if( \is_wp_error($data) || !\in_array(\intval($data['response']['code']), array(200, 301, 302)) )
		{
			$txt = __('An Unexpected HTTP Error occurred during the API request.' , 'lws-adminpanel');
			return new \WP_Error('plugins_api_failed', $txt, $data);
		}

		$dataBody = \json_decode($data['body']);
		$this->log($action, $dataBody);
		if( \is_array($dataBody) )
			$dataBody = \end($dataBody);

		$response = false;
		if( $dataBody && \is_object($dataBody) && isset($dataBody->message) )
			$response = $dataBody->message;

		if( \is_object($response) && $response )
		{
			if( isset($response->licence_expire) )
			{
				if( $response->licence_expire && $this->isActive() && ($d = \date_create($response->licence_expire)) )
					$this->updateGlobalOption($this->getId(), $d->getTimestamp());
				unset($response->licence_expire);
			}
			if( isset($response->trial_expire) )
			{
				if( $this->isTrial() )
				{
					$ending = $this->getGlobalOption('lws-license-end-'.$args->slug);
					if( $ending && ($ending = \date_create($ending)) )
						$response->trial_expire = \min($ending->getTimestamp(), $response->trial_expire);
					$this->updateGlobalOption($this->getId('lwstrial_'), \absint($response->trial_expire));
				}
				unset($response->trial_expire);
			}
			$this->readSubscription($response);
			return $this->postprocessResponse($response);
		}
		else
		{
			$txt = __('Unexpected response from API.' , 'lws-adminpanel');
			return new \WP_Error('plugins_api_failed', $txt, $response);
		}
	}

	private function prepareRequest($action, $real=false)
	{
		global $wp_version;
		$slug = $this->getSlug();

		$query = array(
			$this->getActionKey()   => $action,
			'product_unique_id'     => $this->uuid,
			'licence_key'           => $this->getKey(),
			'version'               => $this->getPluginVersion(),
			'domain'                => $this->getSiteUrl(),
			'wp-version'            => $wp_version,
			'api_version'           => self::API_VERSION,
		);

		if( !$real && $this->isLite() && ($this->isActive() || $this->isTrial()) )
			$query['version'] = '0.0.0'; // we have to go to pro, so let it be newer

		return $query;
	}

	private function readSubscription(&$response)
	{
		if( !$this->maybeActive() )
			return;

		if( isset($response->subscription_status) )
		{
			$was = $this->isSubscriptionActive();
			$this->updateGlobalOption($this->getId('lwssupport_'), $response->subscription_status);

			if( !$this->isSubscriptionActive() )
			{
				if( $was )
				{
					if( $this->isZombie() )
					{
						$msg = sprintf(
							__('Your support access for the plugin <b>%1$s</b> is no longer available. Please visit %2$s to expend your support period.', 'lws-adminpanel'),
							$this->getName(),
							sprintf('<a href="%s" target="_blank">%s</a>', $this->getRemoteMyAccountURL(), $this->getPluginAuthor())
						);
						\lws_admin_add_notice('lwssupport_e_'.$this->getSlug(), $msg, array('level' => 'info'));
					}
					else
					{
						$msg = sprintf(
							__('Your subscription to plugin <b>%1$s</b> Premium Services expired. Please visit %2$s to expend your license period.', 'lws-adminpanel'),
							$this->getName(),
							sprintf('<a href="%s" target="_blank">%s</a>', $this->getRemoteMyAccountURL(), $this->getPluginAuthor())
						);
						\lws_admin_add_notice('lwssupport_e_'.$this->getSlug(), $msg, array('level' => 'error'));
					}
				}
			}
			else
			{
				\lws_admin_delete_notice('lwssupport_e_'.$this->getSlug());
			}
			unset($response->subscription_status);
		}
		else
			$this->updateGlobalOption($this->getId('lwssupport_'), '');

		if( isset($response->zombie) )
		{
			$this->updateGlobalOption($this->getId('lwszombie_'), $response->zombie);
		}
	}

	function getRemoteMyAccountURL()
	{
		$page = '/my-account/'; // (?) /my-account/subscriptions/
		return \apply_filters('lws_adm_license_remote_myaccount_url', $this->getRemoteUrl($page), $this->getSlug());
	}

	private function postprocessResponse($response)
	{
		//include slug and plugin data
		$response->slug    = $this->getSlug();
		$response->plugin  = $this->getBasename();

		//if sections are being set
		if( isset($response->sections) )
			$response->sections = (array)$response->sections;
		//if banners are being set
		if( isset($response->banners) )
			$response->banners = (array)$response->banners;
		//if icons being set, convert to array
		if( isset($response->icons) )
			$response->icons = (array)$response->icons;

		return $response;
	}

	function installUpdater()
	{
		$t = false;
		$update = ($this->isActive() || ($t = $this->isTrial()));
		if( $update )
		{
			// Take over the update check
			\add_filter('pre_set_site_transient_update_plugins', array($this, 'checkForUpdate'), PHP_INT_MAX);

			if( $t )
				$this->noticeTrialEndsSoon();
		}

		if( $update || !$this->isLiteAvailable() )
		{
			// Take over the Plugin info screen
			\add_filter('plugins_api', array($this, 'checkRemoteInfo') , PHP_INT_MAX, 3);
		}
	}

	// Warn before trial ends
	private function noticeTrialEndsSoon()
	{
		if( $e = $this->getTrialEnding() )
		{
			$diff = $e->diff(\date_create(), true)->format('%a');
			$last = \get_option($this->getId('lasttdiff_'), PHP_INT_MAX);
			foreach( $this->trialWarnings as $delay )
			{
				if( $delay < $last && $diff <= $delay )
				{
					\update_option($this->getId('lasttdiff_'), $delay);
					$k = 'trial-ends-'.$this->getSlug();
					$link = sprintf(
						"<a href='%s' target='_blank'>%s</a>",
						\esc_attr(\apply_filters('lws_adm_license_product_page_url', $this->getPluginURI(), $this->getSlug())),
						sprintf(__("%s Premium", 'lws-adminpanel'), $this->getName())
					);
					$date = \date_i18n(\get_option('date_format'), $e->getTimestamp());
					$msg = \apply_filters('lws_adm_trialend_msg', '', $this->getSlug(), $date, $link, $delay, $diff, $e);
					if (!$msg)
						$msg = sprintf('<h2>' . __('Your Trial period expires the %1$d. Consider purchasing %2$s.', 'lws-adminpanel') . '</h2>', $date, $link);
					\lws_admin_add_notice($k, $msg, array('level' => 'warning', 'dismissible' => true, 'forgettable' => true));
				}
			}
		}
	}

	/** tweak option saved to try to update to premium
	 * if code is not already here, instead of returning
	 * to original options page. */
	function redirectToUpdate($location, $status)
	{
		if( $this->isLite() && ($this->isActive() || $this->isTrial()) )
		{
			// ensure we filter update check now
			if( !\has_filter('pre_set_site_transient_update_plugins', array($this, 'checkForUpdate')) )
				\add_filter('pre_set_site_transient_update_plugins', array($this, 'checkForUpdate'), PHP_INT_MAX);

			// let usual trick performs itself, force the update info refresh
			$this->clearUpdateTransient();
			$transients = \get_site_transient('update_plugins');
			// ensure transient minimal values
			if( !(\is_object($transients) && isset($transients->response)) )
			{
				$transients = (object)array(
					'last_checked' => \time(),
					'response'     => array(),
					'no_update'    => array(),
					'translations' => array(),
				);
			}
			\set_site_transient('update_plugins', $transients);

			// get it again
			$transients = \get_site_transient('update_plugins');
			$path = $this->getBasename();
			if( !(\is_object($transients) && isset($transients->response, $transients->response[$path])) )
				return $location;

			// go to running update page with our plugin selected
			$args = array(
				'action' => 'upgrade-plugin',
				'plugin' => $path,
				'_wpnonce' => \wp_create_nonce('upgrade-plugin_' . $path),
			);
			$location = \self_admin_url('update.php');
			$location = \add_query_arg($args, $location);
		}
		return $location;
	}
}
