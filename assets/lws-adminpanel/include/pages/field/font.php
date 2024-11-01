<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();


class Font extends \LWS\Adminpanel\Pages\FieldCSSGroup
{
	/// default css values
	protected function cssPairs()
	{
		return array(
			'font-size' => '10pt',
			'font-weight' => 'normal',
			'font-family' => 'Open Sans',
			'color' => '#000000',
			'text-decoration' => 'none',
			'font-style' => 'normal',
			'text-transform' => 'none'
		);
	}

	protected function eOptions($value, $values)
	{
		$value = strtolower($value);
		foreach( $values as $opt => $txt )
		{
			$opt = strtolower(esc_attr($opt));
			$sel = ($value == $opt ? ' selected="selected"' : '');
			echo "<option value='$opt'$sel>$txt</option>";
		}
	}

	protected function eSize()
	{
		$prop = 'font-size';
		if( array_key_exists($prop, $this->values) )
		{
			$value = esc_attr($this->values[$prop]);
			$dft = esc_attr($this->defaults[$prop]);
			$match = array();
			preg_match('#(\d+(\.\d+)?)([a-zA-Z]+)#' , $value , $match);
			$num = !empty($match) ? $match[1] : '';
			$unit = !empty($match) ? $match[2] : '';
			preg_match('#(\d+)([a-zA-Z]+)#' , $dft , $match);
			$dnum = !empty($match) ? $match[1] : '';
			$dunit = !empty($match) ? $match[2] : '';

			echo "<div class='lwss-text-size-select'><input type='hidden' data-css='$prop' data-lwss='$dft' data-source='{$this->source}' value='$value'/>";
			echo "<input type='text' class='lwss-text-size-value lws-font-size-part' data-lwss='$dnum' data-source='{$this->source}' data-part='val' value='$num'/>";
			echo "<select class='lws-select-input lws-font-size-part lwss-unit-sel' data-lwss='$dunit' data-source='{$this->source}' data-part='unit' data-class='lwss-unit-sel lwss-default-sel lws-font-input'>";
			$this->eOptions($unit, $this->getSize());
			echo "</select>";
			echo "</div>";
		}
	}

	protected function eToggle($prop, $values, $classes, $text='')
	{
		if( array_key_exists($prop, $this->values) )
		{
			$value = esc_attr($this->values[$prop]);
			$dft = esc_attr($this->defaults[$prop]);
			$text = esc_attr($text);
			$str = '';
			foreach( $values as $k => $v )
				$str .= " data-$k='$v'";
			echo "<input class='lws-src-btn-toggle'$str data-text='$text' data-class='$classes' data-css='$prop' data-lwss='$dft' data-source='{$this->source}' value='$value'/>";
		}
	}

	public function input()
	{
		$this->readExtraValues();
		$val = $this->mergedProps();

		echo "<div class='lwss-css-editor'>";
		echo "<div class='lwss-font-inputs lwss-css-inputs'><input type='hidden' name='{$this->m_Id}' class='lwss-merge-css' $val>";
		$this->eFields();
		echo "</div>";
		$this->eSeparator('483');
		// a demo here
		echo "<div class='lwss-font-example-container'>";
		$demo = _x("This is a sample text", "LWSS font text demo", 'lws-adminpanel');
		echo "<div class='lwss-font-example lwss-css-example'>$demo</div>";
		echo "</div>";
	}

	public function eSeparator($width)
	{
		parent::eHSeparator($width);
	}

	public function eFields($noColor=false)
	{
		echo "<div class='lwss-fontname-group'><div class='lwss-fontname-select' tabindex='0'>";
		$vals = array($this->values['font-family'], $this->values['font-weight']);
		$dfts = array($this->defaults['font-family'], $this->defaults['font-weight']);
		echo "<input class='lwss-fontselect-family {$this->style}' data-css='font-family' data-lwss='{$dfts[0]}' data-source='{$this->source}' value='{$vals[0]}'>";
		echo "<input class='lwss-fontselect-weight {$this->style}' data-css='font-weight' data-lwss='{$dfts[1]}' data-source='{$this->source}' value='{$vals[1]}'>";
		echo "</div></div>";

		$this->eSize();
		$this->eToggle('font-style', array('off'=>'normal','on'=>'italic'), 'lws-font-input lwss-font-btn lwss-font-style lws-icon lws-icon-italic ');
		$this->eToggle('text-decoration', array('off'=>'none','on'=>'underline'), 'lws-font-input lwss-font-btn lwss-font-decoration lws-icon lws-icon-underline');
		$this->eToggle('text-transform', array('off'=>'none','on'=>'uppercase'), 'lws-font-input lwss-font-btn lwss-font-transform lws-icon lws-icon-caps-small');
		if( $noColor !== true && array_key_exists('color', $this->values) )
			\LWS\Adminpanel\Pages\Field\Color::eColorPicker('color', esc_attr($this->values['color']), esc_attr($this->defaults['color']), array_key_exists('source', $this->extra) ? $this->extra['source'] : '');
	}

	// returns all Size available to show on select
	protected function getSize()
	{
		return array('px'=>'px', 'em'=>'em', 'rem'=>'rem', 'pt'=>'pt', 'mm'=>'mm');
	}
}

?>
