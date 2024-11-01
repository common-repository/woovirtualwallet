<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();


/** in extra, 'options' is an associative array of {value => label}
 * in extra, if 'notnull' is set to true, no empty option is prepend to the option list. */
class Select extends \LWS\Adminpanel\Pages\Field
{
	protected function dft(){ return array('options'=>array(), 'notnull'=>false); }

	public function input()
	{
		$name = $this->m_Id;
		$value = get_option($name, false);

		if( $value === false )
		{
			if( $this->hasExtra('value') )
				$value = $this->extra['value'];
			else if( $this->hasExtra('default') )
				$value = $this->extra['default'];
		}

		$id = isset($this->extra['id']) ? (" id='".\esc_attr($this->extra['id'])."'") : '';
		$disabled = $this->getExtraValue('disabled', false) ? ' disabled' : '';
		$readonly = $this->getExtraValue('readonly', false) ? ' readonly' : '';
		$maxwidth = $this->getExtraValue('maxwidth', false) ? ' data-maxwidth="' . $this->getExtraValue('maxwidth') . '"' : '';
		$placeholder = $this->getExtraValue('placeholder', false) ? ' data-placeholder="' . $this->getExtraValue('placeholder') . '"' : '';

		echo "<select name='$name' class='{$this->style} lac_select'$id$disabled$readonly$maxwidth$placeholder>";
		if( !$this->extra['notnull'] )
			echo "<option value=''></option>";
		if( !empty($this->extra['options']) && is_array($this->extra['options']) )
		{
			foreach( $this->extra['options'] as $key => $label )
			{
				$selected = ($value == $key) ? "selected='selected'" : "";
				echo "<option value='$key' $selected>$label</option>";
			}
		}
		echo "</select>";
	}
}

?>
