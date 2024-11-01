<?php
namespace LWS\Adminpanel\Pages;
if( !defined( 'ABSPATH' ) ) exit();


/**  */
abstract class Page
{
	/** Echo page content */
	abstract public function content();
	/** Show nothing but prepare data */
	abstract protected function prepare();
	/** Return string with admin, resume, singular */
	abstract public function getType();

	static protected $adminNotices = '';

	/** echo page content container */
	public function page()
	{
		echo "<div class='lws-adminpanel'>";
		$this->getHead()->showStickyPanel($this->getType());
		$this->getHead()->showTransientNotices();

		if( isset($this->data['text']) ){
			if( \is_array($this->data['text']) )
				$this->data['text'] = \lws_array_to_html($this->data['text']);
			echo "<div class='lws-description'>{$this->data['text']}</div>";
		}

		$this->content();
		echo "</div>";
	}

	/**	@param $id (string) page slug (GET argument 'page')
	 *	@param $data (array) detail/content of the page
	 *	@param $parent (string) slug of the WordPress menu entry page
	 *	@param $register (bool) include the page in the WordPress side menu
	 *	@return (object) instance of a class that extends Page */
	public static function create($id, $data, $parent=null, $register=false)
	{
		$page = false;
		if( isset($data['resume']) && $data['resume'] )
		{
			$page = new \LWS\Adminpanel\Pages\Screens\Resume();
		}
		else if( isset($data['singular_edit']) && isset($data['singular_edit']['key']) )
		{
			$key = $data['singular_edit']['key'];
			if( $key && isset($_REQUEST[$key]) )
				$page = new \LWS\Adminpanel\Pages\Screens\Singular();
		}

		if( !$page )
			$page = new \LWS\Adminpanel\Pages\Screens\Admin();
		$page->setData($id, $data, $parent);

		if( $register )
			$page->registerMenu();

		return $page;
	}

	/** to be overriden
	 * @return (bool) is a special page (Resume instance) */
	public function isResume()
	{
		return false;
	}

	/** to be overriden
	 * @return (bool) Save button must be displayed */
	public function allowSubmit()
	{
		return false;
	}

	/** to be overriden
	 * @return array of Group instances */
	public function getGroups()
	{
		return array();
	}

	protected function setData($id, $data, $parent=null)
	{
		$this->id = urlencode($id);
		$this->data = $data;
		$this->master = $parent;
		$this->action = isset($this->data['action']) ? $this->data['action'] : 'options.php';
		$this->vertnav = isset($this->data['vertnav']) ? $this->data['vertnav'] : false;
		$this->description = isset($this->data['description']) ? $this->data['description'] : '';
		$this->color = isset($this->data['color']) ? $this->data['color'] : '';
		$this->image = isset($this->data['image']) ? $this->data['image'] : '';
		$this->custom = array(
			'top' => array(),
			'bot'  => array(),
		);
		if( isset($this->data['function']) )
			$this->custom['top'][] = $this->data['function'];
		if( isset($this->data['delayedFunction']) )
			$this->custom['bot'][] = $this->data['delayedFunction'];
	}

