<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();




/** Expose an enhanced taglist editor
 * Data can be preloaded or read from ajax call.
 * All extra are optionnal.
 * @param $extra['ajax'] the ajax action to grab data list.
 * @param $extra['source'] the preload data list as array of array('value'=>…, 'label'=>…, 'html'=>…)
 * 	value is the recorded value, label is displayed (and search string) to user in input field,
 * @param $extra['class'] css class list transfered to autocomplete wrapper.
 * @param $extra['name'] input name set to autocomplete wrapper input (in case label is relevant too).
 * @param $extra['shared'] defines if the input shares his source with other inputs of the same type.
 * @param $extra['minsearch'] the minimal search string length before ajax call instead of local options.
 * @param $extra['minoption'] if local filter result count is less or equal, ajax call (if any) is attempt.
 * @param $extra['delay'] hit key delay before search trigger (let user finish its term before loading).
 * @param $extra['minlength'] minimal input length before autocomplete starts (default 1).
 * @param $extra['placeholder'] is a input placeholder.
 * @param $extra['spec'] any value transfered as json base64 encoded to ajax.
 * @param $extra['value'] if is set, use this as input value, else try a get_option($id).
 * @param $extra['prebuild'] compute a source if source is omitted @see prebuild.
 * @param $extra['predefined'] precomputed values for extra @see predefined.
 * @param $extra['comprehensive'] means the source is complete and no user value could be added.
 *
 * @note soure is an array of object or array with value, label and optionnaly detail for complex html item in unfold list.
 * It is recommended to have at least the selected value described in source.
 * @note if user entry is not found in preload source and an ajax is set, ajax will be call to complete source. */

class LacTaglist extends \LWS\Adminpanel\Pages\LAC
{
	public function __construct($id, $title, $extra=null)
	{
		parent::__construct($id, $title, $extra);
		add_action('admin_enqueue_scripts', array($this, 'script'), 9);
	}

	protected function html()
	{
		if( $this->isValid(true, $this->getExtraValue('comprehensive', false)) )
		{
			$attrs = implode('', array(
				$this->getExtraAttr('sourceurl', 'data-sourceurl'),
				$this->getExtraAttr('ajax', 'data-ajax'),
				$this->getExtraAttr('placeholder', 'data-placeholder'),
				$this->getExtraCss('class', 'data-class'),
				$this->getExtraAttr('name', 'data-name'),
				$this->getExtraAttr('shared', 'data-shared'),
				$this->getExtraAttr('addlabel', 'data-addlabel', _x('Add', 'lac-taglist "add" button', 'lws-adminpanel')),
				$this->getExtraAttr('minsearch', 'data-minsearch'),
				$this->getExtraAttr('minoption', 'data-minoption'),
				$this->getExtraAttr('delay', 'data-delay'),
				$this->getExtraAttr('minlength', 'data-minlength'),
				$this->getExtraAttr('comprehensive', 'data-comprehensive')
			));
			$originalValue = $this->readOption(false);
			$value = base64_encode(json_encode($originalValue));
			$name = esc_attr($this->m_Id);
			$source = $this->data('source');
			$spec = $this->data('spec');
			if( empty($source) && $this->hasExtra('prebuild') )
			{
				$source = $this->prebuild($originalValue, $this->hasExtra('spec', 'a') ? $this->extra['spec'] : array());
			}
			if( !isset($this->scriptAdded) || !$this->scriptAdded )
			{
				$this->script();
			}
			$inputClass = $this->ignoreConfirm('lac_taglist');
			return "<input class='{$inputClass}' name='$name' data-value='$value'$attrs$source$spec data-lw_name='$name'>";
		}
	}

	public function script()
	{
		$this->scriptAdded = true;
		$this->modelScript();
		wp_enqueue_script('lws-lac-taglist');
		wp_enqueue_style('lws-lac-taglist-style');
	}

}
