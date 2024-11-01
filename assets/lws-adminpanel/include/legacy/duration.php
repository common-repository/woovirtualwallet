<?php
namespace LWS\Adminpanel;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Convenience class.
 *	Represents a duration in year, month or day (exclusive).
 *
 * 	Provide convertion and computing helpers
 *	for php DateTime, DateInterval or MySql sentances.
 */
class Duration
{
	protected $number = 0;
	protected $period = 'D';

	function isNull()
	{
		return $this->number <= 0;
	}

	function getDays()
	{
		return $this->period == 'D' ? $this->number : false;
	}

	function getMonths()
	{
		return $this->period == 'M' ? $this->number : false;
	}

	function getYears()
	{
		return $this->period == 'Y' ? $this->number : false;
	}

	function getPeriod()
	{
		return $this->period;
	}

	function getSqlInterval()
	{
		if( 'W' == $this->period )
			return sprintf('INTERVAL %d DAY', 7*$this->getCount());

		$text = 'INTERVAL ' . $this->getCount();
		switch($this->period)
		{
			case 'Y':
				$text .= ' YEAR';
				break;
			case 'M':
				$text .= ' MONTH';
				break;
			case 'H':
				$text .= ' HOUR';
				break;
			case 'I':
				$text .= ' MINUTE ';
				break;
			case 'S':
				$text .= ' SECOND';
				break;
			default:
				$text .= ' DAY';
				break;
		}
		return $text;
	}

	function getPeriodText($firstLetterUpper=false)
	{
		$text = '-';
		switch($this->period)
		{
			case 'Y':
				$text = _n("Year", "Years", $this->number, 'lws-adminpanel');
				break;
			case 'M':
				$text = _n("Month", "Months", $this->number, 'lws-adminpanel');
				break;
			case 'W':
				$text = _n("Week", "Weeks", $this->number, 'lws-adminpanel');
				break;
			case 'H':
				$text = _n("Hour", "Hours", $this->number, 'lws-adminpanel');
				break;
			case 'I':
				$text = _n("Minute", "Minutes", $this->number, 'lws-adminpanel');
				break;
			case 'S':
				$text = _n("Second", "Seconds", $this->number, 'lws-adminpanel');
				break;
			default:
				$text = _n("Day", "Days", $this->number, 'lws-adminpanel');
				break;
		}
		return $firstLetterUpper ? $text : strtolower($text);
	}

	function getCount()
	{
		return $this->number;
	}

	/** @return clone of given arg.
	 * @param $d if null, use now(). */
	function addDate(\DateTimeInterface $d=null)
	{
		$d = $d ? clone $d : \date_create();
		return $d->add($this->toInterval());
	}

	/** @return clone of given arg.
	 * @param $d if null, use now(). */
	function subDate(\DateTimeInterface $d=null)
	{
		$d = $d ? clone $d : \date_create();
		return $d->sub($this->toInterval());
	}

	/** Compute the date at end of duration.
	 * @param $from (false|DateTime) Starting date, default false means today.
	 * @return DateTime = $form + interval  */
	function getEndingDate($from=false)
	{
		if( false === $from )
			$from = \date_create();
		return $from->add($this->toInterval());
	}

	/** @see DateInterval */
	function toString()
	{
		if( $this->isNull() )
			return '';
		else
		{
			$prefix = 'P';
			$period = $this->period;
			if( !in_array($period, array('Y', 'M', 'D', 'W')) )
			{
				$prefix = 'PT';
				if( $period == 'I' )
					$period = 'M';
			}
			return $prefix.$this->number.$period;
		}
	}

	function toInterval()
	{
		return new \DateInterval($this->toString());
	}

	static function fromInterval($interval)
	{
		static $def = false;
		if( !$def )
		{
			$def = array_intersect_key(array(
				'Y' => '%y',
				'M' => '%m',
				'D' => '%d',
				'H' => '%h',
				'I' => '%i',
				'S' => '%s',
			), self::getSupportedPeriods(true));
		}
		foreach( $def as $out => $in )
		{
			$v = abs($interval->format($in));
			if( $v )
				return new self($v, $out);
		}
		$interval = \date_create()->diff(\date_create()->add($interval), true);
		return new self($interval->format('%a'), 'D');
	}

	/** @param $interval first int is assumed as delay and first [YMD] as unit. if unit is omitted, day is assumed.
	 * A starting 'P' is ignored. */
	static function fromString($interval)
	{
		if( empty($interval) )
			return self::void();
		static $pattern = false;
		if( !$pattern )
			$pattern = '/P?(T?)(\d+)([' . implode('', \array_keys(self::getSupportedPeriods(true))) . '])/i';
		$match = array();
		if( preg_match($pattern, $interval, $match) )
		{
			if( $match[1] === 'T' )
				$match[3] = str_replace('M', 'I', $match[3]);
			return new self($match[2], $match[3]);
		}
		else
			return new self(intval($interval), 'D');
	}

	static function void()
	{
		return new self(0, 'D');
	}

	static function days($count)
	{
		return new self($count, 'D');
	}

	static function months($count)
	{
		return new self($count, 'M');
	}

	static function years($count)
	{
		return new self($count, 'Y');
	}

	static function userMeta($userId, $key)
	{
		return self::fromString(\get_user_meta($userId, $key, true));
	}

	static function postMeta($postId, $key)
	{
		return self::fromString(\get_post_meta($postId, $key, true));
	}

	static function option($key)
	{
		return self::fromString(\get_option($key, 0));
	}

	function deleteUserMeta($userId, $key)
	{
		\delete_user_meta($userId, $key);
	}

	function deletePostMeta($postId, $key)
	{
		\delete_post_meta($postId, $key);
	}

	function deleteOption($key)
	{
		\delete_option($key);
	}

	function updateUserMeta($userId, $key)
	{
		\update_user_meta($userId, $key, $this->toString());
	}

	function updatePostMeta($postId, $key)
	{
		\update_post_meta($postId, $key, $this->toString());
	}

	function updateOption($key)
	{
		\update_option($key, $this->toString(), false);
	}

	/** multiply the count, period stay the same. */
	function mul($qty)
	{
		$this->number *= $qty;
	}

	protected function __construct($n=0, $p='D')
	{
		$this->number = abs(intval($n));
		$this->period = in_array($p, array_keys(self::getSupportedPeriods(true))) ? $p : 'D';
	}

	static function getSupportedPeriods($extended=false)
	{
		static $periods = false;
		static $allPeriods = false;
		if( false === $periods )
		{
			$periods = array(
				'D' => __("Days", 'lws-adminpanel'),
				'M' => __("Months", 'lws-adminpanel'),
				'Y' => __("Years", 'lws-adminpanel'),
			);
			$allPeriods =  array_merge($periods, array(
				'W' => __("Weeks", 'lws-adminpanel'),
				'H' => __("Hours", 'lws-adminpanel'),
				'I' => __("Minutes", 'lws-adminpanel'),
				'S' => __("Seconds", 'lws-adminpanel'),
			));
		}
		return \apply_filters('lws_adminpanel_duration_supported_periods', $extended ? $allPeriods : $periods);
	}
}
