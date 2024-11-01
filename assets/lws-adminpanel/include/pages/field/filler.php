<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();


/** only add a simple vertical white space. */
class Filler extends \LWS\Adminpanel\Pages\Field
{
	protected function dft(){ return array('height' => '128px'); }

	public function __construct($id='', $title='', $extra=null)
	{
		parent::__construct($id, $title, $extra);
		$this->gizmo = true;
	}

	public function input()
	{
		$height = $this->extra['height'];
		echo "<div class='' style='height:$height'></div>";
	}
}

?>
