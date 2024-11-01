<?php
namespace LWS\Adminpanel\Pages;
if( !defined( 'ABSPATH' ) ) exit();


/** Admin notices. Singleton.
 * disimss notice is done via javascript
 * but if an array of notice key is given in 'lws_notice_dismiss' as POST,
 * that notices will be ignored this time.
 *
 * Warning: once loaded, some notices are removed.
 * Take care to never call Notices too soon.
 * Ensure you are at a display step of WoprdPress. */
class Notices
{
	private $notices = array();

	/** return array of object with key, level, dismissible, forgetable, message */
	/** Can be notices or messages
	 *	@param $type (string) 'transient' | 'persistant' */
	function getNotices($type = '')
	{
		if( 'transient' == $type )
			return array_filter($this->notices, function($item){ return $item->transient;});
		else if( 'persistant' == $type )
			return array_filter($this->notices, function($item){ return !$item->transient;});
		else
			return $this->notices;
	}

	/**	A bloc to display on any not lws page.
	 *	@see getNotices */
	function itemToHTML($item)
	{
		if( !$item->message )
			return '';

		return sprintf(
			"<div class='notice lws-adminpanel-notice %s' %s>%s%s</div>",
			$this->getNoticeClass($item),
			$this->getNoticeArgs($item),
			$item->message,
			$this->getCloseButton($item)
		);
	}

	/** return the singleton, load notices if not done yet. */
	static function instance()
	{
		static $_inst = false;
		if( false === $_inst )
		{
			$_inst = new self();
			$_inst->load();
		}
		return $_inst;
	}

	function load()
	{
		$initial = \get_site_option('lws_adminpanel_notices', array());
		$waiting = $this->filterOutDismissed($initial);
		$remaining = array();

		foreach( $waiting as $key => $notice )
		{
			if( !is_array($notice) )
				$notice = array('message'=>$notice, 'once'=>true);

			$item = (object)array(
				'key'         => \sanitize_text_field($key),
				'level'       => isset($notice['level']) ? $notice['level'] : 'warning',
				'dismissible' => !isset($notice['dismissible']) || \boolval($notice['dismissible']),
				'forgettable' => !isset($notice['forgettable']) || \boolval($notice['forgettable']),
				'transient'   => isset($notice['once']) && boolval($notice['once']),
				'message'     => '',
			);
			if( !$item->transient )
				$remaining[$key] = $notice;

			if( isset($notice['d']) && isset($notice['n']) )
			{
				$d = (\is_numeric($notice['d']) && 0<$notice['d']) ? \date_i18n(\get_option( 'date_format' ), \strtotime($notice['d'])) : print_r($notice['d'], true);
				error_log(sprintf('v3 trial expired %s: %s', $notice['n'], $d));
				if( isset($remaining[$key]) )
					unset($remaining[$key]);
			}
			else if( isset($notice['message']) )
			{
				$item->message = \apply_filters('lws_notices_content', $notice['message'], $key);
			}

			$this->notices[$key] = $item;
		}

		if( count($initial) != count($remaining) )
		{
			\update_site_option('lws_adminpanel_notices', $remaining);
		}
	}

	/** @see getNotices */
	function getNoticeClass($item)
	{
		$classes = array('notice-'.\esc_attr($item->level));
		if( $item->dismissible ) $classes[] = 'lws-is-dismissible';
		if( $item->forgettable ) $classes[] = 'lws-is-forgettable';
		return implode(' ', $classes);
	}

	/** @see getNotices */
	function getNoticeArgs($item)
	{
		return sprintf('data-key="%s"', \esc_attr($item->key));
	}

	/** dismiss is done by javascript only, but we can force a form submit to ignore some */
	protected function filterOutDismissed($items)
	{
		$dismissed = isset($_POST['lws_notice_dismiss']) && \is_array($_POST['lws_notice_dismiss']) ? \array_map('sanitize_text_field', $_POST['lws_notice_dismiss']) : array();
		return \array_diff_key($items, \array_combine($dismissed, $dismissed));
	}

	function getForceDismissInputs()
	{
		$inputs = '';
		foreach( $notices as $n )
		{
			$inputs .= "<input type='hidden' name='lws_notice_dismiss[]' value='{$n->key}'>";
		}
		return $inputs;
	}

	function getCloseButton($item)
	{
		if( !($item->dismissible || $item->forgettable) )
			return '';

		$html = __("Dismiss this notice", 'lws-adminpanel');
		$html = "<button type='submit' class='lws-notice-dismiss'><span class='screen-reader-text'>{$html}</span></button>";
		if( $this->isReloadingForced() )
		{
			$allAtOnce = $this->getForceDismissInputs();
			$html = "<form method='post'>{$allAtOnce}{$html}</form>";
		}
		return $html;
	}

	function enqueueScripts()
	{
		if( !$this->isReloadingForced() )
		{
			\wp_enqueue_script('jquery');
			\wp_enqueue_script('lws-tools');
			\wp_enqueue_script('lws-admin-notices', LWS_ADMIN_PANEL_JS . '/adminnotices.js', array('jquery','lws-tools'), LWS_ADMIN_PANEL_VERSION, true);
		}
		\wp_enqueue_style('dashicons');
		\wp_enqueue_style('lws-notices');
	}

	function isReloadingForced()
	{
		if( !isset($this->reloading) )
			$this->reloading = \boolval(\get_option('lws_adminpanel_notice_dismiss_force_reload', ''));
		return $this->reloading;
	}
}
