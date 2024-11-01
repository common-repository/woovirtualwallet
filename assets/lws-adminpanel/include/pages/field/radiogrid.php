<?php
namespace LWS\Adminpanel\Pages\Field;

if (!defined('ABSPATH')) {
    exit();
}


/** Designed to be used inside Wizard only.
 * Behavior is similar to a radio,
 * But choices looks like tiles with a grid layout. */
class RadioGrid extends \LWS\Adminpanel\Pages\Field
{

	/** @return field html. */
	public static function compose($id, $extra=null)
	{
		$me = new self($id, '', $extra);
		return $me->html();
	}

	public function input()
	{
		echo $this->html();
	}

	public function html()
	{
		\wp_enqueue_script('lws-adm-radiogrid', LWS_ADMIN_PANEL_JS.'/controls/radiogrid.js', array('jquery'), LWS_ADMIN_PANEL_VERSION, true);
		//\wp_enqueue_style('lws-adm-radiogrid', LWS_ADMIN_PANEL_CSS.'/controls/radiogrid.css', array(), LWS_ADMIN_PANEL_VERSION);

		$name = \esc_attr($this->id());
		$value = $this->readOption(true);
		$source = $this->getExtraValue('source', array());
		$html = "<input id='{$name}' name='{$name}' value='{$value}' type='hidden'>";

		if( 'large' == $this->getExtraValue('type') )
		{
			foreach( $source as $opt ) {
				$opt = array_merge(
					array(
						'value'    => '',
						'img'      => '',
						'color'    => '',
						'texts'    => array(),
						'labels'   => array(),
					),
					$opt
				);
				$opt['texts'] = array_merge(
					array(
						'title',
						'descr',
					),
					$opt['texts']
				);
				$selected = ($opt['value'] == $value ? ' selected' : '');
				foreach (array('value', 'img') as $attr) {
					$opt[$attr] = \esc_attr($opt[$attr]);
				}

				$style = '';
				if (isset($opt['color']) && $opt['color'] != '') {
					$colorstring = \lws_get_theme_colors('--grid-item-color', $opt['color']);
					$style = " style='$colorstring'";
				}
				$html .=<<<EOT
	<div class="radiogrid-large-container lws_radiobutton_radio{$selected}"$style data-input='#{$name}' data-value='{$opt['value']}'>
		<div class="image"><img src='{$opt['img']}'/></div>
		<div class="text">
			<div class="title">{$opt['texts']['title']}</div>
			<div class="desc">{$opt['texts']['descr']}</div>
		</div>
	</div>
EOT;
			}
		}
		else
		{
			foreach( $source as $opt )
			{
				$opt = array_merge(
					array(
						'value'    => '',
						'class'    => '',
						'icon'     => '',
						'label'    => '',
						'color'    => '',
					),
					$opt
				);
				$selected = ($opt['value'] == $value ? ' selected' : '');
				foreach (array('value', 'class', 'icon') as $attr) {
					$opt[$attr] = \esc_attr($opt[$attr]);
				}
				$color = $this->getExtraValue('color');
				$style = '';
				if (isset($color) && $color != '') {
					$colorstring = \lws_get_theme_colors('--grid-item-color', $color);
					$style = " style='$colorstring'";
				}
				if( $opt['icon'] )
				{
					$html .= <<<EOT
<div class='lws-radiogrid-button {$opt['class']} lws_radiobutton_radio{$selected} '$style data-input='#{$name}' data-value='{$opt['value']}'>
	<div class='icon {$opt['icon']}'></div>
	<div class='label'>{$opt['label']}</div>
</div>
EOT;
				}
				else
				{
					$html .= <<<EOT
<div class='lws-radiogrid-button no-icon {$opt['class']} lws_radiobutton_radio{$selected} ' data-input='#{$name}' data-value='{$opt['value']}'>
	<div class='label'>{$opt['label']}</div>
</div>
EOT;
				}
			}
		}
		return $html;
	}
}
