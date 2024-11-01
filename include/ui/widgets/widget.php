<?php
namespace LWS\WOOVIRTUALWALLET\Ui\Widgets;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Provide a base class to display widget. */
class Widget extends \WP_Widget
{
	static protected function register($className)
	{
		\add_action('widgets_init', function()use($className){\register_widget($className);});
	}

	/** echo a form select line @param $options (array) value=>text */
	protected function eFormFieldSelect($id, $label, $name, $options, $value)
	{
		$input = "<select id='$id' name='$name'>";
		foreach( $options as $v => $txt )
		{
			$selected = $v == $value ? ' selected' : '';
			$input .= "<option value='$v'$selected>$txt</option>";
		}
		$input .= "</select>";
		$this->eFormField($id, $label, $input);
	}

	/** echo a form text line */
	protected function eFormFieldText($id, $label, $name, $value, $placeholder='', $type='text')
	{
		$input = "<input class='widefat' id='$id' name='$name' type='{$type}' value='$value' placeholder='$placeholder'/>";
		$this->eFormField($id, $label, $input);
	}

	/** echo a form radio line */
	protected function eFormFieldRadio($id, $label, $name, $options, $value)
	{
		$input = '';
		foreach( $options as $v => $txt )
		{
			$selected = $v == $value ? ' checked' : '';
			$input .= "<input type='radio' style='margin:0 5px 0 15px;' name='$name' value='$v'$selected>$txt";
		}
		$this->eFormField($id, $label, $input);
	}

	/** echo a form entry line */
	protected function eFormField($id, $label, $input)
	{
		echo "<p><label for='$id'>$label</label>$input</p>";
	}
}
