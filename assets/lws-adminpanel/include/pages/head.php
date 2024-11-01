<?php

namespace LWS\Adminpanel\Pages;

if (!defined('ABSPATH')) exit();


/**  */
class Head
{
	const YOUTUBE = 'https://www.youtube.com/channel/UCM3iPTIcjnJzfEYxMo5hLvg';
	const CHAT    = 'https://discord.gg/rWudNxr';
	const MAILTO  = 'support@longwatchstudio.com';

	function __construct(\LWS\Adminpanel\Pages\Page &$current, $resume = false)
	{
		$this->id = $current->getId();
		$this->data = $current->getData();
		$this->page = &$current;
		$this->resumePage = $resume;
	}

	function getMainId()
	{
		return $this->resumePage ? $this->resumePage->getID() : $this->page->getId();
	}

	/** @return array object, each entry contains:
	 *  * id (string) the slug
	 *  * title (string) human readable title
	 *  * url (string) link to that screen
	 * A level is ignored if title is exactly the same than the level up on it.
	 * A Resume page always stop at first level. */
	function getBreadcrumbs($prefixWithResumePage = true)
	{
		$breadcrumbs = array();

		$currentIsResume = ($this->resumePage && ($this->resumePage->getId() == $this->page->getId()));
		if( $prefixWithResumePage && !$currentIsResume && $this->resumePage )
		{
			$breadcrumbs[] = $this->formatLevelEntry($this->resumePage);
		}

		$format = $this->formatLevelEntry($this->page);
		$lastTitle = $format->title;
		$breadcrumbs[] = $format;

		if( $currentIsResume )
		{
			return $breadcrumbs;
		}

		// dig inside path
		$level = $this->page->getData();
		$path = '';
		foreach ($this->page->getPath() as $slug)
		{
			if ($local = $this->page->getNextLevel($level, $slug))
			{
				$path .= $path ? ('.' . $slug) : $slug;
				$format = $this->formatLevelEntry($local, $path);
				if( $lastTitle != $format->title )
				{
					$breadcrumbs[] = $format;
				}
				$lastTitle = $format->title;
				$level = $local;
			} else
				break;
		}

		return $breadcrumbs;
	}

	/** a graph with each node:
	 * * id
	 * * title
	 * * url
	 * * active (bool) selected menu entry
	 * * depth (int) root is zero
	 * * visible (bool)
	 * * children (array of node) */
	function getMenuGraph($cursor = false)
	{
		if (false === $cursor)
			$cursor = (object) array('level' => $this->data, 'route' => array(), 'path' => $this->page->getPath());

		$graph = array();
		if (isset($cursor->level['tabs'])) {
			$tail = $cursor->path;
			$slug = $tail ? array_shift($tail) : false;
			foreach ($cursor->level['tabs'] as $tab) {
				$depth = count($cursor->route);
				$route = array_merge($cursor->route, array($tab['id']));

				$obj = $this->formatLevelEntry($tab, $this->page->getPathAsString($route));
				$obj->depth    = $depth;
				$obj->icon     = isset($tab['icon']) ? $tab['icon'] : '';
				$obj->active   = ($tab['id'] == $slug);
				$obj->visible  = !(isset($tab['hidden']) && boolval($tab['hidden']));
				$obj->children = $this->getMenuGraph((object) array(
					'level' => $tab,
					'route' => $route,
					'path'  => $obj->active ? $tail : array(),
				));
				$graph[] = $obj;
			}
		}
		return $graph;
	}

	/** @param $level (array|Page) tab array or Page instance
	 *	@return (object) with [id, titel, url] */
	protected function formatLevelEntry(&$level, $tabPath = '')
	{
		if (\is_a($level, '\LWS\Adminpanel\Pages\Page')) {
			return (object) array(
				'id'    => $level->getId(),
				'title' => $level->getSubtitle(),
				'url'   => $level->getLink($tabPath),
			);
		} else {
			return (object) array(
				'id'    => $level['id'],
				'title' => $level['title'],
				'url'   => $this->page->getLink($tabPath),
			);
		}
	}

	/** Echo transient notices if any */
	function showTransientNotices()
	{
		$notices = \LWS\Adminpanel\Pages\Notices::instance()->getNotices('transient');
		$html = '';
		foreach($notices as $notice)
		{
			// ... take care admin-interface.js uses classes below
			// * the full bloc: lws-adminpanel-transient-notices
			// * the close button: lws-notice-dismiss
			// * the notice row: lws-adminpanel-notice
			$html .= \LWS\Adminpanel\Pages\Notices::instance()->itemToHTML($notice);
		}

		if( $html )
		{
			echo "<div class='lws-adminpanel-transient-notices'>{$html}</div>";
		}
	}

