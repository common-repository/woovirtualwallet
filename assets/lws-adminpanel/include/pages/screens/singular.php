<?php
namespace LWS\Adminpanel\Pages\Screens;
if( !defined( 'ABSPATH' ) ) exit();


/**  */
class Singular extends \LWS\Adminpanel\Pages\Page
{
	protected $singularId = false;
	protected $singularKey = false;

	protected function prepare()
	{
		if( $this->isValid() )
		{
			if( isset($this->data['singular_edit']['key']) && $this->data['singular_edit']['key'] )
			{
				$this->singularKey = $this->data['singular_edit']['key'];
				if( isset($_REQUEST[$this->singularKey]) )
					$this->singularId = \sanitize_key($_REQUEST[$this->singularKey]);
			}
		}
		$this->doAction();
	}

	public function getType()
	{
		return 'singular';
	}

	/** @return a well formated format array for Pages::test()
		* @see Pages::test() */
	public function isValid()
	{
		return \LWS\Adminpanel\Internal\Pages::test(
			$this->data['singular_edit'],
			array(
				'form'   => \LWS\Adminpanel\Internal\Pages::format('form',   false, 'callable', "Display the form content (content only, <form> DOM is up to class-page). The \$_REQUEST[\$key] value is repeated as argument to that callable. This function should return false if a problem occurs."),
				'save'   => \LWS\Adminpanel\Internal\Pages::format('save',   true,  'callable', "Save any data set in form. The \$_REQUEST[\$key] value is repeated as argument to that callable. This function should return the singular id (value that will replace \$_REQUEST[\$key])."),
				'delete' => \LWS\Adminpanel\Internal\Pages::format('delete', true,  'callable', "Delete the singular. The \$_REQUEST[\$key] value is repeated as argument to that callable. This function should return false if a problem occurs."),
				'key'    => \LWS\Adminpanel\Internal\Pages::format('key',    false, 'string',   "We look at \$_REQUEST, the form will be displayed only if the key exists. Else we show the regular page.")
			),
			"{$this->id}['singular_edit']"
		);
	}

