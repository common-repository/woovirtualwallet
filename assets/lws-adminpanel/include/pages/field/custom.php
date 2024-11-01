<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();

/** allow any custom HTML content as input part.
 * Expect extra key 'content' that will simply echo.
 * Optionaly, extra read:
 * * 'script' : call wp_enqueue_script($handle [, $src [, $deps[, $ver [,$in_footer]]]]) function
 * 		with arguments from given array in the same order.
 * * 'style' : call wp_enqueue_style($handle [, $src [, $deps[, $ver [,$media]]]]) function
 * 		with arguments from given array in the same order.
 * Anything else works as usual,
 * 'id' will be registered unless extra 'gizmo' is set to true, etc. */
class Custom extends \LWS\Adminpanel\Pages\Field
{
	public function input()
	{
		if( $script = $this->getExtraValue('script', false) )
			\call_user_func_array('wp_enqueue_script', $script);
		if( $style = $this->getExtraValue('style', false) )
			\call_user_func_array('wp_enqueue_style', $style);

		echo $this->getExtraValue('content');
	}
}
