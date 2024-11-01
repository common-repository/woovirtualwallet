<?php

namespace LWS\Adminpanel\Pages;

if (!defined('ABSPATH')) exit();

/**  */
class Group
{
	private $m_FieldArray = array();

	/** @param $data fulfill the Group::format */
	function __construct($data, $page)
	{
		$this->page = $page;
		$this->id = isset($data['id']) ? $data['id'] : '';
		$this->title = $data['title'];
		$this->color = isset($data['color']) ? $data['color'] : '';
		$this->image = isset($data['image']) ? $data['image'] : '';
		$this->icon = isset($data['icon']) ? $data['icon'] : '';
		$this->class = isset($data['class']) ? $data['class'] : '';
		$this->collapsed = isset($data['collapsed']) ? boolval($data['collapsed']) : false;
		$this->helpBanner = isset($data['text']) ? $data['text'] : '';
		$this->extra = isset($data['extra']) ? $data['extra'] : array();
		$this->customBehavior = isset($data['function']) ? $data['function'] : null;
		$this->customDelayedBehavior = isset($data['delayedFunction']) ? $data['delayedFunction'] : null;
		$this->editlist = isset($data['editlist']) ? $data['editlist'] : null;
		$this->editlistFirst = isset($data['editlist_first']) ? boolval($data['editlist_first']) : false;
		$this->advanced = isset($data['advanced']) ? boolval($data['advanced']) : false;
		if (isset($data['require']))
			$this->setRequirement($data['require']);

		if (isset($data['fields']))
		{
			foreach ($data['fields'] as $field)
			{
				if (\LWS\Adminpanel\Internal\Pages::test($field, self::fieldFormat(), "$page ... fields"))
					$this->addField($field);
			}
		}
	}

	/** @return a well formated format array for Pages::test()
	 * @see Pages::test() */
	public static function format()
	{
		return array(
			'title'				=> \LWS\Adminpanel\Internal\Pages::format('title', false, 'string', "Display a group title."),
			'id'				=> \LWS\Adminpanel\Internal\Pages::format('id', true, 'string', "Identify a group"),
			'rights'			=> \LWS\Adminpanel\Internal\Pages::format('rights', true, 'string', "User capacity required to access to this group. Usually 'manage_options'. A tab could be locally more restrictive"),
			'text'				=> \LWS\Adminpanel\Internal\Pages::format('text', true, '', "A free text displayed at top of the group, after the title. If array given, see \lws_array_to_html()"),
			'fields'			=> \LWS\Adminpanel\Internal\Pages::format('fields', true, 'array', "Option fields"),
			'extra'				=> \LWS\Adminpanel\Internal\Pages::format('extra', true, 'array', "Extra features as 'doclink'"),
			'image'				=> \LWS\Adminpanel\Internal\Pages::format('image', true, 'string', "Image to set on group header"),
			'color'				=> \LWS\Adminpanel\Internal\Pages::format('color', true, 'string', "special color for the group. Use hexa color format. eg. #ffffff"),
			'class'				=> \LWS\Adminpanel\Internal\Pages::format('class', true, 'string', "extra class to put on the group | Used for grid display"),
			'collapsed'			=> \LWS\Adminpanel\Internal\Pages::format('collapsed', true, 'bool', "default is false. Defines if a group is collapsed by default or not"),
			'icon'				=> \LWS\Adminpanel\Internal\Pages::format('icon', true, 'string', "Icon to set on group header | overrided if image set"),
			'editlist'			=> \LWS\Adminpanel\Internal\Pages::format('editlist', true, 'LWS\Adminpanel\EditList', "An editlist instance"),
			'editlist_first'	=> \LWS\Adminpanel\Internal\Pages::format('editlist_first', true, 'bool', "Default is false. Show editlist before the fields.'"),
			'function'			=> \LWS\Adminpanel\Internal\Pages::format('function', true, 'callable', "A function to echo a custom feature."),
			'delayedFunction'	=> \LWS\Adminpanel\Internal\Pages::format('delayedFunction', true, 'callable', "Same as function but executed after usual fields display."),
			'advanced'			=> \LWS\Adminpanel\Internal\Pages::format('advanced', true, 'bool', "Default is false. If true, the group is hidden by default."),
			'require' => \LWS\Adminpanel\Internal\Pages::format('require',	true, 'array', "An array with a css selector to an input and the required value ['selector' => '.example', 'value'=> 'yes']. If condition is not fullfilled, all the line is hidden."),
		);
	}

