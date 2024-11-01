<?php
namespace LWS\Adminpanel\EditList;
if( !defined( 'ABSPATH' ) ) exit();

/** As post, display a list of item with on-the-fly edition. */
abstract class Source
{

	/** The edition inputs.
	 *	input[name] should refers to all $line array keys (use input[type='hidden'] for not editable elements).
	 * Readonly element can be displayed using <span data-name='...'></span> but this one will not be send
	 * back at validation, display is its only prupose (name can be the same as an hidden input if you want return)
	 *	@return a string with the form content. */
	abstract function input();

	/**	@return an array with the column which must be displayed in the list.
	 *	array ( $key => array($label [, $col_width]) )
	 * The width (eg. 10% or 45px) to apply to column is optionnal. */
	abstract function labels();

	/**	get the list content and return it as an array.
	 * @param $limit an instance of RowLimit class or null if deactivated (if EditList::setPageDisplay(false) called).
	 *	@return an array of line array. array( array( key => value ) ) */
	abstract function read($limit);

	/**	Save one edited line. If the index is not found, this function must create a new record.
	 * @param $row (array) the edited item to save.
	 * @return On success return the updated line, if failed, return false or a \WP_Error instance to add details. */
	abstract function write( $row );

	/**	Delete one edited line.
	 * @param $row (array) the item to remove.
	 * @return true if succeed. */
	abstract function erase( $row );

	/** this function to return the total number of record in source.
	 * @return the record count or -1 if not implemented or unavailable. */
	public function total()
	{
		return -1;
	}

	/** Override this function to specify default values (array) for a new edition form. */
	public function defaultValues()
	{
		return "";
	}

	/** @deprecated use 'lws_adminpanel_arg_parse' filter instead.
	 * @see \LWS\Adminpanel\Tools\ArgParser */
	public static function invalidArray(&$array, $format, $strictFormat=true, $strictArray=true, $translations=array())
	{
		return \LWS\Adminpanel\Tools\ArgParser::invalidArray($array, $format, $strictFormat, $strictArray, $translations);
	}

	/** Common default style if a cell links to an edition page. */
	public function coatTitleToEditButton($text, $redirectTo, $style='')
	{
		$style = ('lws-editlist-title-edit-btn ' . \ltrim($style));
		$button = "<a href='{$redirectTo}' class='{$style}'>$text</a>";
		return $button;
	}

	public function coatActionButton($text, $icon='lws-icon-settings-gear', $tag='div', $args=array())
	{
		$args['class'] = isset($args['class']) ? rtrim($args['class']) : '';
		$args['class'] .= " editlist-btn {$icon}";
		if( in_array(strtolower($tag), array('a', 'span')) )
			$args['class'] .= " custom";

		foreach( $args as $k => &$v )
			$v = sprintf('%s="%s"', $k, $v);
		$args = implode(' ', $args);
		return "<{$tag} {$args}><div class='btn-descr'>{$text}</div></{$tag}>";
	}
}