	/** Echo the sticky panel header */
	function showStickyPanel($type)
	{
		/** Top Line */
		echo "<div class='lws-sticky-panel'><div class='top-row'>";

		/**  BreadCrumbs */
		$breadcrumbs = $this->getBreadcrumbs();
		echo "<ul class='breadcrumb'>";
		foreach ($breadcrumbs as $crumb)
		{
			if( $crumb->title )
			{
				// duplicates url in data since href could be modified by JS scripts @see editlistfilters
				echo "<li><a href='{$crumb->url}' data-id='{$crumb->id}' data-href='{$crumb->url}'>{$crumb->title}</a></li>";
			}
		}
		echo "</ul>";

		/** Admin Menu */
		$this->showAdminMenu();
		echo "</div>";

		if ($type == 'admin') {
			/** Second Line */
			$secondLine = false;
			$submit = '';
			$expand = '';
			$shrinked = '';
			$hasTabs = ($this->getTabCount($this->page->getData()) > 1);
			if ($hasTabs) $shrinked = ' shrinked';
			$tabs = '';
			if ($this->page->allowSubmit()) {
				$submit = $this->getSubmitButtons($shrinked);
				$secondLine = true;
			}
			if ($this->page->getGroups() && count($this->page->getGroups()) > 1) {
				$expand = $this->getExpandButton($shrinked);
				$secondLine = true;
			}
			if ($hasTabs) {
				$tabs = "<div class='tab-menu'>";
				$tabs .= "<div class='small-screen-tabs lws-icon-menu'><div class='vertical-wrapper'></div></div>";
				$tabs .= $this->getTabs();
				$tabs .= "</div>";
				$secondLine = true;
			} else if ($secondLine) {
				$tabs = "<div class='tab-menu'></div>";
			}
			if ($secondLine) {
				echo "<div class='second-row'>";
				echo $tabs . $expand . $submit;
				echo "</div>";
			}
		}
		echo "</div>";
	}