	/** @return a well formated format array for Pages::test()
		* @see Pages::test() */
	public static function format()
	{
		return array(
			'id'		=> \LWS\Adminpanel\Internal\Pages::format('id',			false, 'string', "Identify a page"),
			'rights'	=> \LWS\Adminpanel\Internal\Pages::format('rights',		false, 'string', "User capacity required to access to this page. Usually 'manage_options'. A tab could be locally more restrictive"),
			'title'		=> \LWS\Adminpanel\Internal\Pages::format('title',		false, 'string', "Display at top of the page and in the menu."),
			'subtitle'	=> \LWS\Adminpanel\Internal\Pages::format('subtitle',	true, 'string', "Display after title on top of the page and replace title in sub menu."),
			'text'		=> \LWS\Adminpanel\Internal\Pages::format('text',		true, '', "A free text displayed at top of the page, after the title banner. If array given, see \lws_array_to_html()"),
			'subtext'	=> \LWS\Adminpanel\Internal\Pages::format('subtext',	true, '', "A free text displayed after the tab line but before tab content. If array given, see \lws_array_to_html()"),
			'description'=> \LWS\Adminpanel\Internal\Pages::format('description',true, '', "An html text explaining in details the purpose of the page. If array given, see \lws_array_to_html()"),
			'color'		=> \LWS\Adminpanel\Internal\Pages::format('color',		true, 'string', "Optional color for resume display"),
			'image'		=> \LWS\Adminpanel\Internal\Pages::format('image',		true, 'string', "Image to display in resume group"),
			'vertnav'   => \LWS\Adminpanel\Internal\Pages::format('vertnav',	true, 'bool', "Defines if the page accepts a vertical groups navigation tool"),
			'dashicons' => \LWS\Adminpanel\Internal\Pages::format('dashicons',	true, 'string', "The URL to the icon to be used for this menu. Dashicons helper class, base64-encoded SVG or 'none' to let css do the work."),
			'action'    => \LWS\Adminpanel\Internal\Pages::format('action',		true, 'string', "Destination url of the page <form> ('action' attribute) if it must be different than 'options.php'"),
			'index'     => \LWS\Adminpanel\Internal\Pages::format('index',		true, 'int', "The position in the menu order this one should appear."),
			'prebuild'  => \LWS\Adminpanel\Internal\Pages::format('prebuild',	true, 'bool', "The first page of the page array is the main menu entry, following pages are assumed to be submenu. Set true for the first page of the page array (default is false) to ignore content, assuming it is an existant page created by wordpress or another plugin."),
			'toc'       => \LWS\Adminpanel\Internal\Pages::format('toc',		true, 'bool', "TableOfContent. The page should display a table of content (default is true). Can be overwrite by tab."),
			'nosave'    => \LWS\Adminpanel\Internal\Pages::format('nosave',		true, 'bool', "Hide the global 'Save options' button at bottom of admin page (default is false, so show the save button). Could be overwritten by tab."),
			'groups'    => \LWS\Adminpanel\Internal\Pages::format('groups',		true, 'array', "Array of group. A group contains fields. Each group has an entry in Table of Content."),
			'tabs'      => \LWS\Adminpanel\Internal\Pages::format('tabs',		true, 'array', "Array of tab. A tab could contain another tabs level. A tab sould contain groups."),
			'function'	=> \LWS\Adminpanel\Internal\Pages::format('function',	true, 'callable', "A function to echo a custom feature."),
			'delayedFunction'	=> \LWS\Adminpanel\Internal\Pages::format('delayedFunction',	true, 'callable', "Same as function but executed after usual fields display."),
			'singular_edit'   => \LWS\Adminpanel\Internal\Pages::format('singular_edit',	true, 'array', "For single post editition purpose. Could replace regular admin page."),
			'hidden'    => \LWS\Adminpanel\Internal\Pages::format('hidden',		true, 'bool', "If true, this page has no menu entry, build a link with add_query_arg('page', /*this_page_id*/, admin_url('admin.php'))."),
			'resume'    => \LWS\Adminpanel\Internal\Pages::format('resume',		true, 'bool', "Content is self-generated to resume all the admin screens at once. Should be the first one of the pages array."),
		);
	}

	/** @return a well formated format array for Pages::test()
		* @see Pages::test() */
	public static function tabFormat()
	{
		return array(
			'title'    => \LWS\Adminpanel\Internal\Pages::format('title',    false, 'string', "Display to the user."),
			'id'       => \LWS\Adminpanel\Internal\Pages::format('id',       true, 'string', "Identify a tab"),
			'rights'   => \LWS\Adminpanel\Internal\Pages::format('rights',   true, 'string', "User capacity required to access to this tab. Usually 'manage_options'."),
			'action'   => \LWS\Adminpanel\Internal\Pages::format('action',   true, 'string', "Destination url of the page <form> ('action' attribute) if it must be different than 'options.php'"),
			'toc'      => \LWS\Adminpanel\Internal\Pages::format('toc',      true, 'bool', "TableOfContent. The page should display a table of content (default is true). Can be overwrite by tab."),
			'nosave'   => \LWS\Adminpanel\Internal\Pages::format('nosave',   true, 'bool', "Hide the global 'Save options' button at bottom of admin page (default is false, so show the save button). Could be overwritten by tab."),
			'icon'	   => \LWS\Adminpanel\Internal\Pages::format('icon', 	   true, 'string', "Icon class to set on tab header"),
			'groups'   => \LWS\Adminpanel\Internal\Pages::format('groups',   true, 'array', "Array of group. A group contains fields. Each group has an entry in Table of Content."),
			'tabs'     => \LWS\Adminpanel\Internal\Pages::format('tabs',     true, 'array', "Array of tab. A tab could contain another tabs level. A tab sould contain groups."),
			'function' => \LWS\Adminpanel\Internal\Pages::format('function', true, 'callable', "A function to echo a custom feature."),
			'delayedFunction'	=> \LWS\Adminpanel\Internal\Pages::format('delayedFunction',	true, 'callable', "Same as function but executed after usual fields display."),
			'hidden'   => \LWS\Adminpanel\Internal\Pages::format('hidden',   true, 'bool', "If true, this tab does not appea in the menu, build a link with add_query_arg(array('page'=>/*this_page_id*/, 'tab'=>/*the_tab_path*/), admin_url('admin.php'))."),
			'vertnav'  => \LWS\Adminpanel\Internal\Pages::format('vertnav',	 true, 'bool', "Defines if the tab accepts a vertical groups navigation tool"),
		);
	}

