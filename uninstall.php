<?php
if( !defined( 'WP_UNINSTALL_PLUGIN' ) ) exit();
@include_once dirname(__FILE__) . '/modules/woovirtualwallet-pro/uninstall.php';

\delete_option('lws_woovirtualwallet_version');
\delete_option('lws_woovirtualwallet_redirect_to_licence');

$roles = array(
	'administrator' => array('edit_other_virtual_wallet', 'read_other_virtual_wallet'),
	'shop_manager' => array('read_other_virtual_wallet'),
);
foreach( $roles as $slug => $caps )
{
	if( $role = \get_role($slug) )
	{
		foreach( $caps as $cap )
			$role->remove_cap($cap);
	}
}
