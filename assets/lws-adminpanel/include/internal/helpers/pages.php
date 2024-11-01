<?php
if( !defined( 'ABSPATH' ) ) exit();

require LWS_ADMIN_PANEL_INCLUDES . '/internal/editlistcontroler.php';

/** @param $pages an array of page description.
 * for examples @see Pages::makePages or @see examples.php */
function lws_register_pages($pages)
{
	\LWS\Adminpanel\Internal\Pages::makePages($pages);
}

/** explore the lwss pseudocss file to create customizable values edition fields.
 * @param $url the path to .lwss file.
 * @param $textDomain the text-domain to use for wordpress translation of field ID to human readable title.
 * @return an  array of field to use in pages descrption array. */
function lwss_to_fields($url, $textDomain, $fieldsBefore=null, $fieldsAfter=null)
{
	$fields = \LWS\Adminpanel\Tools\PseudoCss::toFieldArray($url, $textDomain);
	if( !is_null($fieldsBefore) && is_array($fieldsBefore) && !empty($fieldsBefore) )
	{
		if( isset($fieldsBefore[0]) && is_array($fieldsBefore[0]) )
			$fields = array_merge($fieldsBefore, $fields);
		else
			$fields = array_merge(array($fieldsBefore), $fields);
	}
	if( !is_null($fieldsAfter) && is_array($fieldsAfter) )
	{
		if( isset($fieldsAfter[0]) && is_array($fieldsAfter[0]) )
			$fields = array_merge($fields, $fieldsAfter);
		else
			$fields = array_merge($fields, array($fieldsAfter));
	}
	return $fields;
}

/**	@return an array representing a group to push in admin page registration in 'groups' array.
 *	@param $templates array of template name. */
function lws_mail_settings($templates)
{
	return \LWS\Adminpanel\Internal\Mailer::instance()->settingsGroup($templates);
}

/** Instanciate a list to insert in a group array associated with id 'editlist'.
 * @param $editionId (string) is a unique id which refer to this EditList.
 * @param $recordUIdKey (string) is the key which will be used to ensure record unicity.
 * @param $source instance which etends EditListSource.
 * @param $mode allows list for modification (use bitwise operation, @see ALL)
 * @param $filtersAndActions an array of instance of EditList\Action or EditList\Filter. */
function lws_editlist( $editionId, $recordUIdKey, $source, $mode = \LWS\Adminpanel\EditList::ALL, $filtersAndActions=array() )
{
	return new \LWS\Adminpanel\Internal\EditlistControler($editionId, $recordUIdKey, $source, $mode, $filtersAndActions);
}

/** @return a group array used to define a Google API key for application as font-api et so on. */
function lws_google_api_key_group()
{
	$txt = sprintf("<p>%s</p><p><a href='%s'>%s</a> %s</p><p>%s</p>",
		__("Used to get google fonts.", 'lws-adminpanel'),
		'https://console.developers.google.com/apis/api/webfonts.googleapis.com',
		//'https://console.developers.google.com/henhouse/?pb=["hh-1","webfonts_backend",null,[],"https://developers.google.com",null,["webfonts_backend"],null]&TB_iframe=true&width=600&height=400',
		__( "Generate API Key", 'lws-adminpanel' ),
		sprintf(__( "or <a target='_blank' href='%s'>click here to Get a Google API KEY</a>", 'lws-adminpanel' ),
			'https://console.developers.google.com/flows/enableapi?apiid=webfonts_backend&keyType=CLIENT_SIDE&reusekey=true'
		),
		__( "You MUST be logged in to your Google account to generate a key.", 'lws-adminpanel' )
	);

	return array(
		'title' => __("Google account", 'lws-adminpanel'),
		'text' => $txt,
		'fields' => array( array('type' => 'googleapikey') )
	);
}

function lws_clean_slug_from_mainfile($file)
{
	return strtolower(basename(\plugin_basename($file), '.php'));
}
