<?php
namespace LWS\Adminpanel\Pages\Field;

/** Provide a CSS graphic editor.
 * User is shepherded by a html demo with selectable and editable elements.
 * Use an extra (array) argument with:
 * @param extra['html'] a path (local file path) to a html or php local file used for demo;
 * 	we require a local path since it is include as any html/php page.
 * @param extra['css'] a url (@see plugins_dir()) to the css;
 *  we require an url as wp_enqueue_style requires it.
 * @param extra['subids'] register addionnal stored input values managed by the field.
 *  we expect an array with key the input name and value a human readable title
 *  (do not use __() function on that title since it will be a key for WPML).
 *
 * @param extra['template'] name of the template, is set to $template before calling the demo.
 *  In your demo snippet, you can use filter 'lws_mail_snippet' to get a settings value.
 *
 * If no 'html' set, a 'purpose' can be defined, in this case, a 'template' value must be set too.
 * 'purpose value can be:
 * * 'mail' using mailer, a content will be get via mail the $template body filter given a wp_error as data source.
 * * 'action' the action hook 'lws_adminpanel_stygen_content_echo_'.$template is called, content must be write on standard output.
 * * 'filter' the filter hook 'lws_adminpanel_stygen_content_get_'.$template is applied, content must be returned as a string.
 *
 * Note that $template global variable can be used inside the stygen content. */
class StyGen extends \LWS\Adminpanel\Pages\Field
{

	public function __construct($id, $title, $extra=null)
	{
		parent::__construct($id, $title, $extra);
		\add_action('admin_enqueue_scripts', array($this, 'script'));
		\add_action("update_option_{$id}", array( $this, 'revokeCache'), 10, 3);
		\add_action('admin_head', array($this, 'reset'));
	}

	public function input()
	{
		if($this->is_valid())
		{
			$balises = $this->getBalises($this->extra['css']);

			$cssvalues = base64_encode(json_encode(array_values($balises->user)));
			$dftvalues = base64_encode(json_encode(array_values($balises->file)));

			$labels = array(
				__("Available Elements", 'lws-adminpanel'),
				__("Select an element to start modifying its style", 'lws-adminpanel'),
				esc_attr(_x("Reset Style", "Stygen button", 'lws-adminpanel'))
			);
			$lwsseditor = null;
			$lwsseditor = "<div class='lwss_editor lwss-editor' data-cssvalues='{$cssvalues}' data-dftvalues='{$dftvalues}'>";
			$lwsseditor .= "<input name='{$this->m_Id}' type='hidden' value='' class='lwss_editor_chain'/>";

			if( isset($this->extra['subids']) )
			{
				$subids = is_array($this->extra['subids']) ? $this->extra['subids'] : array($this->extra['subids']);
				foreach( $subids as $k => $v )
				{
					$sub = is_string($k) ? $k : $v;
					$lwsseditor .= "<input name='{$sub}' type='hidden' value='".esc_attr(\get_option($sub))."'/>";
				}
			}
			$lwsseditor .= "<div class='lwss-visual-conteneur'>";
			$lwsseditor .= "<div class='lwss_sidemenu lwss-sidemenu'><div class='lwss-sidemenu-title'>{$labels[0]}</div></div>";
			$lwsseditor .= "<div class='lwss_centraldiv'>";
			$lwsseditor .= "<div class='lwss_info'>{$labels[1]}</div>";
			$lwsseditor .= "<div class='lwss-main-conteneur lwss_canvas lwss-canvas'>";
			$lwsseditor .= $this->getBody($this->extra['html']);
			$lwsseditor .= "</div></div></div>";
			$lwsseditor .= "<div class='lwss-css-conteneur'>";
			$lwsseditor .= "<div class='lwss_css_editor lwss-css-editor'></div>";
			$lwsseditor .= "<div class='lwss_css_centraldiv'>";
			$lwsseditor .= "<div class='lwss-css-main-conteneur lwss_canvas lwss-canvas'>";
			$lwsseditor .= $this->getBody($this->extra['html']);
			$lwsseditor .= "</div></div></div>";
			$lwsseditor .= "<div class='lwss-bottom-row'>";
			$lwsseditor .= "<div class='lwss-bottom-row-title'>Control Panel</div>";
			$lwsseditor .= "<div class='lwss-btvisual lws-active'>Visual Editor</div>";
			$lwsseditor .= "<div class='lwss-btcss'>CSS Editor</div>";
			$lwsseditor .= "<input class='lwss-reset' data-id='{$this->m_Id}' type='button' value='{$labels[2]}' />";
			$lwsseditor .= "</div>";
			$lwsseditor .= '</div>';
			echo $lwsseditor;
		}
	}

