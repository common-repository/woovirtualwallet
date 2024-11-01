<?php
namespace LWS\Adminpanel\EditList;
if( !defined( 'ABSPATH' ) ) exit();

/** Allows you to add filter input or link to the editlist.
 * It is up to you to apply in your EditListSource::read implementation (looking at $_POST or $_GET...)
 * This class provide only a way to display it in rightfull place.
 * Do not insert your <input>, if any, in a <form>. It will be created on-the-fly.
 *
 * You can extends this class and overload input function
 * or provide a CALLABLE to return your html code. */
class Filter
{

	/** The filter inputs.
	 *	@return a string with the form content.
	 * @note use class lws-input-enter-submit on a <input>
	 * to allow validation by pressing enter key without submit button. */
	function input($above=true)
	{
		if( isset($this->_callback) && !is_null($this->_callback) && is_callable($this->_callback) )
			return call_user_func($this->_callback, $above);
		else if( isset($this->_content) && !is_null($this->_content) && is_string($this->_content) )
			return $this->_content;
		else
			return "";
	}

	/** The filter will be defined by a callback function
	 *  @param $callable a php CALLABLE which will provide the html code.
	 * @return a EditListFilter instance */
	static function callback($callable, $class='')
	{
		$inst = new Filter($class);
		$inst->_callback = $callable;
		return $inst;
	}

	/** The filter is provided as is. Good for simple static html code.
	 * @return a EditListFilter instance */
	static function content($html, $class='')
	{
		$inst = new Filter($class);
		$inst->_content = $html;
		return $inst;
	}

	/** provided for convenience.
	 * @return build a url to apply filter with given arguments.
	 * @param $getArgs is an array of (variable_name => value),
	 * this should be read as $_GET in your custom EditListSource::read implementation.
	 * @note cannot be used with EditListFilter::content since we must be at display step to know data. */
	static function url($getArgs=array())
	{
		if( isset($_REQUEST['page']) )
			$getArgs['page'] = \sanitize_text_field($_REQUEST['page']);
		if( isset($_REQUEST['tab']) )
			$getArgs['tab'] = \sanitize_text_field($_REQUEST['tab']);
		return add_query_arg($getArgs, admin_url('/admin.php'));
	}

	function __construct($class='')
	{
		$this->_callback = null;
		$this->_content = null;
		$this->_class = $class;
	}

	function cssClass()
	{
		$c = "lws-editlist-filter";
		if( !empty($this->_class) )
			$c .= (' ' . $this->_class);
		return $c;
	}

}
