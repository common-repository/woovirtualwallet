<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();


class Hidden extends \LWS\Adminpanel\Pages\Field
{
	public function __construct($id='', $title='', $extra=null)
	{
		if( is_array($extra) )
			$extra['hidden'] = true;
		else
			$extra = array('hidden' => true);
		parent::__construct($id, $title, $extra);
	}

	public function input()
	{
		$name = $this->m_Id;
		$value = $this->readOption();
		$id = $this->getExtraAttr('id', 'id');

		$class = array('lws-input-hidden');
		if( $this->style )
			$class[] = $this->style;
		if( $c = $this->getExtraValue('class') )
			$class[] = $c;
		$class = implode(' ', $class);

		echo "<input class='{$class}' type='hidden' name='$name' value='$value' {$id}/>";
	}
}