	/** Echo the Header Admin Menu */
	function showAdminMenu()
	{
		$labels = \apply_filters('lws_adminpanel_topbar_labels_' . $this->id, array(
			'amenu'		=> __("Admin Menu", 'lws-adminpanel'),
			'asettings'	=> __("Advanced Settings", 'lws-adminpanel'),
			'support'	=> __("Support", 'lws-adminpanel'),
			'tshooting'	=> __("Troubleshooting", 'lws-adminpanel'),
			'chat'		=> __("Live Chat", 'lws-adminpanel'),
			'doc'		=> __("Documentation", 'lws-adminpanel'),
			'patch'		=> __("Patch Notes", 'lws-adminpanel'),
			'license'	=> __("License Information", 'lws-adminpanel'),
			'trialtext'	=> __("Try Premium for Free", 'lws-adminpanel'),
		));

		$settings = $this->getAdminMenuSettings();
		list($activecolor, $subcolor) = $this->getStatusBarsColors($settings);
		$licUrl = \apply_filters('lws_adm_menu_license_url', false, $this->getMainId(), $this->id);

		// Show Start Trial Button
		if( $licUrl && isset($settings['trial_available']) && $settings['trial_available'] )
			echo "<a href='{$licUrl}' class='start-trial-button'>{$labels['trialtext']}</a>";

		echo "<div class='admin-menu'>";
		echo <<<EOT
	<div id='lws_am_top_item' class='top-item'>
		<div class='item-icon lws-icon-menu'>
			<div class='notif-counter' style='display:none;' id='lws_am_top_notif_counter'></div>
		</div>
		<div class='item-name'>
			<div class='item-text'>{$labels['amenu']}</div>
			<div class='item-info'>
				<div class='info-icon $activecolor'></div><div class='info-icon $subcolor'></div>
			</div>
		</div>
	</div>
EOT;
		echo "<div id='lws_am_top_menu' class='top-menu'>";

		/** Plugin Name and version */
		echo <<<EOT
	<div class='top-menu-item separator no-pointer'>
		<div class='top-menu-item-icon lws-icon-version'></div>
		<div class='top-menu-item-text upper'>{$settings['title']} {$settings['version']}</div>
	</div>
EOT;

		/** Notifications */
		echo $this->getNoticeMenuItem(
			\LWS\Adminpanel\Pages\Notices::instance()->getNotices('persistant'),
			__("Plugin Notifications", 'lws-adminpanel'),
			'',
			'internal'
		);
		echo $this->getNoticeMenuItem(
			\LWS\Adminpanel\Pages\Page::getAdminNotices(),
			__("Other Notifications", 'lws-adminpanel'),
			'separator',
			'external'
		);

		/** Advanced Settings */
		//echo "<div id='lws_advanced_settings' class='top-menu-item separator'><div class='top-menu-item-icon lws-icon-adv-settings'></div>";
		//echo "<div class='top-menu-item-text'>{$labels['asettings']}</div></div>";

		/** Support */
		echo sprintf("<a href='%s' class='top-menu-item'>", \esc_attr($this->getSupportUrl($settings['mailto'])));
		echo "<div class='top-menu-item-icon lws-icon-support'></div>";
		echo "<div class='top-menu-item-text'>{$labels['support']}</div></a>";

		/** Troubleshooting */
		/*
		echo "<a href='{$settings['tshooting']}' class='top-menu-item'>";
		echo "<div class='top-menu-item-icon lws-icon-debug'></div>";
		echo "<div class='top-menu-item-text'>{$labels['tshooting']}</div></a>";
		*/

		/** Live Chat */
		echo "<a href='{$settings['chat']}' target='_blank' class='top-menu-item'>";
		echo "<div class='top-menu-item-icon lws-icon-discord'></div>";
		echo "<div class='top-menu-item-text'>{$labels['chat']}</div></a>";

		/** Live Chat */
		echo "<a href='{$settings['doc']}' target='_blank' class='top-menu-item'>";
		echo "<div class='top-menu-item-icon lws-icon-books'></div>";
		echo "<div class='top-menu-item-text'>{$labels['doc']}</div></a>";

		/** Patch Notes  */
		echo "<div class='top-menu-item separator'>";
		$classeNotes = '';
		if (isset($patchNotes)) {
			$classeNotes = ' show_patchnotes';
			echo "<div class='top-menu-item-icon lws-icon-notes{$classeNotes}'><div class='notif-counter'></div></div>";
		} else {
			echo "<div class='top-menu-item-icon lws-icon-notes'></div>";
		}
		echo "<div class='top-menu-item-text{$classeNotes}'>{$labels['patch']}</div></div>";

		/** License information */
		if( $licUrl )
		{
			$licUrl = \esc_attr($licUrl);
			$license  = "<a href='{$licUrl}' class='top-menu-item'><div class='top-menu-item-icon lws-icon-key'></div>";
			$license .= "<div class='top-menu-item-text'>{$labels['license']}</div></a>";
			echo $license;
		}

		echo "</div>";
		echo "</div>";
	}

	protected function getNoticeMenuItem($notices, $label='Notices', $class='', $type='')
	{
		if( $class )
			$class = (' ' . ltrim($class));
		$wrapper = '';
		if( $notices )
		{
			if( \is_array($notices) )
			{
				foreach($notices as $notice)
				{
					$close = '';
					if($notice->dismissible || $notice->forgettable)
					{
						$key = \esc_attr($notice->key);
						$text = __("Dismiss", 'lws-adminpanel');
						$close = "<div class='dismiss-btn' data-forget='{$key}'>{$text}</div>";
					}
					$wrapper .= "<div class='lws-notice {$notice->level}'><div class='text'>{$notice->message}</div>{$close}</div>";
				}
			}
			else
			{
				$wrapper = $notices;
			}
			$wrapper = "<div class='lws-adm-notices-wrapper'>{$wrapper}</div>";

			$class .= ' show_notices';
			$wrapper .= "<div class='top-menu-item-icon lws-icon-notifs-on show_notices'><div class='notif-counter'></div></div>";
		}
		else
		{
			$wrapper = "<div class='top-menu-item-icon lws-icon-notifs-off'></div>";
		}

		return <<<EOT
<div class='top-menu-item' data-type='{$type}'>
	{$wrapper}
	<div class='top-menu-item-text check_notices_count_at_start{$class}'>{$label}</div>
</div>
EOT;
	}

