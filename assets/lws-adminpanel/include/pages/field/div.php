<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();


class Div extends \LWS\Adminpanel\Pages\FieldCSSGroup
{
	public function __construct($id='', $title='', $extra=null)
	{
		parent::__construct($id, $title, $extra);
		$this->border = new \LWS\Adminpanel\Pages\Field\Border('', '', $extra);
		$this->font = new \LWS\Adminpanel\Pages\Field\Font('', '', $extra);
	}

	protected function cssPairs()
	{
		return array(
			'color' => '#FFFFFF',
			'background-color' => '#3f89c5',
			'hover-color' => '#000000',
			'hover-background-color' => '#A0A0A0',
			'active-color' => '#FFFFFF',
			'active-background-color' => '#000000'
		);
	}

	public function input()
	{
		$this->readExtraValues();
		$this->border->readExtraValues();
		$this->font->readExtraValues();
		$val = $this->border->mergedProps($this->font->saved);

		echo "<div class='lwss-css-editor'>";
		echo "<div class='lwss-bloc-inputs lwss-css-inputs'><input type='hidden' name='{$this->m_Id}' class='lwss-merge-css' $val>";

		echo "<div class='lwss-input-tab lwss-button-font'>";
		$this->font->eFields();
		echo "<div class='lwss-input-button-toggle lwss-toggle-bloc' tab-index='0' data-target='.lwss-button-border'></div>";
		echo "</div>";

		echo "<div style='display:none' class='lwss-input-tab lwss-input-popup lwss-button-border lwss-fold-on-clic-out'>";
		$this->border->eHighlightZone();
		$this->border->eSeparator('527');
		$this->border->eFields();
		echo "</div>";

		echo "</div>";
		$this->font->eSeparator('527');
		$this->border->eDemoZone('lwss-font-example lwss-button-example lwss-disable-on-clic-out');
		echo "</div>";
	}

	public function eEditColor($prop, $label)
	{
		$value = esc_attr($this->values[$prop]);
		$dft = esc_attr($this->defaults[$prop]);
		echo "<div class='lwss-button-color-cell'>";
		\LWS\Adminpanel\Pages\Field\Color::eColorPicker($prop, $value, $dft, $this->source);
		echo "</div>";
	}
}

?>
