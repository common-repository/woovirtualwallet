<?php
namespace LWS\Adminpanel\Pages;
if( !defined( 'ABSPATH' ) ) exit();


abstract class FieldCSSGroup extends \LWS\Adminpanel\Pages\Field
{
	protected abstract function cssPairs();

	public function readExtraValues()
	{
		$this->saved = isset($this->extra['values']) ? $this->extra['values'] : array();
		$this->defaults = isset($this->extra['defaults']) ? $this->extra['defaults'] : array();
		$this->defaults = \wp_parse_args($this->defaults, $this->cssPairs());
		$this->values = array_merge($this->defaults, $this->saved);
		$this->source = array_key_exists('source', $this->extra) ? $this->extra['source'] : '';
	}

	public function eHSeparator($width=null)
	{
		$w = is_null($width) ? '' :  " style='width:{$width}px'";
		echo "<div class='lwss-font-hor-sep'$w></div>";
	}

	public function eVSeparator($height=null)
	{
		$h = is_null($height) ? '' :  " style='height:{$height}px'";
		echo "<div class='lwss-bloc-vertical-separator'$h></div>";
	}

	/** @return an html property with saved values for this field. */
	public function mergedProps($other=array())
	{
		$props = array();
		foreach($this->saved as $k => $v)
			$props[] = "$k:$v";
		if( !empty($other) && is_array($other) ){
			foreach($other as $k => $v)
				$props[] = "$k:$v";
		}
		$val = esc_attr(implode(';', $props));
		return "value='$val'";
	}
}
