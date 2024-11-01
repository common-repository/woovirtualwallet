<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();


/** extra = array(
					'rows' => 15,
					'cols' => 80,
				); */
class TextArea extends \LWS\Adminpanel\Pages\Field
{
	protected function dft(){ return array('rows' => 15, 'cols' => 80); }

	public function input()
	{
		$name = $this->m_Id;
		$value = htmlspecialchars($this->readOption(false));
		$ph = $this->getExtraAttr('placeholder', 'placeholder');

		$required = (isset($this->extra['required']) && boolval($this->extra['required'])) ? ' required' : '';
		$disabled = $this->getExtraValue('disabled', false) ? ' disabled' : '';
		$readonly = $this->getExtraValue('readonly', false) ? ' readonly' : '';
		$id = $this->getExtraAttr('id', 'id');

		$class = $this->style;
		if( isset($this->extra['class']) && is_string($this->extra['class']) && $this->extra['class'] )
			$class = ($class ? ' ' : '') . $this->extra['class'];

		echo "<textarea class='{$class}'{$ph} rows='{$this->extra['rows']}' cols='{$this->extra['cols']}' name='{$name}'{$required}{$disabled}{$readonly}{$id}>{$value}</textarea>";
	}
}
