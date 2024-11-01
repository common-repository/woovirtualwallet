<?php
namespace LWS\Adminpanel\Internal\Credits;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();
require_once LWS_ADMIN_PANEL_INCLUDES . '/internal/credits/page.php';

/** Add a special page to manage the plugin licenses. */
class SupportPage extends \LWS\Adminpanel\Internal\Credits\Page
{
	function suffixPage(string $page){return $page.'_support';}
	function getTabId(){return 'support';}

	/** @see \LWS\Adminpanel\Internal\Credits\Page::__construct() */
	static function install($file, $adminPageId, $targetPage=false)
	{
		$me = new self($file, $adminPageId, $targetPage, true);

		\add_filter('lws_adm_menu_support_url', array($me, 'getScreenUrl'), 10, 3);

		if( isset($_REQUEST['option_page']) )
		{
			\add_filter('pre_update_option_lws_adm_support_timestamp', array($me, 'preUpdateAction'), 10, 3);
		}
	}

	/** remove any 'Settings saved.' notice. */
	private function ignoreSavingConfirmation()
	{
		\add_filter('pre_set_transient_settings_errors', function(){\lws_admin_delete_notice('lws_ap_page');}, 20);
	}

	private function isNewSubmit($value, $old)
	{
		if( $value == $old )
			return false;

		$flags = explode('|', $value);
		if( 2 != count($flags) )
			return false;

		if( $this->getSlug() != $flags[0] )
			return false;

		return true;
	}

	/** always empty the value after usage. */
	function preUpdateAction($value, $old=false, $option=false)
	{
		if( !$this->isNewSubmit($value, $old) )
			return $value;
		$this->ignoreSavingConfirmation();
		$values = $this->getMailValues();
		if( !$values )
		{
			$this->saveLastValues();
			return $value;
		}

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf('From: %s <%s>', $values['lws_adm_support_user_name'], $values['lws_adm_support_email']),
		);

		\add_action('phpmailer_init', array($this, 'addAltBody'), 9, 1);
		$sent = \wp_mail(
			\apply_filters('lws_adm_support_mail_destination', 'support@longwatchstudio.com', $this->getSlug()),
			"[" . $this->getPluginInfo('Name') . "] " . $this->getSubjectText($values['lws_adm_support_subject'], $values['lws_adm_support_subject']),
			\apply_filters('lws_adm_support_mail_body', $values['lws_adm_support_request'], $this->getSlug()),
			\apply_filters('lws_adm_support_mail_header', $headers, $this->getSlug()),
			$this->getAttachements()
		);
		\remove_action('phpmailer_init', array($this, 'addAltBody'), 9);

		if( !$sent )
		{
			\lws_admin_add_notice_once('lws_adm_support_request', sprintf(__("Sorry, the mail failed to be sent. You can try again or use the &lt;%s&gt; contact address.", 'lws-adminpanel'), 'contact@longwatchstudio.com'));
			$this->saveLastValues();
			return $value;
		}

