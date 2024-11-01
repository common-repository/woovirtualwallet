<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();


class CSSButton extends \LWS\Adminpanel\Pages\FieldCSSGroup
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
		$this->font->eFields(true);
		echo "<div class='lwss-input-button-toggle lwss-toggle-bloc' tab-index='0' data-target='.lwss-button-border'></div>";
		echo "<div class='lwss-input-button-toggle lwss-toggle-states' tab-index='0' data-target='.lwss-button-hover'>";
		echo "<div class='lwss-toggle-states-N'>N</div>";
		echo "<div class='lwss-toggle-states-O'>O</div>";
		echo "<div class='lwss-toggle-states-F'>F</div>";
		echo "</div>";
		echo "</div>";

		echo "<div style='display:none' class='lwss-input-tab lwss-input-popup lwss-button-border lwss-fold-on-clic-out'>";
		$this->border->eHighlightZone();
		$this->eVSeparator();
		$this->border->eFields(true);
		echo "</div>";

		echo "<div style='display:none' class='lwss-input-tab lwss-input-popup lwss-button-hover lwss-fold-on-clic-out'>";
		$this->eStateFields();
		echo "</div>";

		echo "</div>";
		$this->eHSeparator('513');
		echo "<div class='lwss-button-demo-stack'>";
		$this->border->eDemoZone('lwss-font-example lwss-button-example lwss-disable-on-clic-out');
		echo "</div></div>";
	}

	protected function eStateFields()
	{
		$trad = array(
			_x("Normal", "LWSS button colors", 'lws-adminpanel'),
			_x("Over", "LWSS button colors", 'lws-adminpanel'),
			_x("Focused", "LWSS button colors", 'lws-adminpanel'),
			_x("Text", "LWSS button colors", 'lws-adminpanel'),
			_x("Background", "LWSS button colors", 'lws-adminpanel')
		);
		echo "<div class='lwss-absolute-background-normal'></div>";
		echo "<div class='lwss-absolute-background-over'></div>";
		echo "<div class='lwss-absolute-background-focused'></div>";
		echo "<div class='lwss-absolute-background-text'></div>";
		echo "<div class='lwss-absolute-background-background'></div>";
		echo "<div class='lwss-button-color-table'>";
		echo "<div class='lwss-button-color-line lwss-button-color-head'>";
		echo "<div class='lwss-button-color-cell lwss-button-color-empty'></div>";
		echo "<div class='lwss-button-color-cell lwss-button-color-title'>{$trad[0]}</div>";
		echo "<div class='lwss-button-color-cell lwss-button-color-title'>{$trad[1]}</div>";
		echo "<div class='lwss-button-color-cell lwss-button-color-title'>{$trad[2]}</div>";
		echo "</div>";

		echo "<div class='lwss-button-color-line'>";
		echo "<div class='lwss-button-color-cell lwss-button-color-title'>{$trad[3]}</div>";
		$this->eEditColor('color', _x('Normal Text', 'CSS color edition', 'lws-adminpanel'));
		$this->eEditColor('hover-color', _x('On Hover Text', 'CSS color edition', 'lws-adminpanel'));
		$this->eEditColor('active-color', _x('On Click Text', 'CSS color edition', 'lws-adminpanel'));
		echo "</div>";

		echo "<div class='lwss-button-color-line'>";
		echo "<div class='lwss-button-color-cell lwss-button-color-title'>{$trad[4]}</div>";
		$this->eEditColor('background-color', _x('Normal Background', 'CSS color edition', 'lws-adminpanel'));
		$this->eEditColor('hover-background-color', _x('On Hover Background', 'CSS color edition', 'lws-adminpanel'));
		$this->eEditColor('active-background-color', _x('On Click Background', 'CSS color edition', 'lws-adminpanel'));
		echo "</div>";

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
