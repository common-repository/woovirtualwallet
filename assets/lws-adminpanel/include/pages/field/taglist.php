<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();


class TagList extends \LWS\Adminpanel\Pages\Field
{
	public function __construct($id, $title, $extra=null)
	{
		parent::__construct($id, $title, $extra);
		add_action('admin_enqueue_scripts', array($this, 'script'));
		add_action("pre_update_option_{$id}", array( $this, 'formatBeforeSave'), 10, 3);
	}

	protected function dft(){ return array('rows' => 15, 'cols' => 80); }

	public function input()
	{
		$name = $this->m_Id;
		$value = htmlspecialchars(implode(';', get_option($this->m_Id, array())));
		echo "<input type='hidden' class='{$this->style} lws_taglist' name='$name' value='$value'/ data-btlabel=".__("Add", 'lws-adminpanel').">";
	}

	public function script()
	{
		wp_enqueue_script('lws-field-taglist', LWS_ADMIN_PANEL_JS.'/controls/taglist.js', array(), LWS_ADMIN_PANEL_VERSION, true);
	}

	public function formatBeforeSave($value, $old_value, $option)
	{
		$ar = explode(';', $value);
		foreach($ar as &$v) $v = trim($v);
		return $ar;
	}
}

?>