	public function content()
	{
		\wp_enqueue_style('lws-admin-page');
		//\wp_enqueue_style('lws-singular');
		echo "<div class='lws-singular-body' role='main'>";

		$formId = 'lws_adminpanel_singular_form_'.$this->id;
		$formAttr = '';
		foreach( \apply_filters('lws_adminpanel_singular_form_attributes_'.$this->id, array()) as $attr => $value )
			$formAttr .= " $attr='" . esc_attr($value) . "'";
		echo "<div id='lws-singular-singular-wrap'><form id='$formId' name='$formId' method='post'$formAttr>";

		// hidden fields for validation
		\wp_nonce_field($formId, '_lws_ap_single_nonce', true, true);
		$value = \esc_attr(\get_current_user_id());
		echo "<input type='hidden' name='editor' value='$value'>";
		if( isset($this->data['singular_edit']['save']) )
		{
			$value = empty($this->singularId) ? 'create' : 'update';
			echo "<input type='hidden' name='hiddenaction' value='$value'>";
		}
		$value = \esc_attr($this->singularId);
		echo "<input type='hidden' name='singular_id' value='$value'>";

		echo "<div id='lws-adminpanel-singular-holder' class='lws-metabox-holder'>"; // metabox holder
		echo "<div id='lws-adminpanel-singular-container-1' class='postbox-left'>"; // postbox-container 1

		// the content
		echo "<div id='lws-adminpanel-singular-edit' class='singular-edit'>";
		echo "<div id='lws-adminpanel-singular-edit-main' class='main singular-box'>";
		$ok = call_user_func($this->data['singular_edit']['form'], $this->singularId);
		echo "</div><div class='meta'>";
		\do_action('lws_adminpanel_singular_form_'.$this->id, $this->singularId);
		echo "</div></div>"; // ## lws-adminpanel-singular-edit-main ## lws-adminpanel-singular-edit

		echo "</div>"; // ## end of postbox-container 1

		// button group (save, delete and anything else)
		echo "<div id='lws-adminpanel-singular-container-2' class='postbox-right'>"; // postbox-container 2
		echo "<div id='lws-adminpanel-singular-actions' class='singular-actions lws-adminpanel-singular-actionbox'>"; // meta-box-sortables
		if( $ok !== false )
		{
			$publish = '';
			if( !empty($this->singularId) && !empty($this->singularKey) && isset($this->data['singular_edit']['delete']) )
			{
				$delete = array(
					'btn' => _x("Delete element", "Singular object edition screen", 'lws-adminpanel'),
					'yes' => esc_attr(_x("I really want to delete it", "Singular object edition screen", 'lws-adminpanel')),
					'no' => esc_attr(_x("Cancel", "Singular object edition screen", 'lws-adminpanel')),
					'confirm' => _x("This element will be permanently removed. Are you sure?", "Singular object edition screen", 'lws-adminpanel'),
					'title' => esc_attr(_x("Permanent deletion", "Singular object edition screen", 'lws-adminpanel'))
				);

				$args = array(
					'page' => $this->id,
					'action' => 'delete',
					$this->singularKey => $this->singularId,
					'lws-nonce' => \wp_create_nonce($this->id . '-' . $this->singularId)
				);
				$href = \esc_attr(\add_query_arg($args, \admin_url('admin.php')));

				$publish .= "<a class='lws-adminpanel-singular-delete-button' data-yes='{$delete['yes']}' data-no='{$delete['no']}' href='$href'>{$delete['btn']}</a>";
				$publish .= "<div style='display:none;' title='{$delete['title']}' class='lws-adminpanel-singular-delete-confirmation'>{$delete['confirm']}</div>";
			}

			if( isset($this->data['singular_edit']['save']) )
			{
				$submit = empty($this->singularId) ? _x("Create", "Singular object creation screen", 'lws-adminpanel') : _x("Update", "Singular object edition screen", 'lws-adminpanel');
				$publish .= "<div class='singular-metabox-action'>";
				$publish .= "<button class='lws-adminpanel-singular-commit-button lws-adm-btn button button-primary'>$submit</button>";
				$publish .= "</div>";
			}

			$publish = \apply_filters('lws_adminpanel_singular_buttons_'.$this->id, $publish, $this->singularId);
			if( !empty($publish) )
				echo $this->getActionBox('singular-publishing', __("Publish", 'lws-adminpanel'), $publish, 'lws-adminpanel-singular-publish-actions');

			/** Hook lws_adminpanel_singular_boxes_{$page_id}
			 * List the available meta boxes.
			 * @param 1 default boxes (empty array)
			 * @param 2 the singular page id
			 * @return array as $box_id => array( 'title' => (strirg), 'css' => (css classname) ) */
			foreach( \apply_filters('lws_adminpanel_singular_boxes_'.$this->id, array(), $this->singularId) as $boxId => $box)
			{
				if( !is_array($box) )
					$box = array('title'=>$box);
				/** Hook lws_adminpanel_singular_box_content_{$page_id}_{$box_id}
				 * @param 1 default content (empty string)
				 * @param 2 the singular page id
				 * @return (string) box html content. */
				$content = \apply_filters('lws_adminpanel_singular_box_content_'.$this->id.'_'.$boxId, '', $this->singularId);
				echo $this->getActionBox($boxId, isset($box['title']) ? $box['title'] : '', $content, isset($box['css']) ? $box['css'] : '');
			}
		}
		echo "</div></div>"; // ## meta-box-sortables ## postbox-container 2
		echo "</div>"; // ## metabox holder ## poststuff
		echo "</form></div>"; // ## wrap and form
		echo "</div>";
	}

