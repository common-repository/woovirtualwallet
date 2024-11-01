<?php
namespace LWS\Adminpanel\Pages\Field;

/** Provides a Theme selector for complex html templates
 * @param extra['css'] a url (@see plugins_dir()) to the css;
 *  we require an url as wp_enqueue_style requires it.
 * @param extra['template'] name of the template, is set to $template before calling the demo.
 * @param extra['prefix'] prefix string used in the CSS file to refer to variables
 *
 * The CSS file must provide 7 default variables and all color values must use one of those variables
 * The KeyWord 'prefix' is a reference to the prefix param sent as an extra
 * --prefix-main-color : Main Theme color
 * --prefix-second-color : Second Theme color
 * --prefix-third-color : Third Theme color
 * --prefix-fourth-color : Fourth Theme color
 * --prefix-text-color : Text color
 * --prefix-title-color : Title color
 * --prefix-hightlight-color : Text highlight color */

class Themer extends \LWS\Adminpanel\Pages\Field
{
	public function __construct($id, $title, $extra=null)
	{
		parent::__construct($id, $title, $extra);
		add_action('admin_enqueue_scripts', array($this, 'script'));
		add_action("update_option_{$id}", array( $this, 'revokeCache'), 10, 3);
		add_action('admin_head', array($this, 'reset'));
	}

	public function input()
	{
		if( $this->is_valid() )
		{
			$prefix = $this->extra['prefix'];
			$theValues = $this->getBalises($this->extra['css']);
			if( !$theValues ){
				return;
			}
			$cssValues = base64_encode(json_encode($theValues));
			$type = $theValues["--type"];
			$themerContent = $this->getBody();
			$lightThemes = $this->getLightThemes($prefix);
			$darkThemes = $this->getDarkThemes($prefix);

			$themer = <<<EOT
			<div class='lws_themer lws-themer-main' data-cssvalues='{$cssValues}' data-prefix='{$prefix}' data-type='{$type}'>
				<input name='{$this->m_Id}' type='hidden' value='' class='lws_themer_chain'/>
				<div class='lws-themer-top'>
					{$themerContent}
					<div class='lws-themer-themes-panel'>
						<div class='lws-themer-light-themes'>
							<div class='lws-themer-light-title'>Light Themes</div>
							<div class='lws-themer-tlist-container'>{$lightThemes}</div>
						</div>
						<div class='lws-themer-dark-themes'>
							<div class='lws-themer-dark-title'>Dark Themes</div>
							<div class='lws-themer-tlist-container'>{$darkThemes}</div>
						</div>
					</div>
				</div>
				<div class='lws-themer-control'>
					<div class='lws-themer-butcont'>
						<div class='lws-themer-themsel-button'>Select a Theme</div>
					</div>
					<div class='lws-themer-cpcont'>
						<div class='lws-themer-cpline'>
							<div class='lws-color-picker' id='main-color' data-title='Main Color'></div>
							<div class='lws-color-picker' id='second-color' data-title='Second Color'></div>
							<div class='lws-color-picker' id='third-color' data-title='Third Color'></div>
							<div class='lws-color-picker' id='fourth-color' data-title='Fourth Color'></div>
						</div>
						<div class='lws-themer-cpline'>
							<div class='lws-color-picker' id='text-color' data-title='Text Color'></div>
							<div class='lws-color-picker' id='title-color' data-title='Title Color'></div>
							<div class='lws-color-picker' id='highlight-color' data-title='Highlight Color'></div>
						</div>
					</div>
					<div class='lws-themer-lightcont'>
						<div class='lws-themer-light-button'>
							<div class='lws-themer-lb-light lws-icon lws-icon-bulb'></div>
							<div class='lws-themer-lb-dark lws-icon lws-icon-bulb'></div>
						</div>
					</div>
				</div>
			</div>
EOT;
			echo $themer;
		}
	}

