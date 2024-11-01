<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();

/* DEPRECATED */

/** A field to edit a CSS color element.
 * assume css values are in extra['values'] @see CSSSection::details(false) */
class Color extends \LWS\Adminpanel\Pages\Field
{
	public function input()
	{
		$this->analys();
		self::eColorPicker($this->prop, $this->value, $this->dft, $this->getExtraValue('source'), $this->m_Id);
	}

	public static function eColorPicker($prop, $value, $dft, $source, $name='')
	{
		$container = (!empty($name) ? " lwss-css-inputs" : ""); // named input works alone.
		$source = (!empty($source) ? " data-source='$source'" : "");
		$mix = (!empty($value) ? $value : $dft);
		$merge = '';
		$cssprop = '';
		if( !empty($prop) )
		{
			$cssval = "$prop:$mix";
			$merge = " class='lwss-merge-css'";
			$cssprop = " data-css='$prop'";
		}
		else
			$cssval = $mix;

		$str = "<div class='lwss-btn-color-selector-container lwss-color-selector-relative$container' tabindex='0'>";
		$str .= "<div class='lwss-btn-color-selector'>";
		$str .= "<div class='lwss-btn-color-demo'$cssprop$source data-lwss='$dft' style='background-color:$mix'></div>";
		$str .= "<div class='lwss-btn-color-value'>$mix</div>";
		$str .= "<input name='$name'$merge type='hidden' value='$cssval'/>";
		$str .= "</div></div>";
		echo $str;
	}

	/** Fill $this->prop,  $this->dft,  $this->value
	 *	@return the color value. */
	protected function analys()
	{
		$this->prop = '';
		$this->value = '';
		if( isset($this->extra['values']) )
		{
			foreach($this->extra['values'] as $k => $v)
			{
				if( false !== stripos($k, 'color') )
				{
					$this->prop = esc_attr($k);
					$this->value = esc_attr($v);
					break;
				}
			}
		}
		else
		{
			$this->value = esc_attr(get_option( $this->m_Id, '' ));
			$ar = explode(':', $this->value, 2);
			if( count($ar) > 1 )
			{
				$this->prop = esc_attr($ar[0]);
				$this->value = esc_attr($ar[1]);
			}
		}
		$this->analysDefaults();
		return $this->value;
	}

	protected function analysDefaults()
	{
		if( !isset($this->prop) ) $this->prop = '';
		$this->dft = 'rgba(128,128,128,1)';
		$dftKey = isset($this->extra['defaults']) ? 'defaults' : 'default';

		if( isset($this->extra[$dftKey]) )
		{
			if( is_array($this->extra[$dftKey]) )
			{
				if( !empty($this->prop) )
				{
					if( isset($this->extra[$dftKey][$this->prop]) )
						$this->dft = esc_attr($this->extra[$dftKey][$this->prop]);
				}
				else foreach($this->extra[$dftKey] as $k => $v)
				{
					if( false !== stripos($k, 'color') )
					{
						$this->prop = esc_attr($k);
						$this->dft = esc_attr($v);
						break;
					}
				}
			}else if( is_string($this->extra[$dftKey]) )
			{
				$this->dft = $this->extra[$dftKey];
				$ar = explode(':', $this->dft, 2);
				if( count($ar) > 1 )
				{
					$this->prop = esc_attr($ar[0]);
					$this->dft = esc_attr($ar[1]);
				}
			}
		}
	}
}
