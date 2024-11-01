<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();

class Checkbox extends \LWS\Adminpanel\Pages\Field
{
	public function input()
	{
		$name = $this->m_Id;
		$value = '';
		$option = false;

		if( isset($this->extra['value']) )
		{
			$option = boolval($this->extra['value']);
		}
		else if( isset($this->extra['checked']) )
		{
			$option = boolval($this->extra['checked']);
		}
		else
		{
			$option = get_option($this->m_Id, false);
			if( $option === false && $this->hasExtra('default') )
				$option = boolval($this->extra['default']);
		}
		if( $option )
			$value = "checked='checked'";

		$class = $this->style;
		if( isset($this->extra['class']) && is_string($this->extra['class']) && !empty($this->extra['class']) )
			$class = (empty($class) ? '' : ' ') . $this->extra['class'];

		$data = '';
		if( isset($this->extra['data']) && is_array($this->extra['data']) )
		{
			foreach( $this->extra['data'] as $k=>$v )
				$data .= " data-$k='$v'";
		}

		$disabled = '';
		if( $this->getExtraValue('disabled', false) )
			$disabled = "  disabled onclick='return false;'";

		$id = $this->getExtraAttr('id', 'id');
		echo "<input class='$class' type='checkbox' name='$name' $value$data$disabled$id />";
	}
}
