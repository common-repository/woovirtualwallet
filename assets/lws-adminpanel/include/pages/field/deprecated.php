<?php
namespace LWS\Adminpanel\Pages;
if( !defined( 'ABSPATH' ) ) exit();


/** @deprecated use autocomplete with extra['predefined']='user' */
class FieldUser extends \LWS\Adminpanel\Pages\Field\Autocomplete
{
	public function __construct($id, $title, $extra=null)
	{
		if( is_null($extra) || !is_array($extra) ) $extra = array();
		$extra['predefined'] = 'user';
		parent::__construct($id, $title, $extra);
	}

	public static function combobox( $name, $value, $style="", $dummyName='' )
	{
		return self::compose($name, array('predefined'=>'user','class'=>$style, 'name'=>$dummyName, 'value'=>$value));
	}

	public static function userName($userId)
	{
		$username = '';
		if( !empty($userId) && is_numeric($userId) )
		{
			$user = get_user_by('id', $userId);
			if( $user !== false )
				$username = html_entity_decode($user->user_login);
		}
		return $username;
	}
}

/** @deprecated use autocomplete with extra['predefined']='page' */
class FieldPage extends \LWS\Adminpanel\Pages\Field\Autocomplete
{
	public function __construct($id, $title, $extra=null)
	{
		if( is_null($extra) || !is_array($extra) ) $extra = array();
		$extra['predefined'] = 'page';
		parent::__construct($id, $title, $extra);
	}

	public static function combobox( $name, $value, $style="", $dummyName='' )
	{
		return self::compose($name, array('predefined'=>'page','class'=>$style, 'name'=>$dummyName, 'value'=>$value));
	}

	public static function pageTitle($postId)
	{
		$title = '';
		if( !empty($postId) && is_numeric($postId) )
			$title = html_entity_decode(get_the_title($postId));
		return $title;
	}
}

?>
