<?php
if( !defined( 'ABSPATH' ) ) exit();

/**	@deprecated use lws_plugin() */
function lws_require_activation($file, $depracated=false, $adminPageId='', $uuid='')
{
	if( $uuid )
	{
		require_once LWS_ADMIN_PANEL_INCLUDES . '/internal/credits/licensepage.php';
		$tab = true;
		if( \is_array($adminPageId) && $adminPageId ){
			$tab = $adminPageId;
			$adminPageId = \reset($adminPageId);
		}
		\LWS\Adminpanel\Internal\Credits\LicensePage::install($file, $adminPageId, $uuid, false, $tab);
	}

	require_once LWS_ADMIN_PANEL_INCLUDES . '/internal/credits/licensepage.php';
	$lic = new \LWS\Adminpanel\Internal\Credits\License($file, $uuid);
	return $lic->isRunning();
}

/** Register a plugin with premium
 *  @param $file main php file of the plugin.
 *	@param $adminPageId the id of the main administration page. */
function lws_plugin($file, $adminPageId, $uuid, $def=false, $targetPage=false)
{
	require_once LWS_ADMIN_PANEL_INCLUDES . '/internal/credits/licensepage.php';
	\LWS\Adminpanel\Internal\Credits\LicensePage::install($file, $adminPageId, $uuid, $def, $targetPage);

	require_once LWS_ADMIN_PANEL_INCLUDES . '/internal/credits/supportpage.php';
	if( !$targetPage )
		$targetPage = $adminPageId.'_lic';
	\LWS\Adminpanel\Internal\Credits\SupportPage::install($file, $adminPageId, $targetPage);
}

/** Register an addon plugin
 *  @param $file main php file of the plugin.
 *	@param $adminPageId the id of the main administration page. */
function lws_addon($file, $masterSlug, $addonUuid, $def=false)
{
	require_once LWS_ADMIN_PANEL_INCLUDES . '/internal/credits/licensepage.php';
	\LWS\Adminpanel\Internal\Credits\LicensePage::installAddon($file, $masterSlug, $addonUuid, $def);
}

/** Obsolete, just ignored but declaration kept for backward compatibility. */
function lws_register_update($arg1, $arg2='', $arg3='', $arg4=false)
{
}

/** Obsolete, just ignored but declaration kept for backward compatibility. */
function lws_extension_showcase($arg1, $arg2='', $arg3='', $arg4='')
{
}
