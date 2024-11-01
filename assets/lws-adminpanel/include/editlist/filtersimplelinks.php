<?php
namespace LWS\Adminpanel\EditList;
if( !defined( 'ABSPATH' ) ) exit();


/** A list of link to set $_GET parameters.
 * Use Filter::url to build urls,
 * you only give additionnal parameters. */
class FilterSimpleLinks extends Filter
{
	/** @param $links array of {key => (array of {var_name => value}} @see EditListFilter::url
	 * @param $suffixes (array of text), foreach link, add a text after label which is out of <a/> (set in same order as $links values).
	 * @param $cssclass a css class added to filter div.
	 * @param $titles foreach link, a human readable text; use same titles key as link key. If not set, link key is used as title.
	 * @param $name if set, add a hidden input to keep trace of choice (usefull for filter combination) and put a difference display for current choice. */
	function __construct($links=array(), $suffixes=array(), $cssclass='', $titles=array(), $name='', $label='')
	{
		parent::__construct();
		$this->_class = "lws-editlist-filter-selection";
		if( !empty($cssclass) )
			$this->_class .= " $cssclass";
		$href = array();
		if(empty($label)) $label = __("Filter the results", 'lws-adminpanel');
		$retour = "<div class='lws-editlist-filter-box'><div class='lws-editlist-filter-box-title'>{$label}</div>";
		$retour .= "<div class='lws-editlist-filter-box-content'>";
		$str = '';
		$index = 0;
		$name = $this->guessName($links, $name);

		foreach( $links as $a => $url )
		{
			$href = Filter::url($url);
			if( !empty($str) )
				$str .= " | ";
			$add = (count($suffixes) > $index ? $suffixes[$index] : '');
			$title = !empty($titles) && isset($titles[$a]) ? $titles[$a] : $a;

			if( !empty($name) && (isset($_GET[$name]) ? $_GET[$name] : '') == $a )
				$str .= "<span class='lws-editlist-filter-selected'>$title</span> $add";
			else
				$str .= "<a href='$href'>$title</a> $add";

			$index++;
		}
		$retour .= $str."</div></div>";
		$this->_content = $retour;

		if( !empty($name) && isset($_GET[$name]) )
		{
			$lastValue = esc_attr(\sanitize_text_field($_GET[$name]));
			$this->_content .= "<input type='hidden' name='$name' value='$lastValue' />";
		}
	}

	protected function guessName($links, $name)
	{
		if( empty($name) && !empty($links) && !empty($first = reset($links)) && is_array($first) )
		{
			$name = array_keys($first)[0];
			foreach( $links as $a => $url )
			{
				if( empty($first = reset($links)) || !is_array($first) || array_keys($first)[0] != $name )
				{
					$name = '';
					break;
				}
			}
		}
		return $name;
	}
}
