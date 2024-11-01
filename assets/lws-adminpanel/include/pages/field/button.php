<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();

/** Expect a 'callback' entry in extra which refers to a callable.
 * The callback get two arguments: button_id, array with all inputs of the group (id => value).
 * This callback should return false for failure.
 *
 *	A button can be a link to.
 *	All input in the same group will be send.
 *	Expect a 'link' entry in extra with an array with one item in it:
 * * ['post' => 'https://full-url.com'] or
 * * ['ajax'  => 'ajax-action-only']
 *
 * All button can be embeded inside a container by setting a extra['container'] = array('tag'=>'span', 'class'=>'my_css_class')
 *
 * On success, a string can be returned which will be displayed on html after the button. */
class Button extends \LWS\Adminpanel\Pages\Field
{
	public function __construct($id='', $title='', $extra=null)
	{
		parent::__construct($id, $title, $extra);
		$this->gizmo = true;
	}

	public function label()
	{
		if( isset($this->extra['text']) )
			return parent::label();
		else
			return ""; /// title will be used as button text
	}

	public function title()
	{
		if( isset($this->extra['text']) )
			return parent::title();
		else
			return ""; /// title will be used as button text
	}

	public function input()
	{
		$text = esc_attr($this->getExtraValue('text', $this->m_Title));
		$class = (isset($this->extra['class']) && is_string($this->extra['class']) ? " {$this->extra['class']}" : '');

		$triggable = (isset($this->extra['callback']) && is_callable($this->extra['callback']));
		if( $triggable )
			$class .= ' lws_adm_btn_trigger';

		$attrs = '';
		$submit = $this->getExtraValue('link');
		if( $submit )
		{
			$class .= ' lws_adm_btn_group_submit';

			if( \is_array($submit) )
			{
				$method = \array_keys($submit)[0];
				$action = $submit[$method];
				$method = \strtolower($method);
				if( 'ajax' == $method )
					$attrs = sprintf(' data-method="get" data-action="%s"', \esc_attr($action));
				else
					$attrs = sprintf(' data-method="post" data-action="%s"', \esc_attr($action));
			}
			else
				$attrs = sprintf(' data-method="post" data-action="%s"', \esc_attr($submit));
		}

		if( $triggable || $submit )
			$class .= ' lws-adm-btn-trigger';

		$tag = 'span';
		if( isset($this->extra['container']) )
		{
			$cc = '';
			if( is_array($this->extra['container']) )
			{
				if( isset($this->extra['container']['tag']) )
					$tag = $this->extra['container']['tag'];
				if( isset($this->extra['container']['class']) )
					$cc = $this->extra['container']['class'];
			}
			else
				$cc = $this->extra['container'];
			echo "<$tag class='$cc'>";
		}

		echo "<div class='lws-adm-btn$class' id='{$this->m_Id}' type='button'{$attrs}>$text</div>";
		if( $triggable || $submit ) // answer zone
			echo "<div class='lws-adm-btn-trigger-response'></div>";

		if( isset($this->extra['container']) )
			echo "</$tag>";
	}
}
