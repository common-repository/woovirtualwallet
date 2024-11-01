<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();


class Slug extends \LWS\Adminpanel\Pages\Field
{
	public function input()
	{
		$name = $this->m_Id;
		$value = $this->readOption();
		echo "<input class='{$this->style} lws-input-slug' type='text' pattern='[a-z0-9]+(-[a-z0-9]+)*' name='$name' value='$value' placeholder='slug' />";
	}
}

?>