	public function script()
	{
		wp_enqueue_script('lws-themer', LWS_ADMIN_PANEL_JS . '/controls/themer.js', array('jquery', 'jquery-ui-core', 'jquery-ui-widget', 'lws-base64'), LWS_ADMIN_PANEL_VERSION, true);
		wp_enqueue_style($this->id(), \add_query_arg('themer', $this->id(), $this->extra['css']), array(), strval(time())); // set version to timestamp to force no buffering
	}

	// function to get the html file's body only
	protected function getBody()
	{
		$template = $this->getExtraValue('template');
		$page = \apply_filters('lws_adminpanel_themer_content_get_'.$template, '');

		$d = new \DOMDocument;
		$d->loadHTML('<?xml encoding="utf-8" ?>'.$page, LIBXML_NOERROR|LIBXML_NOWARNING);
		$body = $d->getElementsByTagName('body')->item(0);
		$innerHTML = "";
		if (!empty($body)) {
			foreach ($body->childNodes as $child) {
				$innerHTML .= $body->ownerDocument->saveHTML($child);
			}
		} else {
			error_log("themer {$this->m_Id} got no content.");
		}
		return $innerHTML;
	}

	protected function getLightThemes($prefix)
	{
		$lightThemes = array(
			array(
				"title" => "Red",
				"colors" => array(
					$prefix . "main-color" => "#cc1d25",$prefix . "second-color" => "#333333",$prefix . "third-color" => "#cccccc",
					$prefix . "fourth-color" => "#dddddd",$prefix . "text-color" => "#333333",$prefix . "title-color" => "#ffffff",
					$prefix . "highlight-color" => "#aa1d25", "--type" => "light"
				)
			),
			array(
				"title" => "Blue",
				"colors" => array(
					$prefix . "main-color" => "#122e95",$prefix . "second-color" => "#333333",$prefix . "third-color" => "#cccccc",
					$prefix . "fourth-color" => "#dddddd",$prefix . "text-color" => "#333333",$prefix . "title-color" => "#ffffff",
					$prefix . "highlight-color" => "#3fa9c5", "--type" => "light"
				)
			),
			array(
				"title" => "Purple",
				"colors" => array(
					$prefix . "main-color" => "#880077",$prefix . "second-color" => "#333333",$prefix . "third-color" => "#cccccc",
					$prefix . "fourth-color" => "#dddddd",$prefix . "text-color" => "#333333",$prefix . "title-color" => "#ffffff",
					$prefix . "highlight-color" => "#cc00aa", "--type" => "light"
				)
			),
			array(
				"title" => "Green",
				"colors" => array(
					$prefix . "main-color" => "#008f40",$prefix . "second-color" => "#333333",$prefix . "third-color" => "#cccccc",
					$prefix . "fourth-color" => "#dddddd",$prefix . "text-color" => "#333333",$prefix . "title-color" => "#ffffff",
					$prefix . "highlight-color" => "#5be88b", "--type" => "light"
				)
			),
			array(
				"title" => "Orange",
				"colors" => array(
					$prefix . "main-color" => "#d37900",$prefix . "second-color" => "#333333",$prefix . "third-color" => "#cccccc",
					$prefix . "fourth-color" => "#dddddd",$prefix . "text-color" => "#333333",$prefix . "title-color" => "#ffffff",
					$prefix . "highlight-color" => "#eab00f", "--type" => "light"
				)
			),
			array(
				"title" => "Yellow",
				"colors" => array(
					$prefix . "main-color" => "#efe300",$prefix . "second-color" => "#aaaaaa",$prefix . "third-color" => "#cccccc",
					$prefix . "fourth-color" => "#dddddd",$prefix . "text-color" => "#000000",$prefix . "title-color" => "#111111",
					$prefix . "highlight-color" => "#fff200", "--type" => "light"
				)
			),
			array(
				"title" => "Oil",
				"colors" => array(
					$prefix . "main-color" => "#005555",$prefix . "second-color" => "#334444",$prefix . "third-color" => "#bbdddd",
					$prefix . "fourth-color" => "#ccdddd",$prefix . "text-color" => "#333333",$prefix . "title-color" => "#ffffff",
					$prefix . "highlight-color" => "#007777", "--type" => "light"
				)
			),
			array(
				"title" => "Gold",
				"colors" => array(
					$prefix . "main-color" => "#daa520",$prefix . "second-color" => "#443300",$prefix . "third-color" => "#ffcc77",
					$prefix . "fourth-color" => "#ffddaa",$prefix . "text-color" => "#333333",$prefix . "title-color" => "#ffffff",
					$prefix . "highlight-color" => "#ffd700", "--type" => "light"
				)
			),
			array(
				"title" => "Pink",
				"colors" => array(
					$prefix . "main-color" => "#ff1493",$prefix . "second-color" => "#531343",$prefix . "third-color" => "#fcacdc",
					$prefix . "fourth-color" => "#dddddd",$prefix . "text-color" => "#333333",$prefix . "title-color" => "#ffffff",
					$prefix . "highlight-color" => "#ff96b4", "--type" => "light"
				)
			),
			array(
				"title" => "Maroon",
				"colors" => array(
					$prefix . "main-color" => "#802000",$prefix . "second-color" => "#534333",$prefix . "third-color" => "#fcdccc",
					$prefix . "fourth-color" => "#fdeddd",$prefix . "text-color" => "#333333",$prefix . "title-color" => "#ffffff",
					$prefix . "highlight-color" => "#b24222", "--type" => "light"
				)
			),
			array(
				"title" => "Turquoise",
				"colors" => array(
					$prefix . "main-color" => "#00ced1",$prefix . "second-color" => "#334353",$prefix . "third-color" => "#ccdcec",
					$prefix . "fourth-color" => "#ddedfd",$prefix . "text-color" => "#333333",$prefix . "title-color" => "#ffffff",
					$prefix . "highlight-color" => "#40e0d0", "--type" => "light"
				)
			),
			array(
				"title" => "Grey",
				"colors" => array(
					$prefix . "main-color" => "#666666",$prefix . "second-color" => "#333333",$prefix . "third-color" => "#cccccc",
					$prefix . "fourth-color" => "#dddddd",$prefix . "text-color" => "#333333",$prefix . "title-color" => "#ffffff",
					$prefix . "highlight-color" => "#999999", "--type" => "light"
				)
			),
			array(
				"title" => "Black",
				"colors" => array(
					$prefix . "main-color" => "#000000",$prefix . "second-color" => "#333333",$prefix . "third-color" => "#cccccc",
					$prefix . "fourth-color" => "#dddddd",$prefix . "text-color" => "#333333",$prefix . "title-color" => "#ffffff",
					$prefix . "highlight-color" => "#444444", "--type" => "light"
				)
			),
		);
		$lightThemesDivs = "";
		foreach ($lightThemes as $item) {
			$colors = base64_encode(json_encode($item['colors']));
			$lightThemesDivs .= "<div class='lws-themer-theme-div' data-title='{$item['title']}' data-colors='{$colors}' data-type='light'></div>";
		}
		return $lightThemesDivs;
	}

