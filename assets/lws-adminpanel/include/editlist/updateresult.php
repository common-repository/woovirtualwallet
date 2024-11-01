<?php
namespace LWS\Adminpanel\EditList;
if( !defined( 'ABSPATH' ) ) exit();

/** can be returned by write to detail the action result */
class UpdateResult
{
	public $data; /// (array) the data array, as it should be updated in view
	public $success; /// (bool) success of operation
	public $message; /// (string) empty, error reason or success additionnal information to display.
	/** @return a success UpdateResult instance. */
	public static function ok($data, $message='')
	{
		$me = new self();
		$me->success = true;
		$me->data = is_array($data) ? $data : array();
		$me->message = is_string($message) ? $message : '';
		return $me;
	}
	/** @return an error UpdateResult instance. */
	public static function err($reason='')
	{
		$me = new self();
		$me->success = false;
		$me->data = null;
		$me->message = is_string($reason) ? trim($reason) : '';
		return $me;
	}
	/** @return (bool) is a UpdateResult instance. */
	public static function isA($instance)
	{
		return \is_a($instance, get_class());
	}
}