	protected function getActionBox($id, $title, $content, $css='')
	{
		$attrId = empty($id) ? '' : " id='$id'";
		$class = 'singular-metabox lws-postbox';
		if( !empty($css) )
			$class .= ' ' . $css;
		$html = "<div$attrId class='$class'>";
		$html .= "<h2 class='lws-singular-postbox-title'><span>{$title}</span></h2>";
		$html .= "<div class='inside'>{$content}</div></div>";
		return $html;
	}

	/** @return (bool) singular should still be displayed. */
	protected function doAction()
	{
		if( isset($this->data['rights']) && !empty($this->data['rights']) )
		{
			if( !\current_user_can($this->data['rights']) )
			{
				\lws_admin_add_notice_once('singular_edit', __("Action rejected for current user. Insufficient capacities.", 'lws-adminpanel'), array('level'=>'error'));
				return;
			}
		}

		if( isset($_GET['action']) && $_GET['action'] == 'delete' )
			$this->delete();
		elseif( isset($_POST['hiddenaction']) && in_array($_POST['hiddenaction'], array('create', 'update')) )
			$this->update();
	}

	/** Call save callable then redirect to avoid input reposting. */
	protected function update()
	{
		$formId = 'lws_adminpanel_singular_form_'.$this->id;
		$doaction = true;
		// trustable origin
		if( !isset($_POST['_lws_ap_single_nonce']) )
			$doaction = false;
		elseif( !\check_admin_referer($formId, '_lws_ap_single_nonce') )
			$doaction = false;
		elseif( !\wp_verify_nonce($_POST['_lws_ap_single_nonce'], $formId) )
			$doaction = false;
		elseif( !isset($this->data['singular_edit']['save']) )
			$doaction = false;

		if( $doaction )
		{
			\lws_admin_add_notice_once('singular_edit', __("Your settings have been saved.", 'lws-adminpanel'), array('level'=>'success'));
			$id = call_user_func($this->data['singular_edit']['save'], $this->singularId);

			if( empty($this->singularId) && (is_string($id) || is_numeric($id) || is_bool($id)) )
				$this->singularId = sanitize_key($id);
			\do_action('lws_adminpanel_singular_update_'.$this->id, $this->singularId);

			// redirection
			$args = array('page' => $this->id);
			if( !empty($this->singularKey) )
				$args[$this->singularKey] = $this->singularId;
			$redirect_to = \add_query_arg($args, \admin_url('admin.php'));
			\wp_redirect($redirect_to, 303);
			exit;
		}
	}

	protected function delete()
	{
		if( !empty($this->singularId) )
		{
			$args = array('page' => $this->id);

			if( !isset($this->data['singular_edit']['delete']) )
			{
				\lws_admin_add_notice_once('singular_edit', _x("Unavailable action.", "post deletion", 'lws-adminpanel'), array('level'=>'error'));
				if( !empty($this->singularKey) )
					$args[$this->singularKey] = $this->singularId;
			}
			elseif( !isset($_GET['lws-nonce']) || !\wp_verify_nonce($_GET['lws-nonce'], $this->id . '-' . $this->singularId) )
			{
				\lws_admin_add_notice_once('singular_edit', _x("Security check failed.", "post deletion", 'lws-adminpanel'), array('level'=>'error'));
				if( !empty($this->singularKey) )
					$args[$this->singularKey] = $this->singularId;
			}
			else // trustable origin
			{
				\lws_admin_add_notice_once('singular_edit', __("Element permanently removed.", 'lws-adminpanel'), array('level'=>'success'));

				if( false !== call_user_func($this->data['singular_edit']['delete'], $this->singularId) )
					\do_action('lws_adminpanel_singular_delete_'.$this->id, $this->singularId);
				else if( !empty($this->singularKey) )
					$args[$this->singularKey] = $this->singularId;
			}

			// redirection
			$redirect_to = \add_query_arg($args, \admin_url('admin.php'));
			\wp_redirect($redirect_to, 303);
			exit;
		}
	}
}
