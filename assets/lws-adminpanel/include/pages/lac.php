<?php
namespace LWS\Adminpanel\Pages;
if( !defined( 'ABSPATH' ) ) exit();

/** Base for autocomplete like control.
 * Data can be preloaded or read from ajax call.
 * All extra are optionnal.
 * @param $extra['ajax'] the ajax action to grab data list.
 * @param $extra['source'] the preload data list as array of array('value'=>…, 'label'=>…, 'detail'=>…)
 * 	value is the recorded value, label is displayed (and search string) to user in input field,
 * 	detail key is optionnal if you want to display complexe html as menu item.
 * @param $extra['class'] css class list transfered to autocomplete wrapper (span).
 * @param $extra['name'] input name set to autocomplete wrapper input (in case label is relevant too).
 * @param $extra['minsearch'] the minimal search string length before ajax call instead of local options.
 * @param $extra['minoption'] if local filter result count is less or equal, ajax call (if any) is attempt.
 * @param $extra['delay'] hit key delay before search trigger (let user finish its term before loading).
 * @param $extra['minlength'] minimal input length before autocomplete starts (default 1).
 * @param $extra['placeholder'] is a input placeholder.
 * @param $extra['spec'] any value transfered as json base64 encoded to ajax.
 * @param $extra['value'] if is set, use this as input value, else try a get_option($id).
 * @param $extra['prebuild'] compute a source if source is omitted @see prebuild.
 * @param $extra['predefined'] precomputed values for extra @see predefined.
 *
 * @note soure is an array of object or array with value, label and optionnaly detail for complex html item in unfold list.
 * It is recommended to have at least the selected value described in source.
 * @note if user entry is not found in preload source and an ajax is set, ajax will be call to complete source. */
abstract class LAC extends \LWS\Adminpanel\Pages\Field
{
	abstract protected function html();

	public function __construct($id, $title, $extra=null)
	{
		parent::__construct($id, $title, $extra);
	}

	/** @return (string) html code of the control field standalone.
	 * arguments are as for fields in admin pages. */
	public static function compose($id, $extra=null)
	{
		$class = get_called_class();
		return (new $class($id, '', $extra))->html();
	}

	/** echo the control */
	public function input()
	{
		echo $this->html();
	}

	/** @param $remote also allow ajax and predefined data source */
	protected function isValid($remote=true, $readonly=true)
	{
		if( $remote )
			$this->predefined();

		if( !( isset($this->extra['source']) || ($remote && (isset($this->extra['ajax']) || isset($this->extra['predefined']))) ) )
		{
			if( $readonly )
			{
				error_log("[".get_class()."] No data list provided to " . $this->id());
				return false;
			}
			else if( !empty($value = $this->readOption(false)) )
			{
				// build a source from option_value
				if( is_array($value) )
				{
					$this->extra['source'] = array();
					foreach( $value as $v )
					{
						if( !empty($v) )
						{
							if( is_array($v) )
							{
								if( isset($v['value']) )
									$this->extra['source'][$v['value']] = array('value'=>$v['value'], 'label'=>(isset($v['label'])?$v['label']:$v['value']));
							}
							else
								$this->extra['source'][$v] = array('value'=>$v, 'label'=>$v);
						}
					}
				}
				else if( is_string($value) )
				{
					$this->extra['source'] = array($value => array('value'=>$value, 'label'=>$value));
				}
			}
		}
		return true;
	}

	/** @param $extra['predefined'] expect filter "lws_autocomplete_compose_{$extra['predefined']}",
	 * complete extra with known values for specific behavior, exists 'page' and 'user'. */
	protected function predefined()
	{
		if( isset($this->extra['predefined']) && $this->extra['predefined'] !== false && is_string($this->extra['predefined']) )
			$this->extra = array_merge(apply_filters('lws_autocomplete_compose_'.sanitize_key($this->extra['predefined']), array()), is_array($this->extra) ? $this->extra : array());
	}

	protected function data($key)
	{
		if( $this->hasExtra($key, 'a') )
		{
			$data = base64_encode(json_encode($this->extra[$key]));
			return "data-$key='$data'";
		}
		else
			return '';
	}

	protected function modelScript()
	{
		$dep = array('jquery', 'jquery-ui-core', 'jquery-ui-widget', 'lws-base64');
		foreach( $dep as $uid )
			wp_enqueue_script($uid);
		wp_enqueue_script('lws-lac-model');
		return array('lws-lac-model');
	}

