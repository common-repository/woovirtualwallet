<?php
namespace LWS\Adminpanel\Pages\Screens;
if( !defined( 'ABSPATH' ) ) exit();


/**  */
class Admin extends \LWS\Adminpanel\Pages\Page
{
	public static $MaxTitleLength = 21; /// for table of content
	private $groups = array(); /// instances of Group class

	public function content()
	{
		\wp_enqueue_style('lws-admin-page');
		if(isset($this->vertnav) && $this->vertnav)
		{
			\wp_enqueue_script('lws-vert-nav');
		}
		if( $this->hasGroup() || $this->hasCustoms() )
		{
			echo "<div class='lws-admin-page'>";
			$this->execCustomsBefore();
			if( $this->hasGroup() )	$this->echoForm();
			$this->execCustomsAfter();
			echo "</div>";
		}
	}

	public function getType()
	{
		return 'admin';
	}

	/** Create instances of active groups and fields. */
	protected function prepare()
	{
		\add_filter('pre_set_transient_settings_errors', array($this, 'noticeSettingsSaved'));
		$this->createGroups($this->data, $this->getPath());
	}

	function hasCustoms($before=true, $after=true)
	{
		return ($this->custom['top'] || $this->custom['bot']);
	}

	/** Allows plugin execute custom code just before page content.
	 *	Callables can be added from page and/or traversed tabs */
	function execCustomsBefore()
	{
		if( $this->custom['top'] )
		{
			$path = $this->getPath();
			foreach( $this->custom['top'] as $callable )
				\call_user_func($callable, $this->getId(), $path);
		}
	}

	/** Allows plugin execute custom code just after page content.
	 *	Callables can be added from page and/or traversed tabs */
	function execCustomsAfter()
	{
		if( $this->custom['bot'] )
		{
			$path = $this->getPath();
			foreach( $this->custom['bot'] as $callable )
				\call_user_func($callable, $this->getId(), $path);
		}
	}

	public function allowSubmit()
	{
		return $this->hasField() && !(isset($this->data['nosave']) && boolval($this->data['nosave']));
	}

	/** Deepest displaying step, show groups in a form. */
	private function echoForm()
	{
		$formAttrs = \apply_filters('lws_adminpanel_form_attributes'.$this->id, array(
			'method' => 'post',
			'action' => $this->action,
		));
		$attrs = '';
		foreach($formAttrs as $k => $v)
		{
			$v = \esc_attr($v);
			$attrs .= " $k='$v'";
		}

		$path = $this->getPathAsString();
		// form is required with 'tab' to know where we are
		echo "<form id='{$this->id}' {$attrs}><input type='hidden' name='tab' value='{$path}'>";

		// let WordPress register the page fields
		\settings_fields($this->id);

		$extraClass= ($this->vertnav) ? ' has-vertnav' : '';
		echo "<div class='groups-grid$extraClass'>";
		foreach( $this->groups as $Group )
			$Group->eContent();

		echo "</div></form>";
	}

	/** @return an array of Field instances. */
	public function getFields()
	{
		$f = array();
		foreach( $this->groups as $Group )
				$Group->mergeFields($f);
		return $f;
	}

	protected function hasField()
	{
		foreach( $this->groups as $Group )
		{
			if( $Group->hasFields(true) )
				return true;
		}
		return false;
	}

	protected function hasGroup()
	{
		return !empty($this->groups);
	}

	public function getGroups(){
		if($this->hasGroup())
		{
			return $this->groups;
		}
		return false;
	}

	/** Notify settings well saved */
	public function noticeSettingsSaved($value)
	{
		if( $value && isset($_POST['option_page']) && $_POST['option_page'] == $this->id )
		{
			$val = array_merge(array('code'=>'', 'type'=>'', 'message'=>''), \current($value));
			if( 'settings_updated' == $val['code'] && \in_array($val['type'], array('updated', 'success')) )
			{
				// transiant/fleeting notice
				\lws_admin_add_notice_once(
					'lws_ap_page',
					$val['message'] ? $val['message'] : __("Your settings have been saved.", 'lws-adminpanel'),
					array('level'=>'success')
				);
			}
		}
		return $value;
	}

	protected function createGroups($data, $path=array())
	{
		while( $data )
		{
			if( isset($data['groups']) && $data['groups'] )
			{
				foreach($data['groups'] as $group)
				{
					$this->groups[] = new \LWS\Adminpanel\Pages\Group($group, $this->getId());
				}
			}

			if( $path )
				$data = $this->getNextLevel($data, array_shift($path));
			else
				$data = false;
		}
	}
}
