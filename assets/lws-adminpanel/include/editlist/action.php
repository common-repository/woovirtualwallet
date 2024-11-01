<?php
namespace LWS\Adminpanel\EditList;
if( !defined( 'ABSPATH' ) ) exit();

/** Grouped action to apply on a selection of element. */
abstract class Action
{
	/** @param $uid an action identifier */
	function __construct($uid){ $this->UID = sanitize_key($uid); }

	/** The edition inputs.
	 * Allows the user to choose the grouped action to apply.
	 *	@return a string with the form content without submit button. */
	abstract function input();

	/**	Apply the action on the rows.
	 * It is up to you to get action information (should be in $_POST if use any <input>).
	 * @param $itemsIds (array of array) the ids of the selected items to update.
	 * @return (bool|string) true if succeed, false if failed,
	 * or a string that will be displayed to the user. */
	abstract function apply( $itemsIds );

}