	public function script()
	{
		$cm_settings['codeEditor'] = wp_enqueue_code_editor(array('type' => 'text/css'));
		wp_enqueue_script('wp-theme-plugin-editor');
		wp_enqueue_style('wp-codemirror');
		wp_enqueue_script( 'lws-stygen-font', LWS_ADMIN_PANEL_JS . '/controls/stygen/stygenfont.js', array('jquery', 'lws-base64'), LWS_ADMIN_PANEL_VERSION, true );
		wp_enqueue_script( 'lws-stygen-fields', LWS_ADMIN_PANEL_JS . '/controls/stygen/stygenfields.js', array('jquery', 'lws-adminpanel-autocomplete'), LWS_ADMIN_PANEL_VERSION, true );
		wp_enqueue_script( 'lws-stygen-panel', LWS_ADMIN_PANEL_JS . '/controls/stygen/stygenpanel.js', array('lws-stygen-fields','lws-stygen-font'), LWS_ADMIN_PANEL_VERSION, true );
		wp_enqueue_script( 'lws-stygen', LWS_ADMIN_PANEL_JS . '/controls/stygen/stygen.js', array('lws-base64','lws-stygen-panel'), LWS_ADMIN_PANEL_VERSION, true );
		wp_localize_script('lws-stygen', 'cm_settings', $cm_settings);
		wp_enqueue_style( $this->id(), \add_query_arg('stygen', $this->id(), $this->extra['css']), array(), strval(time()) ); // set version to timestamp to force no buffering
	}

	// function to get the html file's body only
	protected function getBody($url)
	{
		$lws_stygen = $this->id();
		$template = $this->getExtraValue('template');
		$page = '';
		if( $url !== false )
		{
			ob_start();
			require($url);
			$page = ob_get_contents();
			ob_end_clean();
		}
		else if( $this->getExtraValue('purpose') == 'mail' )
		{
			require_once LWS_ADMIN_PANEL_INCLUDES . '/internal/mailer.php';
			$page = \LWS\Adminpanel\Internal\Mailer::instance()->getDemo($template);
		}
		else if( $this->getExtraValue('purpose') == 'action' )
		{
			ob_start();
			\do_action('lws_adminpanel_stygen_content_echo_'.$template);
			$page = ob_get_contents();
			ob_end_clean();
		}
		else if( $this->getExtraValue('purpose') == 'filter' )
		{
			$page = \apply_filters('lws_adminpanel_stygen_content_get_'.$template, '');
		}
		else
			return __("Snippet unknown", 'lws-adminpanel');

		$d = new \DOMDocument;
		@$d->loadHTML('<?xml encoding="utf-8" ?>'.$page, LIBXML_NOERROR|LIBXML_NOWARNING);
		$body = $d->getElementsByTagName('body')->item(0);
		$innerHTML = "";
		if( !empty($body) )
		{
			foreach( $body->childNodes as $child )
				$innerHTML .= $body->ownerDocument->saveHTML($child);
		}
		else
			error_log("stygen {$this->m_Id} got no content.");
		return $innerHTML;
	}

	protected function is_valid()
	{
		if(!isset($this->extra['html']))
		{
			error_log("No html file provided");
			return false;
		}
		if(!isset($this->extra['css']))
		{
			error_log("No lwss file provided");
			return false;
		}
		return true;
	}

	/** If user never set a value, it got file content */
	protected function getBalises($cssFile)
	{
		$pseudocss = new \LWS\Adminpanel\Tools\PseudoCss();
		$pseudocss->extract($pseudocss->relevantUrlPart($cssFile), false, false);

		$balises = (object)array(
			'file' => $pseudocss->getBalises(),
			'user' => array()
		);

		$values = get_option($this->m_Id, false);
		if( false !== $values )
		{
			if( $css = base64_decode($values) )
			{
				$loading = new \LWS\Adminpanel\Tools\PseudoCss();
				$loading->fromString($css);
				$balises->user = $loading->getBalises();
			}
		}
		else
			$balises->user = $balises->file;

		return $balises;
	}

	/** from do_action( 'updated_option', $option, $old_value, $value );
	 *	or do_action( "update_option_{$option}", $old_value, $value, $option );
	 *	@brief delete any cache. */
	public function revokeCache($old_value, $value, $option)
	{
		$filename = sanitize_key($this->m_Id) . '-cached.css';
		$cached = new \LWS\Adminpanel\Tools\Cache($filename);
		$cached->del();
		\update_option($option.'_adm_ts', time());
	}

	/**	Erase cache and database value. Require a $_POST["lws-stygen-reset-{$this->m_Id}"] = true */
	public function reset($force=false)
	{
		$resetId = "lws-stygen-reset-{$this->m_Id}";
		if( $force || (isset($_POST[$resetId]) && boolval($_POST[$resetId])) )
		{
			delete_option($this->m_Id);
			$this->revokeCache('', '', $this->m_Id);
		}
	}

}
