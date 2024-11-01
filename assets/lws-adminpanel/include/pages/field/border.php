<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();


/** A group of input to edit a CSS border element.
 * assume css values are in extra['values'] @see CSSSection::details(false) */
class Border extends \LWS\Adminpanel\Pages\FieldCSSGroup
{
	/// default css values
	protected function cssPairs()
	{
		return array(
			'margin' => '3px',
			'padding' => '3px',
			'border-width' => '0px',
			'border-radius' => '0px',
			'border-color' => '#00000000',
			'background-color' => '#FFFFFF00'
		);
	}

	protected function tooltipsMargin()
	{
		return _x("1 to 4 values accepted<br/>
<p class='lwss-tooltip-exemple'>5px | Sets a global margin of 5 pixels</p>
<p class='lwss-tooltip-exemple'>1px 2px 3px 4px | Sets top, right, bottom, left margins</p>",
			'CSS margin tooltip', 'lws-adminpanel');
	}

	protected function tooltipsPadding()
	{
		return _x("1 to 4 values accepted<br/>
<p class='lwss-tooltip-exemple'>5px | Sets a global padding of 5 pixels</p>
<p class='lwss-tooltip-exemple'>1px 2px 3px 4px | Sets top, right, bottom, left padding</p>",
			'CSS padding tooltip', 'lws-adminpanel');
	}

	protected function tooltipsBorderSize()
	{
		return _x("1 to 4 values accepted<br/>
<p class='lwss-tooltip-exemple'>5px | Sets a global border size of 5 pixels</p>
<p class='lwss-tooltip-exemple'>1px 2px 3px 4px | Sets top, right, bottom, left borders</p>",
			'CSS border tooltip', 'lws-adminpanel');
	}

	protected function tooltipsBorderRadius()
	{
		return _x("1 to 4 values accepted<br/>
<p class='lwss-tooltip-exemple'>5px | Sets a global border radius of 5 pixels</p>
<p class='lwss-tooltip-exemple'>1px 2px 3px 4px | Sets top, right, bottom, left border radius</p>",
			'CSS radius tooltip', 'lws-adminpanel');
	}

	public function input()
	{
		$this->readExtraValues();

		echo "<div class='lwss-css-editor'>";
		$this->eHighlightZone();
		$this->eSeparator();
		$val = $this->mergedProps();
		echo "<div class='lwss-bloc-inputs lwss-bloc-standard-input lwss-css-inputs'><input type='hidden' name='{$this->m_Id}' class='lwss-merge-css' $val>";
		$this->eFields();
		echo "</div>";
		parent::eHSeparator(470);
		$this->eDemoZone();
		echo "</div>";
	}

	public function eFields($noBGColor=false)
	{
		$this->eEditSize('margin', _x('Margin', 'CSS border size edition', 'lws-adminpanel'),
			$this->tooltipsMargin());
		$this->eEditSize('padding', _x('Padding', 'CSS border size editoin', 'lws-adminpanel'),
			$this->tooltipsPadding());
		$this->eEditSize('border-width', _x('Border', 'CSS border size editoin', 'lws-adminpanel'),
			$this->tooltipsBorderSize());
		$this->eEditSize('border-radius', _x('Radius', 'CSS border size editoin', 'lws-adminpanel'),
			$this->tooltipsBorderRadius());
		$this->eEditColor('border-color', _x('Border Color', 'CSS color edition', 'lws-adminpanel'));
		if( $noBGColor !== true )
			$this->eEditColor('background-color', _x('Background', 'CSS color edition', 'lws-adminpanel'));
	}

	protected function br(&$br)
	{
		if( !isset($this->br) )
			$this->br = 0;
		return ((++$this->br % 2 == 0)?'<br/>':'');
	}

	protected function eEditSize($prop, $label, $tooltip='')
	{
		if( array_key_exists($prop, $this->values) )
		{
			$value = esc_attr($this->values[$prop]);
			$dft = esc_attr($this->defaults[$prop]);
			$append = $this->br($br);
			$tooltip = esc_attr($tooltip);

			echo "<div class='lwss-hl-width lwss-$prop-editor'>";
			echo "<label class='lwss-bloc-label'>$label</label>";
			if( !empty($tooltip) )
				echo "<div class='lwss-tooltip-container'>";
			echo "<input type='text' data-css='$prop' data-lwss='$dft' data-source='{$this->source}' class='lwss-text-with-tooltip' value='$value' />";
			if( !empty($tooltip) )
				echo "<div class='lwss-tooltip' data-tooltip='$tooltip'></div></div>";
			echo "</div>$append";
		}
	}

	public function eEditColor($prop, $label, $values=null, $defaults=null)
	{
		if( is_null($values) ) $values = $this->values;
		if( is_null($defaults) ) $defaults = $this->defaults;
		if( array_key_exists($prop, $values) )
		{
			$value = esc_attr($values[$prop]);
			$dft = esc_attr($defaults[$prop]);
			$append = $this->br($br);
			echo "<div class='lwss-hl-width lwss-$prop-editor'>";
			echo "<label class='lwss-bloc-label'>$label</label>";
			echo "<div class='lwss-bloc-colorpicker'>";
			\LWS\Adminpanel\Pages\Field\Color::eColorPicker($prop, $value, $dft, $this->source);
			echo "</div></div>$append";
		}
	}

	public function eSeparator()
	{
		parent::eVSeparator();
	}

	/// a div to show to user what he is editing
	public function eHighlightZone()
	{
		?><div class="lwss-bloc-highlight"><div class="lwss-bloc-demo"><div class="lwss-bloc-demo-inside">&nbsp;</div></div><?php
		foreach( array('margin', 'padding', 'border-width', 'border-radius') as $zone )
		{
			foreach( array('u', 'd', 'l', 'r') as $side )
			{
				$orientation = ($side == 'u' || $side == 'd') ? 'v' : 'h';
				echo "<div class='lwss-bloc-mark lwss-bloc-mark-$side lwss-bloc-mark-$orientation lwss-bloc-mark-$zone'></div>";
			}
		}
		?></div><?php
	}

	/// a div to represent the edition
	public function eDemoZone($addClass="")
	{
		if( !empty($addClass) && substr($addClass, 0, 1) != ' ' )
			$addClass = ' ' . $addClass;
		$demo = _x("This is a sample text", "LWSS border text demo", 'lws-adminpanel');
		echo "<div class='lwss-bloc-example-container'>";
		echo "<div class='lwss-bloc-example lwss-css-example$addClass'>$demo</div></div>";
	}

}

?>