	/** Register entry in wordpress lateral admin menu.
	 * @return $this. */
	public function registerMenu()
	{
		if( is_null($this->master) )
		{
			$position = isset($this->data['index']) ? $this->data['index'] : null;
			$position = $this->getFreeMenuPosition($position);
			$this->pageId = add_menu_page($this->getTitle(), $this->getTitle(), $this->data['rights'], $this->id, array($this, 'page'), isset($this->data['dashicons']) ? $this->data['dashicons'] : '', $position);
		}
		else
			$this->pageId = add_submenu_page($this->master, $this->getTitle(), $this->getSubtitle(), $this->data['rights'], $this->id, array($this, 'page'));
		return $this;
	}

	public function build()
	{
		if( !(isset($this->built) && $this->built) )
		{
			$this->filterGraph($this->data, true);
			$this->forceIds($this->data, true);
			$this->mergeGraph($this->data, $this->getPath());

			if( \apply_filters('lws_adminpanel_grab_other_plugin_notices', true) )
				\add_action('in_admin_header', array(\get_class(), 'runNoticeGrabber'));
		}
		$this->prepare();
	}

	static function getAdminNotices()
	{
		return self::$adminNotices;
	}

	/** Let anything during 'admin_notices' written in a buffer.
	 *	Show that buffer content in a dedicated place.
	 *	Purpose is to improve our admin page readability. */
	static function runNoticeGrabber()
	{
		$hooks = \apply_filters('lws_adminpanel_admin_notices_starting_hooks', array('admin_notices', 'user_admin_notices', 'network_admin_notices'));
		foreach( $hooks as $hook )
			\add_action($hook, array(\get_class(), 'startsGrabbingNotices'), PHP_INT_MIN);
		\add_action('all_admin_notices', array(\get_class(), 'endsGrabbingNotices'), PHP_INT_MAX);
	}

	/** @see runNoticeGrabber() */
	static function startsGrabbingNotices()
	{
		\ob_start();
	}

	/** @see runNoticeGrabber() */
	static function endsGrabbingNotices()
	{
		self::$adminNotices = \ob_get_clean();
		return self::$adminNotices;
	}

	function getFreeMenuPosition($index, $step = 0.000001)
	{
		if( !empty($index) )
		{
			global $menu;
			for( $occuped = array_keys($menu) ; in_array("$index", $occuped) ; $index += $step ); // empty for
			$index = "$index";
		}
		return $index;
	}

	function setHead(\LWS\Adminpanel\Pages\Head $head)
	{
		$this->head = $head;
	}

	function &getHead()
	{
		if( !isset($this->head) )
			$this->head = new \LWS\Adminpanel\Pages\Head($this);
		return $this->head;
	}

	public function getId()
	{
		return $this->id;
	}

	function getTitle()
	{
		return $this->data['title'];
	}

	function getSubtitle()
	{
		return isset($this->data['subtitle']) && $this->data['subtitle'] ? $this->data['subtitle'] : $this->data['title'];
	}

	function getParentId()
	{
		return $this->master;
	}

	function &getData()
	{
		return $this->data;
	}

	function getTabs()
	{
		return isset($this->data['tabs']) ? $this->data['tabs'] : array();
	}

	/** @param $user (int|WP_User)
	 *	@return bool */
	function userCan($user)
	{
		return !(isset($this->data['rights']) && $this->data['rights']) || \user_can($user, $this->data['rights']);
	}

	function currentUserCan()
	{
		return !(isset($this->data['rights']) && $this->data['rights']) || \current_user_can($this->data['rights']);
	}

	function getLink($tab='')
	{
		if( !isset($this->baseURL) )
		{
			// compute base URL
			$arg = array();
			if( isset($_SERVER['QUERY_STRING']) )
				parse_str($_SERVER['QUERY_STRING'], $arg);
			$arg['tab'] = true;
			$this->baseURL = \remove_query_arg(array_keys($arg));

			$arg = array('page' => $this->id);
			if( isset($_GET['post_type']) )
				$arg['post_type'] = \sanitize_key($_GET['post_type']);

			$this->baseURL = \add_query_arg($arg, $this->baseURL);
		}

		if( $tab )
			return \add_query_arg(array('tab' => $tab), $this->baseURL);
		else
			return $this->baseURL;
	}

	function getNextLevel(array $currentLevel, $slug)
	{
		$local = false;
		if( isset($currentLevel['tabs']) )
		{
			foreach( $currentLevel['tabs'] as $tab )
			{
				if( $tab['id'] == $slug )
					$local = $tab;
			}
		}
		return $local;
	}

	function getLevelTabCount(array $currentLevel, $slug, $withHidden=false)
	{
		$c = 0;
		if( isset($currentLevel['tabs']) )
		{
			foreach( $currentLevel['tabs'] as $tab )
			{
				if( $tab['id'] == $slug || $withHidden || !(isset($tab['hidden']) && \boolval($tab['hidden'])) )
					++$c;
			}
		}
		return $c;
	}

