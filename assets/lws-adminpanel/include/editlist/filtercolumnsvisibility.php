<?php

namespace LWS\Adminpanel\EditList;

if (!defined('ABSPATH')) exit();


/** A filter that allows to show or hide columns of the editlist */
class FilterColumnsVisibility extends Filter
{
	/** @param $name you will get the filter value in $_GET[$name]. */
	function __construct($name, $title, $extra = array())
	{
		parent::__construct();
		$this->_class = "lws-editlist-filter-search lws-editlist-filter-column-visibility";
		$this->name = $name;
		$this->title = $title;
		$this->extra = $extra;
	}

	function input($above = true, $columns = array())
	{
		$retour = "<div class='lws-editlist-filter-box end'><div class='lws-editlist-filter-box-title'>{$this->title}</div>";
		$retour .= "<div class='visibility-cb-line'>";
		foreach ($columns as $key => $value)
		{
			if (is_array($value) && isset($value[2]) && $value[2])
			{
				$name = $this->name . '_' . $key;
				$retour .= "<div class='visibility-cb-wrapper'>";
				$retour .= "<input type='checkbox' class='lws_checkbox lws-ignore-confirm editlist_cb_visibility' data-size='15' name='$name' data-name='$key' checked />";
				$retour .= "<div class='visibility-cs-wrapper'>{$value[0]}</div>";
				$retour .= "</div>";
			}
		}
		$retour .= "</div></div>";
		return $retour;
	}
}
