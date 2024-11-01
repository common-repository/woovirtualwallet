<?php
namespace LWS\Adminpanel\Pages\Field;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Set integer and time unit {Day, month, year}
 * $extra['value'] and stored wp_option format is DateInterval format.
 * $extra['force'] value and period not hidable.
 * $extra['periods'] bool|array filter periods Y, M, D, W, H, I, S
 */
class Duration extends \LWS\Adminpanel\Pages\Field
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

	private function html()
	{
		\do_action('lws_adminpanel_enqueue_lac_scripts', array('select'));
		\wp_enqueue_script('lws-checkbox');
		\wp_enqueue_script('lws-adm-duration', LWS_ADMIN_PANEL_JS.'/controls/duration.js', array('jquery', 'lws-checkbox'), LWS_ADMIN_PANEL_VERSION, true);

		$value = $this->readOption(false);
		$duration = \LWS\Adminpanel\Tools\Duration::fromString($value);

		$html = '';
		if( !$this->getExtraValue('force', false) )
			$html .= $this->checkbox($duration);
		$html .= $this->value($duration);
		$html .= $this->period($duration);
		$html .= $this->master($duration);
		return "<span class='lws-editlist-opt-multi lws_adm_durationfield lws-field-duration'>".$html."</span>";
	}

	protected function master($duration)
	{
		$d = $duration->toString();
		return "<input name='{$this->m_Id}' type='hidden' class='lws_adm_lifetime_master' value='$d'/>";
	}

	protected function isStartHidden($duration)
	{
		return $duration->isNull() && !$this->getExtraValue('force', false);
	}

	protected function period($duration)
	{
		$hidden = $this->isStartHidden($duration) ? ' style="display:none"' : '';
		$p = $duration->getPeriod();

		if( $filtered = $this->getExtraValue('periods', false) )
		{
			if( \is_array($filtered) )
				$supported = \array_intersect_key(\LWS\Adminpanel\Tools\Duration::getSupportedPeriods(true), array_fill_keys($filtered, true));
			else
				$supported = \LWS\Adminpanel\Tools\Duration::getSupportedPeriods(true);
		}
		else
			$supported = \LWS\Adminpanel\Tools\Duration::getSupportedPeriods();

		$period = "<select class='{$this->style} lac_select lws_adm_lifetime_unit' data-mode='select'$hidden>";
		foreach( $supported as $value => $text )
		{
			$selected = ($p == $value ? ' selected' : '');
			$period .= "<option value='$value'$selected>$text</option>";
		}
		$period .= "</select>";
		return $period;
	}

	protected function value($duration)
	{
		$title = esc_attr(__("An integer value greater than zero.", 'lws-adminpanel'));
		$hidden = $this->isStartHidden($duration) ? ' style="display:none"' : '';
		$v = $duration->getCount();

		$pattern = $this->getExtraValue('force', false) ? '\d*[1-9]\d*' : '\d*';
		$style = $this->getExtraValue('small', false) ? ' lws_adm_lifetime_small' : '';

		return "<input size='4' class='{$this->style} lws_adm_lifetime_value$style' pattern='{$pattern}' title='$title' maxlength='4' type='text' value='$v'$hidden/>";
	}

	protected function checkbox($duration)
	{
		$checked = $duration->isNull() ? '' : ' checked';
		$idAttr = (false === strpos($this->m_Id, '[') ? "id='{$this->m_Id}'" : '');
		return "<input {$idAttr} type='checkbox' class='lws_checkbox lws_adm_lifetime_check'$checked>";
	}
}