	/** remove tab/group user cannot see
	 *	and test data array is valid. */
	protected function filterGraph(&$level, $recurse=false, $rights=false)
	{
		if( isset($level['rights']) )
			$rights = $level['rights'];

		if( isset($level['groups']) && $level['groups'] )
		{
			$level['groups'] = array_filter($level['groups'], array($this, 'testGroupFormat'));

			if( $rights )
			{
				$level['groups'] = array_filter($level['groups'], function($grp)use($rights){
					if( $r = isset($grp['rights']) ? $grp['rights'] : $rights )
						return \current_user_can($r);
					else
						return true;
				});
			}
		}

		if( isset($level['tabs']) && $level['tabs'] )
		{
			$level['tabs'] = array_filter($level['tabs'], array($this, 'testTabFormat'));

			if( $rights )
			{
				$level['tabs'] = array_filter($level['tabs'], function($tab)use($rights){
					if( $r = isset($tab['rights']) ? $tab['rights'] : $rights )
						return \current_user_can($r);
					else
						return true;
				});
			}

			if( $recurse && $level['tabs'] )
			{
				foreach( $level['tabs'] as &$tab )
				{
					$this->filterGraph($tab, true, $rights);
				}
			}
		}
	}

	function testGroupFormat($group)
	{
		return \LWS\Adminpanel\Internal\Pages::test($group, \LWS\Adminpanel\Pages\Group::format(), $this->getId() . " ... groups");
	}

	function testTabFormat($tab)
	{
		return \LWS\Adminpanel\Internal\Pages::test($tab, \LWS\Adminpanel\Pages\Page::tabFormat(), $this->getId() . " ... tabs");
	}

	/** Group and tab ID are not required, but we need them anyway.
	 *	Set arbitrary id where it is missing. */
	protected function forceIds(array &$level, $recurse=false)
	{
		if( !isset($this->forcedId) )
			$this->forcedId = array('g'=>0, 't'=>0);

		if( isset($level['groups']) && $level['groups'] )
		{
			foreach( $level['groups'] as &$group )
			{
				if( !(isset($group['id']) && $group['id']) )
					$group['id'] = sprintf("--%s-group-%d", $this->getId(), $this->forcedId['g']++);
			}
		}

		if( isset($level['tabs']) && $level['tabs'] )
		{
			foreach( $level['tabs'] as &$tab )
			{
				if( !(isset($tab['id']) && $tab['id']) )
					$tab['id'] = sprintf("--%s-tab-%d", $this->getId(), $this->forcedId['t']++);
				if( $recurse )
					$this->forceIds($tab, true);
			}
		}
	}

	/** Now data graph is filtered and completed,
	 *	merge overridable piece of data. */
	protected function mergeGraph($level, $path)
	{
		foreach( $path as $slug )
		{
			if( $level = $this->getNextLevel($level, $slug) )
			{
				if( isset($level['toc']) )             $this->data['toc']     = $level['toc'];
				if( isset($level['nosave']) )          $this->data['nosave']  = $level['nosave'];
				if( isset($level['action']) )          $this->action          = $level['action'];
				if( isset($level['function']) )        $this->custom['top'][] = $level['function'];
				if( isset($level['delayedFunction']) ) $this->custom['bot'][] = $level['delayedFunction'];
				if( isset($level['vertnav']) )         $this->vertnav         = $level['vertnav'];
			}
			else
				break;
		}
	}

	/** @return the path going through all tab levels as an array of tab id from top level to leaf.
	 * If no tab path exists, return the first one.
	 * Cumulate the toc, nosave and action settings */
	function getPath()
	{
		if( !isset($this->path) )
		{
			$this->path = array();

			// follow queried path
			$path = (isset($_REQUEST['tab']) && $_REQUEST['tab']) ? explode('.', \sanitize_text_field($_REQUEST['tab'])) : array();
			$level = $this->getData();
			foreach( $path as $slug )
			{
				$level = $this->getNextLevel($level, $slug);
				if( $level )
					$this->path[] = $slug;
				else
					break;
			}

			// continue as deep as possible (first tab is default)
			while( $level )
			{
				if( !(isset($level['tabs']) && $level['tabs']) ) break;
				if( $level = reset($level['tabs']) )
				{
					if( isset($level['id']) && $level['id'] )
						$this->path[] = $level['id'];
					else
						break;
				}
			}
		}
		return $this->path;
	}

	/** @return a formated path string to represent a tab path.
	 * @param $path (array sorted form root tab to leaf) if false, use the current tab path. */
	function getPathAsString($path=false)
	{
		return implode('.', false === $path ? $this->getPath() : $path);
	}
}
