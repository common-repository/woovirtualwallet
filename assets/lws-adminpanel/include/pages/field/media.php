<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();

/** extra entries could be:
 * urlInputName : add a hidden input to get the image url.
 * size: define the wordpress size name of the image to use.
 * classSize: the css size style.
 * value: force a value instead of reading options.
 * type: only 'image' is supported actually.
 * */
class Media extends \LWS\Adminpanel\Pages\Field
{
	protected function dft(){ return array('type'=>'image'); }

	public static function compose($id, $extra=null)
	{
		$me = new self($id, '', $extra);
		return $me->html();
	}

	public function input()
	{
		echo $this->html();
	}

	private function html()
	{
		$img = '';
		$del = esc_attr(__("Remove", 'lws-adminpanel'));
		$title = esc_attr(__("Select a media", 'lws-adminpanel'));
		$pick = esc_attr(__("Use the selected item", 'lws-adminpanel'));
		$add = esc_attr(__("Select a media", 'lws-adminpanel'));
		$edit = esc_attr(__("Edit...", 'lws-adminpanel'));

		$value = $this->readOption();
		$size = $this->getExtraValue('size', 'small');
		$classSize = $this->getExtraValue('classSize', 'medium');
		$url = $this->getExtraValue('urlInputName', '');

		if( $this->extra['type'] == 'image' )
		{
			if( !empty($value) && is_numeric($value) )
				$img = wp_get_attachment_image($value, $size);
		}
		else
			error_log("No other media than image is managed yet. Sorry.");

		$aspect = $this->getExtraValue('aspect', $size);
		if( !in_array($aspect, array('small', 'medium')) )
			$aspect = 'small';

		if( empty($img) ){
			$hide = " style='display:none'";
			$btclass = " lws-media-add";
			$btntext = $add;
		}else{
			$hide = '';
			$btclass = " lws-media-edit";
			$btntext = $edit;
		}
		$str = "<div class='lws_media_master lws-{$aspect}'><div class='lws-adm-media'$hide>$img</div>";
		$str .= "<input type='button' class='lws_adminpanel_btn_add_media$btclass' value='$btntext' data-type='{$this->extra['type']}' data-add='$add' data-edit='$edit' data-title='$title' data-pick='$pick' data-image-size='$size' data-class-size='$classSize'>";
		$str .= "<input type='button' class='lws-media-del lws_adminpanel_btn_del_media'$hide value='$del'>";
		$str .= "<input type='hidden' class='lws_adminpanel_input_media_id lws-force-confirm' name='{$this->m_Id}' value='$value' />";
		if( !empty($url) )
			$str .= "<input type='hidden' class='lws_adminpanel_input_media_url' name='$url'/>";
		$str .= "</div>";

		$script = LWS_ADMIN_PANEL_JS . '/controls/media.js';
		wp_enqueue_script( 'lws-adm-media', $script, array('jquery'), LWS_ADMIN_PANEL_VERSION, true );
		return $str;
	}
}

?>