	/** @return a well formated format array for Pages::test()
	 * @see Pages::test() */
	public static function fieldFormat()
	{
		return array(
			'id'    => \LWS\Adminpanel\Internal\Pages::format('id',		false, 'string', "used with update_option and get_option."),
			'type'  => \LWS\Adminpanel\Internal\Pages::format('type',	false, 'string', "A known field type."),
			'title' => \LWS\Adminpanel\Internal\Pages::format('title',	true, 'string', "Field title."),
			'extra' => \LWS\Adminpanel\Internal\Pages::format('extra',	true, 'array', "type specific."),
			'require' => \LWS\Adminpanel\Internal\Pages::format('require',	true, 'array', "An array with a css selector to an input and the required value ['selector' => '.example', 'value'=> 'yes']. If condition is not fullfilled, all the line is hidden."),
		);
	}

	public function addField($data)
	{
		$extra = isset($data['extra']) ? $data['extra'] : array();
		$id = isset($data['id']) ? $data['id'] : '';
		$title = isset($data['title']) ? $data['title'] : '';
		$f = Field::create(strtolower($data['type']), $id, $title, $extra);
		if (isset($data['require']))
			$f->setRequirement($data['require']);

		if (!is_null($f))
			$this->m_FieldArray[] = $f->register($this->page);
		return $f;
	}

	public function title($maxlen = 0, $etc = '...')
	{
		if ($maxlen <= 0)
			return $this->title;

		$minTitle = \wp_kses($this->title, array());
		if (\strlen($minTitle) <= $maxlen)
			return $this->title;
		else
			return substr($minTitle, 0, ($maxlen - strlen($etc))) . $etc;
	}

	public function targetId()
	{
		return 'lws_group_targetable_' . $this->id;
	}

	public function titleId()
	{
		return 'lws_group_title_' . $this->id;
	}

	/** An advanced group is hidden by default (set advanced=>true as arguments)
	 * A group is also advanced if all its fields are advanced.
	 * Side effect: no field => not advanced. */
	public function isAdvanced()
	{
		if ($this->advanced)
			return true;
		if (empty($this->m_FieldArray))
			return false;
		if (!is_null($this->editlist) && is_a($this->editlist, '\LWS\Adminpanel\Internal\EditlistControler'))
			return false;

		foreach ($this->m_FieldArray as $field)
		{
			if (!$field->isAdvanced())
				return false;
		}
		$this->advanced = true;
		return true;
	}

	/** @param $require (array) An array with a css selector to an input and the required value ['selector' => '.example', 'value'=> 'yes'].
	 * (Managed in Group) If condition is not fullfilled, all the line is hidden. */
	public function setRequirement(array $require)
	{
		if (isset($require['selector']) && \is_string($require['selector']) && $require['selector'])
		{
			$this->requirement = array_merge(array('value' => '', 'cmp' => '=='), $require);
			if (!\in_array($this->requirement['cmp'], array('==', '!=', 'match')))
			{
				$this->requirement['cmp'] = '==';
				error_log("In field [{$this->m_Id}], 'require.cmp' expect a string in [==, !=, match]. Default is ==.");
			}
		}
		else
			error_log("In field [{$this->m_Id}], 'require' expect an array with a css selector to an input and the required value ['selector' => '.example', 'value'=> 'yes']. If condition is not fullfilled, all the line is hidden.");
	}

	public function getRequirementClass($prefix = ' ')
	{
		return (isset($this->requirement) && $this->requirement) ? ($prefix . 'lws_adm_field_require') : '';
	}

	public function getRequirementArgs($prefix = ' ')
	{
		if (isset($this->requirement) && $this->requirement)
		{
			$s = \esc_attr($this->requirement['selector']);
			$v = \esc_attr($this->requirement['value']);
			$c = \esc_attr($this->requirement['cmp']);
			return "{$prefix}data-selector='{$s}' data-value='{$v}' data-operator='{$c}'";
		}
		else
			return '';
	}

