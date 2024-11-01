<?php
namespace LWS\Adminpanel\Pages\Screens;
if( !defined( 'ABSPATH' ) ) exit();


/** Must be defined as first page of the array given to \lws_register_pages()
 *	Must declare an array index 'resume' => true */
class Resume extends \LWS\Adminpanel\Pages\Page
{
	/** Echo page content
	 *	Declaration of all pages can be found in $this->pages array
	 *	Page header in getHead() */
	public function content()
	{
		\wp_enqueue_style('lws-resume-page');
		echo "<div class='lws-admin-page'>";
		echo "<div class='lws-resume-grid'>";

		foreach($this->pages as $page)
		{
			if(isset($page->description) && $page->description)
			{
				if( \is_array($page->description) )
					$page->description = \lws_array_to_html($page->description);
				$link = \esc_attr(\admin_url('admin.php?page='.$page->id));
				$title = $page->getTitle();
				if(isset($page->color) && $page->color)
				{
					$mediumColor = $page->color."60";
					$lightColor = $page->color."20";
					$style = " style='--group-color:{$page->color};--group-medium-color:{$mediumColor};--group-light-color:{$lightColor}'";
				}
				echo "<a href='{$link}' class='resume-item'$style>";
				echo "<div class='resume-top'>";
				if(isset($page->image) && $page->image)
				{
					echo "<div class='resume-item-icon'><img src='{$page->image}'/></div>";
				}
				echo "<div class='resume-item-title'>{$title}</div>";
				echo "</div>";
				echo "<div class='resume-content'>{$page->description}</div>";
				echo "</a>";
			}
		}
		echo "</div></div>";
	}

	protected function prepare()
	{}

	public function isResume()
	{
		return true;
	}

	public function getType()
	{
		return 'resume';
	}

	/** @param $pages array of Page instances */
	public function setAllPagesData($pages)
	{
		$this->pages = $pages;
	}

	public function allowSubmit()
	{
		return false;
	}

	public function getGroups()
	{
		return false;
	}
}
