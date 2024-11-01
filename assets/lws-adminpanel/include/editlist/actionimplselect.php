<?php
namespace LWS\Adminpanel\EditList;
if( !defined( 'ABSPATH' ) ) exit();


/** A default implementation to display a select.
 * You provide select content to constructor.
 * action is performed by a CALLABLE you must provide. */
class ActionImplSelect extends Action
{
	/** Dispose a <select> filled with the given $choices.
	 * At validation, call the given $callback.
	 * @param $choices is array of(value => text)
	 * @param $callback is a php CALLABLE which accept 3 arguments: $uid, select.value, selected items */
	function __construct($uid, $choices, $callback)
	{
		parent::__construct($uid);
		$this->choices = array();
		if( is_array($this->choices) )
		{
			foreach($choices as $v => $t )
				$this->choices[esc_attr($v)] = sanitize_text_field($t);
		}
		$this->callback = $callback;
	}

	function input()
	{
		$str = "<select name='{$this->UID}' class='lws-ignore-confirm'>";
		if( is_array($this->choices) )
		{
			foreach( $this->choices as $v => $t )
				$str .= "<option value='$v'>$t</option>";
		}
		$str .= "</select>";
		return $str;
	}

	function apply( $itemsIds )
	{
		if( isset($_POST[$this->UID]) && is_array($this->choices) )
		{
			$action = esc_attr(\sanitize_text_field($_POST[$this->UID]));
			if( isset($this->choices[$action]) && $this->callback != null && is_callable($this->callback) )
			{
				call_user_func( $this->callback, $this->UID, $action, $itemsIds );
				return true;
			}
		}
		return false;
	}
}
