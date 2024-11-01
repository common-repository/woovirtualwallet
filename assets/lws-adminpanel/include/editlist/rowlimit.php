<?php
namespace LWS\Adminpanel\EditList;
if( !defined( 'ABSPATH' ) ) exit();

class RowLimit
{
	/// the offset of the first row to return
	public $offset = 0;
	/// the number of row to return
	public $count = 10;
	/// @return a mysql sentence part to add to your sql query
	public function toMysql(){ return " LIMIT {$this->offset}, {$this->count}"; }
	public function valid(){ if($this->offset < 0){$this->count += $this->offset; $this->offset = 0;} return ($this->count>0); }
	public static function append($limit, $sql)
	{
		if(!is_null($limit) && is_a($limit, \get_class()) && $limit->valid() && is_string($sql))
			$sql .= $limit->toMysql();
		return $sql;
	}
}