		$this->saveLastValues(true);
		\lws_admin_add_notice_once('lws_adm_support_request', __("Your request has been sent to our support team. We will respond as soon as possible.", 'lws-adminpanel'), array('level' => 'success'));
		return $value;
	}

	private function getAttachements()
	{
		$path = rtrim(WP_CONTENT_DIR, '/').'/uploads/lws-debug/';
		if( !file_exists($path) )
			mkdir($path, 0775, true);

		$file = $path . 'support.txt';
		\file_put_contents($file, $this->getDebugInfo());
		@chmod($file, 0664);
		return array($file);
	}

	private function getSubjectText($key, $default=false, $subjects=false)
	{
		if( false === $subjects )
			$subjects = $this->getPrefilled();
		foreach( $subjects['select'] as $subject )
		{
			if( $subject['value'] == $key )
				return $subject['label'];
			if( isset($subject['group']) && $subject['group'] )
			{
				$label = $this->getSubjectText($key, false, $subject['group']);
				if( $label )
					return $label;
			}
		}
		return $default;
	}

	/** add a plain text version of our email */
	function addAltBody($phpmailer)
	{
		if( $phpmailer->ContentType === 'text/plain' )
			return;

		static $toDelPattern = array(
			'@<head[^>]*?>.*?</head>@siu',
			'@<style[^>]*?>.*?</style>@siu',
			'@<script[^>]*?.*?</script>@siu',
			'@<object[^>]*?.*?</object>@siu',
			'@<embed[^>]*?.*?</embed>@siu',
			'@<noscript[^>]*?.*?</noscript>@siu',
			'@<noembed[^>]*?.*?</noembed>@siu'
		);
		$body = preg_replace($toDelPattern, '', $phpmailer->Body);

		static $replace = array(
			"<br" => "\n<br",
			"</p>" => "</p>\n\n",
			"</td>" => "</td>\t",
			"</tr>" => "</tr>\n",
			"<table" => "\n<table",
			"</thead>" => "</thead>\n",
			"</tbody>" => "</tbody>\n",
			"</table>" => "</table>\n",
		);
		$body = str_replace(array_keys($replace), array_values($replace), $body);
		$body = trim(\wp_kses($body, array()));

		static $redondant = array("/\t+/", '/ +/', "/(\n[ \t]*\n[ \t]*)+/", "/\n[ \t]*/");
		static $single = array("\t", ' ', "\n\n", "\n");
		$phpmailer->AltBody = html_entity_decode(preg_replace($redondant, $single, $body));
	}

	// append website install info
	private function getDebugInfo()
	{
		$debug = "=== Debug Info ===\n\n> ";

		global $wp_version;
		$data = array(
			sprintf("From: %s %s", $this->getSlug(), $this->getPluginInfo()['Version']),
			sprintf("License: %s", \get_network_option(\get_main_network_id(), 'lws-license-key-' . $this->getSlug())),
			sprintf("Website: %s", (defined('WP_SITEURL') && WP_SITEURL) ? WP_SITEURL : \get_option('siteurl')),
			sprintf("WordPress: %s (%s)", $wp_version, \is_multisite() ? 'Multisite is enabled' : 'Multisite is disabled'),
			sprintf("PHP: %s", \phpversion()),
		);
		$debug .= implode("\n> ", $data);

		$data = \apply_filters('lws_adminpanel_licenses_status', array());
		$filter = array(
			'Name' => true,
			'Version' => true,
			'lite' => true, // no pro code
			'active' => true, // trial or pro
			'trial' => true, // is trial
			'expired' => true,
			'subscription' => true,
			'trial_available' => true,
		);
		foreach( $data as $plugin => &$details )
		{
			$details = array_intersect_key($details, $filter);
			foreach( $details as $k => &$v )
				$v = sprintf("\n> > %s: %s", $k, \is_bool($v) ? ($v ? 'Yes' : 'No') : $v);
			$details = ($plugin . implode('', $details));
		}
		$debug .= ("\n\n=== installed LWS licenses ===\n\n> " . implode("\n\n> ", $data));

		if( !\function_exists('\get_plugins') )
			require_once(ABSPATH . 'wp-admin/includes/plugin.php');
		$others = \get_plugins();
		$debug .= sprintf("\n\n=== Other Plugins --- %d installed (with lws and deactivated) ===", count($others));
		foreach( $others as $path => $details )
		{
			if( !\is_plugin_active($path) )
				continue;
			if( isset($data[$path]) )
				continue;

			$slug = dirname($path);
			$debug .= sprintf(
				"\n\n> %s %s\n> > %s (%s)",
				\trim($slug, '.') ? $slug : $path,
				isset($details['Version']) ? $details['Version'] : '?',
				isset($details['Name']) ? $details['Name'] : '?',
				isset($details['PluginURI']) ? $details['PluginURI'] : '?'
			);
		}

		return $debug;
	}

	private function getMailValues()
	{
		$values = array(
			'lws_adm_support_user_name' => isset($_POST['lws_adm_support_user_name']) ? \trim(\sanitize_text_field($_POST['lws_adm_support_user_name'])) : false,
			'lws_adm_support_email'     => isset($_POST['lws_adm_support_email']) ? \trim(\sanitize_email($_POST['lws_adm_support_email'])) : false,
			'lws_adm_support_subject'   => isset($_POST['lws_adm_support_subject']) ? \trim(\sanitize_text_field($_POST['lws_adm_support_subject'])) : false,
			'lws_adm_support_request'   => isset($_POST['lws_adm_support_request']) ? \trim(\wp_unslash($_POST['lws_adm_support_request'])) : false,
		);

		if( !$values['lws_adm_support_user_name'] )
		{
			\lws_admin_add_notice_once('lws_adm_support_request', __("Please, fill your Name before sending the mail.", 'lws-adminpanel'));
			return false;
		}
		if( !$values['lws_adm_support_email'] )
		{
			\lws_admin_add_notice_once('lws_adm_support_request', __("Please, set a response EMail.", 'lws-adminpanel'));
			return false;
		}
		if( !$values['lws_adm_support_subject'] )
		{
			\lws_admin_add_notice_once('lws_adm_support_request', __("Please, select a Subject before sending the mail.", 'lws-adminpanel'));
			return false;
		}
		if( !$values['lws_adm_support_request'] )
		{
			\lws_admin_add_notice_once('lws_adm_support_request', __("Please, Fill your request before sending the mail.", 'lws-adminpanel'));
			return false;
		}
		return $values;
	}

	/** Save or remove in session the current email info */
	private function saveLastValues($clear=false)
	{
		if( !$clear )
		{
			$values = array(
				'lws_adm_support_user_name' => isset($_POST['lws_adm_support_user_name']) ? \trim(\sanitize_text_field($_POST['lws_adm_support_user_name'])) : false,
				'lws_adm_support_email'     => isset($_POST['lws_adm_support_email']) ? \trim(\sanitize_email($_POST['lws_adm_support_email'])) : false,
				'lws_adm_support_subject'   => isset($_POST['lws_adm_support_subject']) ? \trim(\sanitize_text_field($_POST['lws_adm_support_subject'])) : false,
				'lws_adm_support_request'   => isset($_POST['lws_adm_support_request']) ? \trim(\wp_unslash($_POST['lws_adm_support_request'])) : false,
			);
			$_SESSION['lws_adm_support_request_values'] = $values;
		}
		else if( isset($_SESSION['lws_adm_support_request_values']) )
		{
			$_SESSION['lws_adm_support_request_values'] = false;
		}
	}

	/** look at session if we kept something */
	private function restoreLastValues()
	{
		$user = \wp_get_current_user();
		$values = array(
			'lws_adm_support_user_name' => $user->first_name . ' ' . $user->last_name,
			'lws_adm_support_email'     => '',
			'lws_adm_support_subject'   => '',
			'lws_adm_support_request'   => '',
		);
		if( isset($_SESSION['lws_adm_support_request_values']) && $_SESSION['lws_adm_support_request_values'] )
		{
			$session = $_SESSION['lws_adm_support_request_values'];
			if( !is_array($session) )
				return $values;

			foreach( $values as $k => &$v )
			{
				if( isset($session[$k]) && $session[$k] )
					$v = $session[$k];
			}
		}
		return $values;
	}

	protected function getTab()
	{
		if( !session_id() ) /// @see restoreLastValues(), @see saveLastValues()
			session_start(); /// session must be started before headers sent

		$groups = $this->getInfoGroup();
		$groups = $this->getFormGroup($groups);

		$page = array(
			'id'     => $this->getTabId(),
			'title'  => __("Support", 'lws-adminpanel'),
			'icon'   => 'lws-icon-support',
			'nosave' => true,
			'groups' => $groups,
		);
		return $page;
	}

	function forceTinyMceInHtmlMode()
	{
		\add_filter('wp_editor_settings', function($settings){
			$settings['quicktags'] = false;
			$settings['wpautop'] = false;
			return $settings;
		});
	}

	private function getInfoGroup($groups = array())
	{
		$content = <<<EOT
<div class='lws-support-content'>
	<p>You can send a support request for different reasons. However, for some specific requests, we will also collect extra data that will help us answer accurately.
	You will find below the different types of support requests possible and what we need from you for each type of request.</p>
	<h2>Bug Report</h2>
	<p>If you want to report a bug, you must first ensure that the bug comes from this plugin.
	<b>In order to be sure, please deactivate all plugins that are not necessary for the feature to work</b>.
	If the bug is still present, send us a support request.
	If the bug disappears, then try activating your plugins one by one and test the feature until the bug appears again.
	When sending the bug report, please indicate which other plugin created the conflict.
	<h3>Data Collected</h3>
	<ul>
		<li>Plugin Name and License Key</li>
		<li>License status</li>
		<li>List of installed and active plugins</li>
	</ul>
	<h2>Help for the plugin setup</h2>
	<p>If you want some help for setting up the plugin, our support team is here to help.
	When selecting the subject, a pre-filled text will be added to the text editor.
	<b>Please try to answer all questions in the pre-filled text</b>.</p>
	<h3>Data Collected</h3>
	<ul>
		<li>Plugin Name and License Key</li>
		<li>License status</li>
	</ul>
	<h2>Feature request</h2>
	<p>If you want to request a new feature in the plugin, please describe it and its purpose as accurately as possible.</p>
	<h3>Data Collected</h3>
	<ul>
		<li>Plugin Name and License Key</li>
		<li>License status</li>
	</ul>
</div>
EOT;

		$groups['info'] = array(
			'id'     => 'help',
			'icon'   => 'lws-icon-info',
			'title'  => __("Support Instructions", 'lws-adminpanel'),
			'color'  => '#335696',
			'class'  => 'half',
			'text'   => __("Please read the instructions carefully before sending a support request.", 'lws-adminpanel'),
			'fields' => array(
				'subject' => array(
					'id'    => '',
					'type'  => 'custom',
					'extra' => array(
						'content' => $content,
						'gizmo'   => true,
					)
				),
			)
		);
		return $groups;
	}

	private function getPrefilled()
	{
		$prefilled = array(
			'select' => array(
				array('value' => 'bug', 'label' => 'Report a Bug'),
				array('value' => 'howto', 'label' => 'Help for the plugin setup'),
				array('value' => 'feature', 'label' => 'Request a new feature'),
			),
			'texts' => array(
				'howto' => 'Help for the plugin setup',
				'feature' => 'Describe the feature that you would like to see added to the plugin',
				'bug' => '',
			)
		);

		$prefilled['texts']['bug'] = <<<EOT
<p><b>Before sending the bug request, please answer the following questions:</b></p>
<ul>
<li>Did you try to disable all other unnecessary plugins ?</li>
<li>Does the problem persist after disabling all plugins ?</li>
<li>If not, after enabling the plugins one by one, can you tell us which plugin caused the conflict ?</li>
</ul>
<h2>Bug Description</h2>
<p>Please provide a detailed description of the bug encountered and, if possible, screenshots</p>
EOT;

		$prefilled = \apply_filters('lws_adm_support_contents', $prefilled, $this->getSlug());

		foreach ($prefilled['texts'] as $subjet => &$text)
		{
			$text = sprintf(
				'<div class="lws_adm_support_content_template" style="display:none;" data-value="%s" data-text="%s"></div>',
				\esc_attr($subjet),
				base64_encode(json_encode($text))
			);
		}
		return $prefilled;
	}

	private function getFormGroup($groups = array())
	{
		$subjects = $this->getPrefilled();
		$values = $this->restoreLastValues();

		$groups['form'] = array(
			'id'     => 'form',
			'icon'   => 'lws-icon-support',
			'title'  => sprintf(__("Support Request for <b>%s</b>", 'lws-adminpanel'), $this->getPluginInfo('Name')),
			'color'  => '#336666',
			'class'  => 'half',
			'text'   => __("Fill in the form below to send a support request.", 'lws-adminpanel'),
			'function' => array($this, 'forceTinyMceInHtmlMode'),
			'delayedFunction' => function()use($subjects){echo implode('', $subjects['texts']);},
			'fields' => array(
				'timestamp' => array(
					'id'    => 'lws_adm_support_timestamp',
					'type'  => 'hidden',
					'title' => '',
					'extra' => array(
						'value' => $this->getSlug() . '|' . \time(),
					),
				),
				'username' => array(
					'id'    => 'lws_adm_support_user_name',
					'type'  => 'input',
					'title' => __('Your Name', 'lws-adminpanel'),
					'extra' => array(
						'noconfirm' => true,
						'value'     => $values['lws_adm_support_user_name'],
						'size'		=> '40',
						'gizmo'   => true,
					)
				),
				'email' => array(
					'id'    => 'lws_adm_support_email',
					'type'  => 'input',
					'title' => __('Your Email', 'lws-adminpanel'),
					'extra' => array(
						'noconfirm' => true,
						'value' => $values['lws_adm_support_email'],
						'size'	=> '40',
						'gizmo' => true,
						'help'  => __("The email we will response to. Please make sure this is a valid email that you consult frequently.", 'lws-adminpanel'),
					)
				),
				'subject' => array(
					'id'    => 'lws_adm_support_subject',
					'type'  => 'lacselect',
					'title' => __('Subject', 'lws-adminpanel'),
					'extra' => array(
						'noconfirm' => true,
						'source'    => $subjects['select'],
						'mode'      => 'select',
						'maxwidth'  => '500px',
						'gizmo'     => true,
					)
				),
				'request' => array(
					'id'    => 'lws_adm_support_request',
					'type'  => 'wpeditor',
					'title' => __("Your Request", 'lws-adminpanel'),
					'extra' => array(
						'value' => $values['lws_adm_support_request'],
						'help'      => __("Describe your problem here, what happen, what is expected, what did you do.", 'lws-adminpanel'),
						'textarea_rows' => 12,
						'gizmo'     => true,
					)
				),
				'submit' => array(
					'id'    => '',
					'type'  => 'custom',
					'extra' => array(
						'gizmo'   => true,
						'content' => sprintf(
							"<button type='submit' class='lws-button-link'>%s</button>",
							__("Send the support request", 'lws-adminpanel')
						),
					)
				),
			)
		);

		if( $values['lws_adm_support_subject'] )
			$groups['form']['fields']['subject']['extra']['value'] = $values['lws_adm_support_subject'];
		return $groups;
	}
}
