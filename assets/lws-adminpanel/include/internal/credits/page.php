<?php
namespace LWS\Adminpanel\Internal\Credits;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();


/** Add a custom page to a plugin admin. */
abstract class Page
{
	protected $file = '';
	protected $adminPageId = '';

	/**	In case new page uses its own
	 *	@param $page the admin page id.
	 *	@return (string) the licence admin page id. */
	abstract function suffixPage(string $page);

	/**	In case new page is inserted in an existant page.
	 *	@return (string) the id of the tab in admin page. */
	abstract function getTabId();

	/**	@return (array) a tab definition @see \LWS\Adminpanel\Pages\Page::tabFormat() */
	abstract protected function getTab();

	/**	@param $targetPage (bool|string|array) if false, create a dedicated page; else insert in specifed page[s].
	 *	@param $create (bool) create the page in admin */
	protected function __construct($file, $adminPageId, $targetPage=false, $create=false)
	{
		$this->file = $file;
		$this->adminPageId = $adminPageId;
		$this->tabDst = false;

		if( $create )
		{
			$this->setDestinationPage($targetPage);
			$method = $targetPage ? 'addTab' : 'addPage';
			\add_filter('lws_adminpanel_pages_'.$adminPageId, array($this, $method), PHP_INT_MAX);
		}
	}

	function getCurrentPage()
	{
		if( isset($_REQUEST['page']) && ($current = \sanitize_text_field($_REQUEST['page'])) )
			return $current;
		if( isset($_REQUEST['option_page']) && ($current = \sanitize_text_field($_REQUEST['option_page'])) )
			return $current;
		return false;
	}

	function getScreenUrl($url, $mainId, $pageId)
	{
		if( isset($this->pageQueryArgs) && $this->pageQueryArgs && isset($this->myPages) && $this->myPages )
		{
			if( isset($this->myPages[$pageId]) )
				$url = \add_query_arg($this->pageQueryArgs, \admin_url('admin.php'));
		}
		return $url;
	}

	function getMainScreenUrl()
	{
		if( isset($this->myPages) && $this->myPages )
		{
			$id = reset($this->myPages);
			$url = \add_query_arg('page', $id, \admin_url('admin.php'));
			return \apply_filters('lws_adm_license_main_plugin_page_url', $url, $this->getSlug());
		}
		return false;
	}

	function isNotPrebuild($page, $neitherResume=false)
	{
		if( isset($page['prebuild']) && $page['prebuild'] )
			return false;
		if( $neitherResume && isset($page['resume']) && $page['resume'] )
			return false;
		return true;
	}

	/** Add a license page at last page.
	 *	Reuse the same main title as the first not prebuild page. */
	function addPage($pages)
	{
		$this->pageQueryArgs = array(
			'page' => $this->suffixPage($this->adminPageId),
		);
		$page = array(
			'id'       => $this->pageQueryArgs['page'],
			'title'    => __("License", 'lws-adminpanel'),
			'rights'   => 'manage_options',
			'tabs'     => array()
		);

		foreach($pages as $p)
		{
			if( $p['title'] && $this->isNotPrebuild($p) )
			{
				$page['subtitle'] = $page['title'];
				$page['title'] = $p['title'];
				break;
			}
		}

		if( $current = $this->getCurrentPage() )
		{
			if( $this->pageQueryArgs['page'] == $current )
				$page['tabs']['lic'] = $this->getTab();
		}

		$pages[] = $page;
		$this->myPages = \array_column(\array_filter($pages, array($this, 'isNotPrebuild')), 'id', 'id');
		return $pages;
	}

	/** If is the current page, add a tab.
	 *	Could move the main group if actually no tab exists.
	 *	Never add a license tab in a prebuild page. */
	function addTab($pages)
	{
		if( $current = $this->getCurrentPage() )
		{
			$oneOfUs = false;
			foreach($pages as &$p)
			{
				if( isset($p['id']) && $current == $p['id'] )
				{
					$oneOfUs = true;
					if( $this->matchDestinationPage($p, true) )
					{
						if( isset($p['groups']) && !(isset($p['tabs']) && $p['tabs']) )
						{
							$p['tabs'][] = array(
								'id'     => 'general',
								'title'  => $p['title'],
								'groups' => $p['groups'],
							);
							unset($p['groups']);
						}

						$p['tabs'][$this->getTabId()] = $this->getTab();
						$this->pageQueryArgs = array('page' => $p['id'], 'tab' => $this->getTabId());
					}
					break;
				}
			}

			if( $oneOfUs && !(isset($this->pageQueryArgs) && $this->pageQueryArgs) )
			{
				// fill pageQueryArgs anyway with the first valid page
				foreach($pages as &$p)
				{
					if( $this->matchDestinationPage($p, true) )
					{
						$this->pageQueryArgs = array('page' => $p['id'], 'tab' => $this->getTabId());
						break;
					}
				}
			}
		}

		$this->myPages = \array_column(\array_filter($pages, array($this, 'isNotPrebuild')), 'id', 'id');
		return $pages;
	}

	/** Restrict licence tab to a specific page. */
	private function setDestinationPage($pageId)
	{
		if( $pageId )
		{
			$this->tabDst = true;
			if( \is_array($pageId) )
				$this->tabDst = $pageId;
			elseif( \is_string($pageId) )
				$this->tabDst = array($pageId);
		}
		else
			$this->tabDst = false;
	}

	/** If we restrict licence tab to a specific page. */
	private function matchDestinationPage($level, $neitherResume=false)
	{
		if( true === $this->tabDst )
			return $this->isNotPrebuild($level, $neitherResume);
		elseif( false === $this->tabDst )
			return false;
		else
			return \in_array($level['id'], $this->tabDst);
	}

	function getSlug()
	{
		if( !isset($this->slug) )
		{
			$this->slug = \strtolower(\basename(\plugin_basename($this->file), '.php'));
		}
		return $this->slug;
	}

	function getPluginInfo($field=false)
	{
		if( !isset($this->plugin) )
		{
			if( !\function_exists('\get_plugin_data') )
				require_once(ABSPATH . 'wp-admin/includes/plugin.php');

			$this->plugin = \get_plugin_data($this->file, false);
			$this->plugin = array_merge(array(
				'Name'      => '',
				'Version'   => '',
				'Author'    => '',
				'AuthorURI' => '',
				'PluginURI' => '',
			), $this->plugin);
		}
		if( $field )
			return $this->plugin[\ucfirst($field)];
		else
			return $this->plugin;
	}
}