	/** @return data-source=...
	 * If a value exists, return at least the value description.
	 * expect a extra['prebuild'] as an array with the keys:
	 * * value: sql select field for value
	 * * label: sql select field for label
	 * * from: sql from table
	 * * join: sql sentance to join another table
	 * * detail (optional): sql select field for label
	 * * max (optional): maximum number of item
	 * *orderby (optional): the sql field to sort (you can append DESC or ASC) */
	protected function prebuild($value, $spec)
	{
		$source = false;
		if( is_callable($this->extra['prebuild']) )
		{
			$source = call_user_func($this->extra['prebuild'], $value, $spec);
			if( !empty($source) )
				return "data-source='$source'";
		}
		else if( is_array($this->extra['prebuild']) )
		{
			$prebuild = $this->extra['prebuild'];
			if( isset($prebuild['value']) && isset($prebuild['label']) && isset($prebuild['from']) )
			{
				global $wpdb;
				$select = "SELECT {$prebuild['value']} as value, {$prebuild['label']} as label";
				if( isset($prebuild['detail']) ) $select .= ", {$prebuild['detail']} as detail";
				$from = "\nFROM {$prebuild['from']}";

				if( isset($prebuild['join']) )
				{
					if( is_array($prebuild['join']) )
						$from .= implode("\n", $prebuild['join']);
					else if( is_string($prebuild['join']) )
						$from .= "\n" . $prebuild['join'];
				}

				$clause = implode(' AND ', self::specToFilter($spec));
				$where = !empty($clause) ? "\nWHERE $clause" : '';

				$end = '';
				if( isset($prebuild['orderby']) )
					$end .= "\nORDER BY {$prebuild['orderby']}";
				$max = (isset($prebuild['max']) && is_numeric($prebuild['max']) && $prebuild['max']>=0) ? intval($prebuild['max']) : 20;
				$end .= "\nLIMIT 0, $max";

				$source = $wpdb->get_results("$select$from$where$end", OBJECT_K);

				if( !empty($value) )
				{
					$where .= empty($where) ? "\nWHERE " : "\nAND ";
					if( is_array($value) )
					{
						$list = array();
						foreach( $value as $v )
							$list[] = $wpdb->prepare("%s", $v);
						$where .= "{$prebuild['value']} IN (" . implode(',', $list) . ")";
					}
					else
					{
						$where .= $wpdb->prepare("{$prebuild['value']}=%s", $value);
					}
					$this->mergeArray($source, $wpdb->get_results("$select$from$where", OBJECT_K));
				}
			}
		}
		if( !empty($source) )
		{
			$source = base64_encode(json_encode($source));
			return "data-source='$source'";
		}
		else
			return '';
	}

	/** @brief array_merge but be sur to keep key.
	 *
	 * php array_merge is confused by ID (int) as key
	 * then assumed a not associative array,
	 * so values are append instead of merged. */
	protected function mergeArray(&$source, $other)
	{
		foreach( $other as $k => $v )
			$source[$k] = $v;
	}

	/** @param $fields allowed sql fields @return (array) sql filters
	 * @param $spec (array, json base64 encoded /optional) to filter posts, $field => data,
	 * where data can be a string value for = comparison or an array(value, operator). */
	public static function specToFilter($spec, $fields=array())
	{
		$where = array();
		if( is_array($spec) )
		{
			global $wpdb;
			$operators = array('=', '<>', '>', '<', '<=', '>=', 'IS', 'IS NOT', 'IN', 'NOT IN', 'LIKE', 'RLIKE', 'NOT LIKE', 'NOT RLIKE');

			foreach($spec as $key=>$filter)
			{
				if( empty($fields) || in_array($key, $fields, true) )
				{
					if( is_string($filter) )
					{
						$where[] = $wpdb->prepare("`$key`=%s", $filter);
					}
					else if( is_array($filter) && !empty($filter) )
					{
						$op = count($filter) > 1 && in_array($filter[1], $operators, true) ? $filter[1] : '=';
						if( is_array($filter[0]) )
						{
							if( !empty($filter[0]) && ($op == 'IN' || $op == 'NOT IN') )
							{
								for( $i=0 ; $i<count($filter[0]) ; ++$i )
									$filter[0][$i] = $wpdb->prepare("%s", $filter[0][$i]);
								$where[] = "`$key` $op (" . implode(',', $filter[0]) . ")";
							}
						}
						else if( is_string($filter[0]) )
							$where[] = $wpdb->prepare("`$key` $op %s", $filter[0]);
					}
					else
						error_log(__FUNCTION__." part of filter ignored [$key] due to data part.");
				}
				else
					error_log(__FUNCTION__." part of filter ignored [$key] not tolerated.");
			}
		}
		return $where;
	}
}

?>