	/** echo the group content */
	public function eContent()
	{
		$txtid = $this->targetId();
		//$advanced = $this->isAdvanced() ? ' lws_advanced_option' : '';
		$class = $this->class ? (' ' . $this->class) : '';
		$class .= $this->getRequirementClass();
		$args = $this->getRequirementArgs();

		$colorStyle = '';
		if (isset($this->color) && $this->color)
		{
			$mediumColor = $this->color . '60';
			$lightColor = $this->color . '20';
			$colorStyle = " style='--group-color:{$this->color};--group-medium-color:{$mediumColor};--group-light-color:{$lightColor};'";
		}
		echo "<div class='lws-form-div group-item$class'$colorStyle id='{$txtid}'{$args}>";

		echo $this->groupTitleLine();
		if ($this->helpBanner)
		{
			if (\is_array($this->helpBanner))
				$this->helpBanner = \lws_array_to_html($this->helpBanner);
			echo "<div class='group-help'>{$this->helpBanner}</div>";
		}

		echo "<div class='group-content fields-grid'>";

		if ($this->customBehavior != null && is_callable($this->customBehavior))
		{
			echo "<div class='group-spanned'>";
			call_user_func($this->customBehavior, $this->id);
			echo "</div>";
		}

		if ($this->editlistFirst)
		{
			if (!is_null($this->editlist) && is_a($this->editlist, '\LWS\Adminpanel\Internal\EditlistControler'))
			{
				echo "<div class='group-spanned'>";
				$this->editlist->display();
				echo "</div>";
			}
		}

		foreach ($this->m_FieldArray as $field)
		{
			if (!$field->isHidden())
			{
				$id = esc_attr($field->id());
				$class = '';
				//$class .= ($field->isAdvanced() ? " lws_advanced_option" : '');
				$class .= $field->getRequirementClass();
				$args = $field->getRequirementArgs();

				$help = $field->help();
				if (!$help && $field->getTooltips())
					$help = $field->getTooltips();
				if ($help)
					echo "<div class='field-help'$args>{$help}</div>";

				if ($field->title())
				{
					$label = $field->label();
					echo "<div class='field-label{$class}'$args><label for='$id'>$label</label>";
					if ($help)
						echo "<div class='bt-field-help'>?</div>"; // button to display help above
					echo "</div>";
				}
				else
				{
					$class .= ' group-spanned';
				}

				echo "<div class='field-input{$class}'$args>";
				$field->input();
				echo "</div>";
			}
			else
				$field->input();
		}

		if (!$this->editlistFirst)
		{
			if (!is_null($this->editlist) && is_a($this->editlist, '\LWS\Adminpanel\Internal\EditlistControler'))
			{
				echo "<div class='group-spanned'>";
				$this->editlist->display();
				echo "</div>";
			}
		}

		if ($this->customDelayedBehavior != null && is_callable($this->customDelayedBehavior))
		{
			echo "<div class='group-spanned'>";
			call_user_func($this->customDelayedBehavior, $this->id);
			echo "</div>";
		}

		echo "</div>";
		echo "</div>";
	}

	protected function groupTitleLine()
	{
		$ttlid = $this->titleId();
		if (empty($this->title))
			$titleLine = "<div id='$ttlid'></div>";
		else
		{
			$lpaddingClass = '';
			$icon = '';
			if (isset($this->icon) && !empty($this->icon))
			{
				$lpaddingClass = ' has-icon';
				$icon = "<div class='group-icon {$this->icon}' data-lws-icon='{$this->icon}'></div>";
			}
			if (isset($this->image) && !empty($this->image))
			{
				$url = \esc_attr($this->image);
				$lpaddingClass = ' has-icon';
				$icon = "<div class='group-icon'><img src='{$url}'/></div>";
			}
			$titleLine  = "<div class='group-title-line$lpaddingClass'>";
			$titleLine .= $icon;
			$titleLine .= "<div class='group-title'>{$this->title}</div>";
			$expandIcon = (isset($this->collapsed) && $this->collapsed) ? " lws-icon-plus" : " lws-icon-minus";
			$titleLine .= "<div class='group-expand $expandIcon'></div>";
			if (isset($this->extra['doclink']) && $this->extra['doclink'])
			{
				$titleLine .= "<a href='{$this->extra['doclink']}'  target='_blank' class='group-doc lws-icon-books'></a>";
			}
			$titleLine .= "</div>";
		}
		return $titleLine;
	}

	public function hasFields($excludeGizmo = false)
	{
		if ($excludeGizmo)
		{
			foreach ($this->m_FieldArray as $f)
			{
				if (!$f->isGizmo())
					return true;
			}
			return false;
		}
		else
			return !empty($this->m_FieldArray);
	}

	public function mergeFields(&$fields)
	{
		foreach ($this->m_FieldArray as $f)
			$fields[] = $f;
	}
}