	/** Submit and Cancel buttons */
	function getSubmitButtons($shrinked = '')
	{
		$buttons  = "<div id='discard_changes' class='second-row-button discard$shrinked'>";
		$buttons .= "<div class='button-icon lws-icon-undo'></div>";
		$buttons .= "<div class='button-text'>" . __('Discard Changes', 'lws-adminpanel') . "</div></div>";
		$buttons .= "<button id='save_changes' class='second-row-button save' type='submit' form='{$this->id}'>";
		$buttons .= "<div class='button-icon lws-icon-floppy-disk-2'></div>";
		$buttons .= "<div class='button-text'>" . __('Save Changes', 'lws-adminpanel') . "</div></button>";
		return $buttons;
	}

	/** Groups Expand Button */
	function getExpandButton($shrinked='')
	{
		$buttons  = "<div id='expand_groups' class='second-row-button expand$shrinked'>";
		$buttons .= "<div class='button-icon lws-icon-plus'></div>";
		$buttons .= "<div class='button-text'>".__('Expand All', 'lws-adminpanel')."</div></div>";
		return $buttons;
	}

	/** Tabs Menu */
	function getTabs()
	{
		$menu = $this->makeTabsMenu($this->getMenuGraph());
		return $menu;
	}

	/* Create multi level tabs menu depending on settings */
	private function makeTabsMenu($graph)
	{
		$menu = "";
		$cpt = 0;
		foreach ($graph as $tab) {
			// avoid hidden tab
			if( $tab->visible )
			{
				$count = count(array_filter(array_column($tab->children, 'visible')));
				$hasTab = $count > 1;
				$href = $hasTab ? '#' : $tab->url;

				$class = "tab-menu-item";
				if ($hasTab) $class .= " has-children";
				$classa = "item-level-{$tab->depth}";
				if ($tab->active) $classa .= " active";

				$arrow = '';
				if ($hasTab) {
					if ($tab->depth == 0)
						$arrow = "<div class='arrow-down'></div>";
					else
						// Define the Arrow on the third level if need be
						$title = "";
				}

				$menu .= "<div class='$class'>";
				$menu .= "<a id='{$tab->id}' class='$classa' href='{$href}'>";
				if(isset($tab->icon) && !empty($tab->icon))
				{
					$menu .= "<div class='menu-item-icon {$tab->icon}'></div>";
				}
				$mintext = \str_replace("& ","<br/>",$tab->title);
				$menu .= "<div class='menu-item-text'>{$tab->title}</div>";
				$menu .= $arrow;
				$menu .= "</a>";
				if ($hasTab) {
					$menu .= "<ul class='tab-submenu-grid grid-level-" . ($tab->depth + 1) . " data-depth='" . ($tab->depth + 1) . "'>";
					$menu .= $this->makemenu($tab->children);
					$menu .= "</ul>";
				}
				$menu .=	"</div>";
				$cpt++;
			}
		}
		return $menu;
	}

	protected function getAdminMenuSettings()
	{
		$id = $this->getMainId();
		$settings = array(
			'title'      => \get_admin_page_title(),
			'subtitle'   => isset($this->data['subtitle']) ? $this->data['subtitle'] : '',
			'url'        => __("https://plugins.longwatchstudio.com/", 'lws-adminpanel'),
			'version'    => \apply_filters('lws_adminpanel_plugin_version_'     . $id, '', $this->id),
			'origin'     => \apply_filters('lws_adminpanel_plugin_origin_'      . $id, array('LWS', 'Long Watch Studio'), $this->id),
			'doc'        => \apply_filters('lws_adminpanel_documentation_url_'  . $id, __("https://plugins.longwatchstudio.com/documentation/", 'lws-adminpanel'), $this->id),
			'chat'       => \apply_filters('lws_adminpanel_plugin_chat_url_'    . $id, self::CHAT, $this->id),
			'mailto'     => \apply_filters('lws_adminpanel_plugin_support_email'. $id, self::MAILTO, $this->id),
			'purchase'   => false,
			'lite'       => true,
			'trial'      => false,
			'active'     => false,
			'expired'    => false,
		);
		$settings = \apply_filters('lws_adm_menu_license_status', $settings, $id, $this->id);
		return $settings;
	}

	/** @return array with 2 CSS colors. */
	private function getStatusBarsColors($settings)
	{
		$left = $right = 'color';

		if( $settings['active'] ) $left     .= ' premium'; // green
		else if( $settings['trial'] ) $left .= ' trial'; // orange
		else $left                          .= ' lite'; // grey

		if( isset($settings['soon']) )
		{
			if( $settings['soon'] <= 3 )     $right .= ' sooner'; // bright red
			elseif( $settings['soon'] <= 5 ) $right .= ' soon'; // bright orange
			else                             $right .= ' belatedly'; // green
		}
		elseif( $settings['expired'] )
			$right .= ' expired'; // red
		elseif( $settings['lite'] )
			$right .= ' idle'; // grey
		elseif( $settings['subscription'] )
			$right .= ' subscription'; // orange
		else
			$right .= ' running'; // green

		return array($left, $right);
	}

