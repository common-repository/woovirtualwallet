<?php
namespace LWS\Adminpanel\Tools;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Convenience class.
 *	Helper to build MySQL request
 */
class Request
{
	protected $sql = array();
	protected $op = 'AND';
	protected $args = array();

	function __construct($table='', $as='', $whereOperator='AND')
	{
		$this->sql = array(
			'select' => array(),
			'from'   => "FROM `{$table}` as `{$as}`",
			'join'   => array(),
			'where'  => array(),
			'groupby'=> array(),
			'order'  => array(),
			'limit'  => '',
		);
		$this->op = \strtoupper(\trim($whereOperator));
		if( !\in_array($this->op, array('AND', 'OR')) )
			$this->op = 'AND';
		$this->args = array();
	}

	static function from($table='', $as='', $whereOperator='AND')
	{
		$me = new self($table, $as, $whereOperator);
		return $me;
	}

	function &arg($value, $add=true)
	{
		if( is_array($value) )
			$this->args = array_merge($this->args, $value);
		else if( $add === true )
			$this->args[] = $value;
		else if( $add === false )
			$this->args = array($value);
		else
			$this->args[$add] = $value;
		return $this;
	}

	function &removeArg($key=true)
	{
		if( $key === true )
			$this->args = array();
		else if( is_array($key) )
			$this->args = array_diff_key($this->args, $value);
		else if( isset($this->args[$key]) )
			unset($this->args[$key]);
		return $this;
	}

	function &select($field, $add=true)
	{
		return $this->fill('select', $field, $add);
	}

	function &where($cond, $add=true)
	{
		return $this->fill('where', $this->tryFlatCondition($cond), $add);
	}

	function &joinClause($join, $add=true)
	{
		return $this->fill('join', $join, $add);
	}

	function &leftJoin($table, $as='', $on=array(), $add=true)
	{
		return $this->join($table, $as, $on, 'LEFT', $add);
	}

	function &rightJoin($table, $as='', $on=array(), $add=true)
	{
		return $this->join($table, $as, $on, 'RIGHT', $add);
	}

	function &innerJoin($table, $as='', $on=array(), $add=true)
	{
		return $this->join($table, $as, $on, 'INNER', $add);
	}

	function &join($table, $as='', $on=array(), $direction='', $add=true)
	{
		$term = trim($direction);
		if( $term ) $term .= ' ';
		$term .= "JOIN `{$table}`";
		if( $as = trim($as) )
			$term .= " AS {$as}";

		$on = $this->tryFlatCondition($on);
		$term .= " ON {$on}";

		return $this->fill('join', $term, $add);
	}

	function &order($field, $ascending=true, $add=true)
	{
		if( $ascending == true )
			$field .= " ASC";
		else if( $ascending == false )
			$field .= " DESC";
		else if( $ascending )
			$field .= " {$ascending}";
		return $this->fill('order', $field, $add);
	}

	function &group($field, $add=true)
	{
		return $this->fill('groupby', $field, $add);
	}

	function &limit($offset, $count=false)
	{
		$offset = \intval($offset);
		$this->sql['limit'] = "LIMIT {$offset}";
		if( $count !== false )
		{
			$count = \intval($count);
			$this->sql['limit'] .= ", {$count}";
		}
		return $this;
	}

	function &rowLimit($instance)
	{
		if( $instance && \is_a($instance, '\LWS\Adminpanel\EditList\RowLimit') && $instance->valid() )
			$this->limit($instance->offset, $instance->count);
		return $this;
	}

	function &noLimit()
	{
		$this->sql['limit'] = '';
		return $this;
	}

	function toString()
	{
		$tmp = $this->sql;
		$tmp['select'] = "SELECT " . implode(', ', array_filter($tmp['select']));
		$tmp['join'] = implode("\n", array_filter($tmp['join']));

		if( $tmp['where'] = array_filter($tmp['where']) )
		{
			$tmp['where'] = "WHERE " . implode(" {$this->op} ", array_filter($tmp['where'], '\strlen'));
		}
		if( $tmp['groupby'] = array_filter($tmp['groupby']) )
		{
			$tmp['groupby'] = "GROUP BY " . implode(', ', $tmp['groupby']);
		}
		if( $tmp['order'] = array_filter($tmp['order']) )
		{
			$tmp['order'] = "ORDER BY " . implode(', ', $tmp['order']);
		}

		$str = implode("\n", array_filter($tmp));
		if( $this->args )
		{
			global $wpdb;
			$str = $wpdb->prepare($str, $this->args);
		}
		return $str;
	}

	function getVar($columnOffset=0, $rowOffset=0)
	{
		global $wpdb;
		return $wpdb->get_var($this->toString(), $columnOffset, $rowOffset);
	}

	function getRow($outputType=OBJECT, $rowOffset=0)
	{
		global $wpdb;
		return $wpdb->get_row($this->toString(), $outputType, $rowOffset);
	}

	function getCol($columnOffset=0)
	{
		global $wpdb;
		return $wpdb->get_col($this->toString(), $columnOffset);
	}

	function getResults($outputType=OBJECT)
	{
		global $wpdb;
		return $wpdb->get_results($this->toString(), $outputType);
	}

	protected function &fill($part, $term, $add)
	{
		if( is_array($term) && is_array($this->sql[$part]) )
			$this->sql[$part] = array_merge($this->sql[$part], $term);
		else if( $add === true )
			$this->sql[$part][] = $term;
		else if( $add === false )
			$this->sql[$part] = array($term);
		else
			$this->sql[$part][$add] = $term;
		return $this;
	}

	protected function tryFlatCondition($conds)
	{
		if( \is_array($conds) )
			return $this->flatConditions($conds);
		else
			return $conds;
	}

	protected function flatConditions($conds, $deep=0)
	{
		$word = $this->op;
		if( isset($conds['condition']) )
		{
			if( \in_array($w = \strtoupper(\trim($conds['condition'])), array('AND', 'OR')) )
				$word = $w;
			unset($conds['condition']);
		}

		if( $conds )
		{
			foreach( $conds as &$cond )
			{
				if( \is_array($cond) )
					$cond = $this->flatConditions($cond, 1+$deep);
			}
			$conds = array_filter($conds, '\trim');
			$conds = array_filter($conds, '\strlen');

			if( $conds )
			{
				$conds = implode(" {$word} ", $conds);
				if( $deep != 0 || $this->op != $word )
					$conds = "({$conds})";
			}
		}
		return $conds;
	}
}