	protected function getDarkThemes($prefix)
	{
		$darkThemes = array(
			array(
				"title" => "Light Red",
				"colors" => array(
					$prefix . "main-color" => "#ff7070",$prefix . "second-color" => "#bbbbbb",$prefix . "third-color" => "#777777",
					$prefix . "fourth-color" => "#555555",$prefix . "text-color" => "#eeeeee",$prefix . "title-color" => "#333333",
					$prefix . "highlight-color" => "#ffdddd", "--type" => "dark"
				)
			),
			array(
				"title" => "Light Blue",
				"colors" => array(
					$prefix . "main-color" => "#3fa9f5",$prefix . "second-color" => "#bbbbbb",$prefix . "third-color" => "#666666",
					$prefix . "fourth-color" => "#444444",$prefix . "text-color" => "#eeeeee",$prefix . "title-color" => "#333333",
					$prefix . "highlight-color" => "#8fbfff", "--type" => "dark"
				)
			),
			array(
				"title" => "Light Green",
				"colors" => array(
					$prefix . "main-color" => "#66ffaa",$prefix . "second-color" => "#bbbbbb",$prefix . "third-color" => "#555555",
					$prefix . "fourth-color" => "#222222",$prefix . "text-color" => "#eeeeee",$prefix . "title-color" => "#333333",
					$prefix . "highlight-color" => "#99ffcc", "--type" => "dark"
				)
			),
			array(
				"title" => "Orange",
				"colors" => array(
					$prefix . "main-color" => "#e39950",$prefix . "second-color" => "#bbbbbb",$prefix . "third-color" => "#555555",
					$prefix . "fourth-color" => "#222222",$prefix . "text-color" => "#eeeeee",$prefix . "title-color" => "#333333",
					$prefix . "highlight-color" => "#fac05f", "--type" => "dark"
				)
			),
			array(
				"title" => "Yellow",
				"colors" => array(
					$prefix . "main-color" => "#efe300",$prefix . "second-color" => "#bbbbbb",$prefix . "third-color" => "#555555",
					$prefix . "fourth-color" => "#222222",$prefix . "text-color" => "#eeeeee",$prefix . "title-color" => "#333333",
					$prefix . "highlight-color" => "#fff200", "--type" => "dark"
				)
			),
			array(
				"title" => "Grey",
				"colors" => array(
					$prefix . "main-color" => "#dddddd",$prefix . "second-color" => "#bbbbbb",$prefix . "third-color" => "#555555",
					$prefix . "fourth-color" => "#333333",$prefix . "text-color" => "#eeeeee",$prefix . "title-color" => "#333333",
					$prefix . "highlight-color" => "#ffffff", "--type" => "dark"
				)
			),
			array(
				"title" => "White",
				"colors" => array(
					$prefix . "main-color" => "#f5f5f5",$prefix . "second-color" => "#bbbbbb",$prefix . "third-color" => "#555555",
					$prefix . "fourth-color" => "#222222",$prefix . "text-color" => "#eeeeee",$prefix . "title-color" => "#333333",
					$prefix . "highlight-color" => "#ffffff", "--type" => "dark"
				)
			),
		);
		$darkThemesDivs = "";
		foreach ($darkThemes as $item) {
			$colors = base64_encode(json_encode($item['colors']));
			$darkThemesDivs .= "<div class='lws-themer-theme-div' data-title='{$item['title']}' data-colors='{$colors}' data-type='dark'></div>";
		}
		return $darkThemesDivs;
	}


	protected function is_valid()
	{
		if (!isset($this->extra['css'])) {
			error_log("No lwss file provided");
			return false;
		}
		return true;
	}

	/** If user never set a value, it got file content */
	protected function getBalises($cssFile)
	{
		// from db
		$values = get_option($this->m_Id, false);
		if( $values )
		{
			if( $css = base64_decode($values) )
			{
				$loading = new \LWS\Adminpanel\Tools\PseudoCss();
				$loading->fromString($css);
				foreach( $loading->getBalises() as $balise )
				{
					if( ':root' == $balise->Selector )
						return $balise->Defaults;
				};
			}
		}

		// fallback in css file
		$loading = new \LWS\Adminpanel\Tools\PseudoCss();
		$loading->extract($loading->relevantUrlPart($cssFile), false, false);
		foreach( $loading->getBalises() as $balise )
		{
			if( ':root' == $balise->Selector )
				return $balise->Defaults;
		};

		error_log("The Themer requires a template css with :root selector. See ".$cssFile);
		return '';
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
		if ($force || (isset($_POST[$resetId]) && boolval($_POST[$resetId]))) {
			delete_option($this->m_Id);
			$this->revokeCache('', '', $this->m_Id);
		}
	}
}
