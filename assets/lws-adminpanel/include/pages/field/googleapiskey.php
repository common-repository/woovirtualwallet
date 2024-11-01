<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();


/** It is the nly field who does require an id (and title)
 * since we overwrite it with 'lws-private-google-api-key'
 * This option_id store a google api key.
 *
 * This key is used by font list service to retrieve the google font list.
 * It can be used by anyone to access google services.
 *
 * Provide this to your plugin admin options to let user set it's own
 * since google could limit calls (to avoid ddos) to his api,
 * a very popular plugin should be denied if anyone use the same api key. */
class GoogleAPIsKey extends \LWS\Adminpanel\Pages\Field
{
	const ID = 'lws-private-google-api-key';

	public function __construct($id='', $title='', $extra=null)
	{
		parent::__construct($id, $title, $extra);
		$this->m_Id = self::ID;
		$this->m_Title = __("Your Google API key", 'lws-adminpanel');
	}

	public function input()
	{
		$value = esc_attr(get_option($this->m_Id, ''));
		echo "<input class='{$this->style}' type='text' name='{$this->m_Id}' value='$value' />";
		if( !$value )
			\lws_admin_add_notice_once(self::ID, __("You should define a Google API key in this settings.", 'lws-adminpanel'), array('level'=>'info', 'dismissible'=>true));
	}
}