	/** Get notices shown in the administration */
	/** echo page header */
	function echoHead()
	{
		if ($this->getTabCount($this->page->getData()) > 1) {
			$menu = $this->makemenu($this->getMenuGraph());

?>
			<div class='lws-tabs-zone'>
				<div class='lws-tabs-small-menu'>
					<div class='lws-tabs-sm-menubutton lws-icon lws-icon-menu-bars'></div>
				</div>
				<ul class='lws-mtab-ul-0' role='tablist' data-depth='0'>
					<?= $menu ?>
				</ul>
			</div>
<?php
		}

		$advTitle = __("Advanced Settings", 'lws-adminpanel');
		echo "<div class='lws-sub-description'>";
		echo "<div id='lws_toc_options'><div class='lws-toc-options-wrapper'><div class='lws-toc-options-icon lws-icon lws-icon-settings-gear'></div><div class='lws-toc-options-text'>$advTitle</div></div></div>";
		if (isset($this->data['subtext']))
			echo \is_array($this->data['subtext']) ? \lws_array_to_html($this->data['subtext']) : $this->data['subtext'];
		echo "</div>";
	}

	/** To show our topbar on any WordPress admin page.
	 * See WooRewards-pro, the badge list screen. */
	static function echoExternal($pageId, $settings)
	{
		$data = array_merge(array(
			'id' => $pageId,
			'rights' => ''
		), $settings);
		$page = \LWS\Adminpanel\Pages\Page::create($pageId, $data);
		$head = new self($page);
		$head->showStickyPanel($pageId, $settings);
		$head->showTransientNotices();
	}

	/** @return (int) the count of tab at given level
	 *	Hidden tab are not counted.
	 *	@param $data (array) the page data (or a tab array) */
	function getTabCount(array $data)
	{
		$c = 0;
		if (isset($data['tabs']) && $data['tabs']) {
			foreach ($data['tabs'] as $tab) {
				if( !(isset($tab['hidden']) && boolval($tab['hidden'])) )
					++$c;
			}
		}
		return $c;
	}

	private function makemenu($graph)
	{
		$menu = "";
		$cpt = 0;
		foreach ($graph as $tab) {
			// avoid hidden tab and licence tab (except if not at first level)
			if( $tab->visible )
			{
				$count = count(array_filter(array_column($tab->children, 'visible')));
				$hasTab = $count > 1;
				$href = $hasTab ? '#' : $tab->url;

				$class = "item-level-{$tab->depth}";
				if ($tab->depth == 0 && $cpt != 0) $class .= " lws-mtab-menu-sep";
				if ($hasTab)                     $class .= " lws_mtab_hassub";
				$classa = "ui-tabs-anchor lws-theme-over-fg";
				if ($tab->active)           $classa .= " lws-mtab-active";

				$title = $tab->title;
				if ($hasTab) {
					if ($tab->depth == 0)
						$title .= "<span class='menu-item-icon lws-icon lws-icon-circle-down'></span>";
					else
						$title = "<div class='menu-item-text lws-mtab-submenu-label'>$title</div><div class='lws-mtab-menu-arrow lws-icon lws-icon-circle-right'></div>";
				}

				$menu .=	"<li id='{$tab->id}' class='$class' role='tab'>";
				$menu .=	"<a  class='$classa' role='presentation' href='{$href}'>{$title}</a>";
				if ($hasTab) {
					$menu .=	"<ul class='lws-top-menu lws-mtab-ul-" . ($tab->depth + 1) . " lws-mtab-menu-hidden' role='tablist' data-depth='" . ($tab->depth + 1) . "'>";
					$menu .= $this->makemenu($tab->children);
					$menu .= "</ul>";
				}
				$menu .=	"</li>";
				$cpt++;
			}
		}
		return $menu;
	}

	function getSupportUrl($default=false)
	{
		$url = \apply_filters('lws_adm_menu_support_url', false, $this->getMainId(), $this->id);
		if( !$url )
		{
			$url = $default;
			if( !\preg_match('/^mailto:/i', $url) && \is_email($url) )
				$url = 'mailto:'.$url;
		}
		return $url;
	}
}
