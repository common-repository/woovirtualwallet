<?php
namespace LWS\Adminpanel\EditList;
if( !defined( 'ABSPATH' ) ) exit();


class Pager
{
	const KEY_SUFFIX_PAGE = '-limit-page';
	const KEY_SUFFIX_COUNT = '-limit-count';
	const PP = 5;
	protected $guid;
	protected $keyPage;
	protected $keyCount;

	/** @return a RowLinit instance for EditListSource::read function */
	public function readLimit($max=false)
	{
		$limit = new RowLimit();
		if( isset($_REQUEST[$this->keyPage]) && isset($_REQUEST[$this->keyCount]) )
		{
			$p = \sanitize_text_field($_REQUEST[$this->keyPage]);
			$c = \sanitize_text_field($_REQUEST[$this->keyCount]);
			if( !empty($p) && is_numeric($p) && !empty($c) && is_numeric($c) )
			{
				$limit->offset = (($p-1) * $c);
				$limit->count = $c;

				if( $limit->offset < 0 )
					$limit->offset = 0;
				if( $limit->count < self::PP )
					$limit->count = self::PP;
				if( $max !== false && is_numeric($max) && ($max >= 0) && ($limit->offset >= $max) )
					$limit->offset = 0;
			}
		}
		return $limit;
	}

	/** @param the editlist unique ID */
	public function __construct($guid)
	{
		$this->guid = $guid;
		$this->keyPage = $guid . self::KEY_SUFFIX_PAGE;
		$this->keyCount = $guid . self::KEY_SUFFIX_COUNT;
		//error_log(print_r($this,true));
	}

	/** @return a string with html for page navigation snippet */
	public function navDiv($rcount, $currentLimit = null)
	{
		if( is_null($currentLimit) )
			$currentLimit = $this->readLimit();
		$last = false;
		if( $rcount >= 0 )
			$last = $this->page(max(0,$rcount-1), $currentLimit->count);
		$index = $this->page($currentLimit->offset, $currentLimit->count);

		$str = "<div class='lws-tablenav'>";
		$str .= "<div class='lws-tablenav-ipp'>";
		$str .= $this->snippetPerPage($currentLimit->count);
		$str .= "</div>";
		$str .= "<div class='lws-tablenav-pages'>";

		if( $last !== false ) // total
			$str .= $this->snippetTotal($rcount);

		$str .= "<span class='lws-pagination-links'>";
		$str .= $this->navBtn("lws-icon-first-page", 1, $last, $index);
		$str .= $this->navBtn("lws-icon-previous-page", $index - 1, $last, $index);
		$str .= $this->snippetCurrentPage($index, $last, $index);
		$str .= $this->navBtn("lws-icon-next-page", $index + 1, $last, $index);
		$str .= $this->navBtn("lws-icon-last-page", $last, $last, $index);
		$str .= "</span>"; // lws-pagination-links

		$str .= "</div></div>";
		return $str;
	}

	/// return html snippet for total of element
	protected function snippetTotal($rcount)
	{
		$strCount = sprintf( _n("%d item", "%d items", $rcount, 'lws-adminpanel'), $rcount );
		return "<div class='lws-displaying-num'>$strCount</div>";
	}

	/// return html snippet for number of element per page input
	protected function snippetPerPage($perpage)
	{
		$ph = __("Items per page", 'lws-adminpanel');
		$pp = self::PP;
		$countPages = array(10,20,40,80);
		$str = "<div class='lws-perpage-input'>";
		$str .= "<div class='lws-label-ipp'>$ph</div>";
		foreach($countPages as $value){
			$class = ($perpage==$value) ? 'lws-input-ipp lws-input-ipp-sel' : 'lws-input-ipp';
			$str .= "<div class='{$class}' data-name='{$this->keyCount}' data-count='{$value}'>{$value}</div>";
		}
		$str .= "<input type='hidden' value='$perpage' name='{$this->keyCount}'></div>";
		return $str;
	}

	/// return html snippet for number of element per page input
	protected function snippetCurrentPage($index, $last)
	{
		$ph = "";
		$max = "";
		if( $last !== false )
		{
			$ph = sprintf(_nx("/ %d", "/ %d", $last, "Total page number", 'lws-adminpanel'), $last);
			$max = " max='$last'";
		}
		$str = "<label class='lws-paging-input'><input type='text' value='$index' name='{$this->keyPage}' class='lws-input lws-input-enter-submit lws-ignore-confirm' min='1'$max> <span>$ph</span></label>";
    return $str;
	}

	/** return html for button page up, down and so on
	 * @param $txt text of the button
	 * @param $page destination page
	 * @param $last index of last page or false if unknown
	 * @param $index the current page */
	protected function navBtn($txt, $page, $last, $index)
	{
		$dest = "";
		$ok = "-ko";
		if( $page !== false && is_numeric($page)
		 && $page >= 1 && $page != $index
		 && ($last === false || $page <= $last) )
		{
			$ok = "-ok";
			$dest = " data-page='$page'";
		}

		$str = "<div class='lws-paging-navspan lws-paging-navspan$ok lws-icon $txt'$dest data-name='{$this->keyPage}'></div>";
		return $str;
	}

	/** @return the index of the page (start count at 1) or false if $offset is unknown.
	 * @param $offset of the record in full list.
	 * @param $perpage number of record to display per page. */
	protected function page($offset, $perpage)
	{
		if( is_numeric($offset) && ($offset >= 0) )
		{
			$p = floor($offset / max(self::PP,$perpage));
			return (intval($p) + 1);
		}
		else
			return false;
	}

}
