<?php
namespace LWS\Adminpanel\Tools;

/** Parse an argument array.
 * expect at least an array with key
 * * values (array of $key => $value)
 * optional keys are:
 * * format array of $key => format string (@see ArgParser.::format)
 * * required array of $key => bool (all not listed key here are assumed to be optional)
 * * defaults array of $key => $value (all empty value in values (after format filtering) are supplied).
 * * labels array of $key => string use this string instead of key to report an error.
 * * shallow bool, true means given format is not complete, value could have undescribe content. Default is false.
 * * post bool means values come from $_POST, then a stripslashes() is applied on each values. Default is false.
 *
 * If formats required it, all values are filtered by trim, sanitize_text_field, sanitize_key, intval...
 * the returned array add the following entries:
 * * valid (bool) indicates if args follfill requirements
 * * error (string) if valid is false, an error description is set.
 *
 * For each format, required or defaults, if not an array, assume the same for each value. */
class ArgParser
{

	static public function install()
	{
		$me = new self();
		\add_filter('lws_adminpanel_arg_parse', array($me, 'parse'));
		\add_filter('lws_adminpanel_post_parse', array($me, 'parsePost'));
		\add_filter('lws_adminpanel_arg_parse_opt', array($me, 'parseTransposed'), 10, 2);
		\add_filter('lws_adminpanel_post_parse_opt', array($me, 'parsePostTransposed'), 10, 2);
	}

	/** For convenience, $options contains the transposed of $args matrix.
	 * Each entry is an array with 'format', 'required'...
	 * Then $attrs contains only 'values' and global arguments (as 'post', 'shallow').
	 * @return same array as parse() */
	function parseTransposed($attrs, $options)
	{
		if( !is_array($options) )
			$options = array();

		foreach( $options as $prop => $opt)
		{
			if( !is_array($opt) )
			{
				$attrs['format'][$prop] = $opt;
			}
			else foreach( array('format'=>'format', 'required'=>'required', 'defaults'=>'default', 'labels'=>'label') as $cat => $src )
			{
				if( isset($opt[$src]) )
					$attrs[$cat][$prop] = $opt[$src];
			}
		}

		return $this->parse($attrs);
	}

	/** For convenience, $options contains the transposed of $args matrix.
	 * Each entry is an array with 'format', 'required'...
	 * Then $attrs contains global arguments (as 'post', 'shallow').
	 * Read $_POST, no need to set 'values' in $attrs.
	 * @return same array as parsePost() */
	function parsePostTransposed($attrs, $options)
	{
		$attrs['post'] = true;
		$attrs['values'] = $_POST;
		return $this->parseTransposed($attrs, $options);
	}

	/** Same as parse but read $_POST, no need to set 'values' in $args. */
	function parsePost($args)
	{
		$args['post'] = true;
		$args['values'] = $_POST;
		return $this->parse($args);
	}

	/** Format values and test them. */
	function parse($args)
	{
		if( is_array($args) && isset($args['values']) && is_array($args['values']) )
		{
			$args['error'] = '';
			$args['valid'] = true;

			if( isset($args['post']) && boolval($args['post']) )
				$this->stripSlashes($args['values']);

			if( $args['valid'] && isset($args['defaults']) )
				$this->defaults($args);

			if( isset($args['format']) )
				$this->format($args);

			if( $args['valid'] && isset($args['required']) )
				$this->required($args);

			if( $args['valid'] && isset($args['shallow']) && !boolval($args['shallow']) )
				$this->nomore($args);
		}
		return $args;
	}

	protected function stripSlashes(&$values)
	{
		foreach( $values as $k => &$value )
		{
			if( is_array($value) )
				$this->stripSlashes($value);
			else
				$value = stripslashes($value);
		}
	}

	/** Format could be (should produce a value filtering):
	 * * d or i must be a integer
	 * * D or I or + must be a integer, greater than zero
	 * * 0 integer greater or equal to zero
	 * * f must be a float (we try to interpret localised float format)
	 * * F must be a float greater than 0.0 (we try to interpret localised float format)
	 * * s must be a string (trimmed)
	 * * S must be a not empty string (trimmed)
	 * * t (sanitize_text_field)
	 * * T (sanitize_text_field) not empty string
	 * * k (sanitize_key)
	 * * K (sanitize_key) not empty string
	 * * / start a regex (eg. look for a php string case insensitive "/php/i") (trimmed).
	 *
	 * If the format value is a callable, it must return (bool) true if format is ok.
	 * * a callable format is defined by an array with a key 'callable' and the callable as value.
	 * * eg. 'format' => array( 'my_var' => array('callable' => 'boolval') )
	 * If the format value is an array, it assumes the respective value in values is an array too.
	 * The array in format contains only one value that is the format of each element in the array in values.
	 **/
	protected function format(&$args)
	{
		foreach( $args['format'] as $key => $format )
		{
			if( isset($args['values'][$key]) )
			{
				$value = $args['values'][$key];
				if( $this->formatValue($args, $key, $format, $value) )
					$args['values'][$key] = $value;
			}
		}
		return true;
	}

	/** @param $value in/out
	 * @return bool format ok. */
	protected function formatValue(&$args, $key, $format, &$value)
	{
		if( is_array($format) )
		{
			if( isset($format['callable']) && is_callable($format['callable']) )
			{
				if( !call_user_func($format['callable'], $value) )
					return $this->error($args, sprintf(_x("%s value rejected", "Input array validation", 'lws-adminpanel'), $this->label($args, $key)));
			}
			else
			{
				if( is_string($value) )
				{
					$value = @base64_decode($value, true);
					if( $value )
						$value = @json_decode($value);
				}
				if( !is_array($value) )
					return $this->error($args, sprintf(_x("%s is not an array", "Input array validation", 'lws-adminpanel'), $this->label($args, $key)));
				if( !empty($format) )
				{
					$f = array_pop($format);
					$sub = sprintf(__("A value in %s", 'lws-adminpanel'), $this->label($args, $key));
					foreach( $value as &$v )
					{
						if( !$this->formatValue($args, $sub, $f, $v) )
							return false;
					}
				}
			}
		}
		else if( is_string($format) )
		{
			$f = substr($format, 0, 1);
			$u = strtoupper($f);
			if( !$this->isEmpty($value) || $u == $f || $f == '/' )
			{

				if( $f == '/' )
				{
					if( !preg_match($format, $value) )
						return $this->error($args, sprintf(_x("%s is not valid", "Input array validation", 'lws-adminpanel'), $this->label($args, $key)));
				}
				else if( $f == '0' )
				{
					if( !is_numeric($value) || ($value = intval($value)) < 0 )
						return $this->error($args, sprintf(_x("%s must be equal or greater than zero", "Input array validation", 'lws-adminpanel'), $this->label($args, $key)));
				}
				else if( $u == 'F' )
				{
					$v = $this->unlocaliseDecimal($value);
					if( $v === false )
						return $this->error($args, sprintf(_x("%s is not a decimal number", "Input array validation", 'lws-adminpanel'), $this->label($args, $key)));
					else if( $f == $u && $v <= 0.0 )
						return $this->error($args, sprintf(_x("%s must be greater than zero", "Input array validation", 'lws-adminpanel'), $this->label($args, $key)));
					$value = $v;
				}
				else
				{
					if( $u == 'I' || $u == 'D' || $u == '+' )
					{
						if( !is_numeric($value) )
							return $this->error($args, sprintf(_x("%s is not a number", "Input array validation", 'lws-adminpanel'), $this->label($args, $key)));
						if( $u == $f && ($value = intval(trim($value))) <= 0 )
							return $this->error($args, sprintf(_x("%s must be greater than zero", "Input array validation", 'lws-adminpanel'), $this->label($args, $key)));
					}
					else if( !is_string($value) )
					{
						return $this->error($args, sprintf(_x("%s is not a string", "Input array validation", 'lws-adminpanel'), $this->label($args, $key)));
					}
					else
					{
						if( $u == 'K' )
							$value = \sanitize_key($value);
						else if( $u == 'T' )
							$value = \sanitize_text_field($value);
						else
							$value = trim($value);

						if( $u == $f && $this->isEmpty($value) )
							return $this->error($args, sprintf(_x("%s is empty", "Input array validation", 'lws-adminpanel'), $this->label($args, $key)));
					}
				}
			}
		}
		else
			error_log("[" . getClass() . "] unknown given format ($key): " . print_r($format, true));
		return true;
	}

	/** guess a float convertion whatever localisation format.
	 *	Decimal part is sorted out by first dot or comma found from right of the string. */
	protected function unlocaliseDecimal($number)
	{
		$dot = strrpos($number, '.');
		$comma = strrpos($number, ',');
		static $antipattern = '/[\s\'\.,]/';

		if( $dot === false && $comma === false )
		{
			$number = preg_replace($antipattern, '', $number);
			return \is_numeric($number) ? intval($number) : false;
		}
		else
		{
			if( $dot === false )
				$sep = $comma;
			else if( $comma === false )
				$sep = $dot;
			else
				$sep = max($dot, $comma);

			$int = preg_replace($antipattern, '', substr($number, 0, $sep));
			$dec = trim(substr($number, $sep+1));
			if( !empty($int) && !is_numeric($int) )
				return false;
			if( !empty($dec) && !is_numeric($dec) )
				return false;
			return floatval(intval($int).'.'.$dec);
		}
	}

	/** must be called after format. Raise an error if value is missing or empty. */
	protected function required(&$args)
	{
		if( is_array($args['required']) )
		{
			foreach( $args['required'] as $key => $required )
			{
				if( $required && ( !isset($args['values'][$key]) || $this->isEmpty($args['values'][$key]) ) )
					return $this->error($args, sprintf(_x("Missing required value for %s", "Input array validation", 'lws-adminpanel'), $this->label($args, $key)));
			}
		}
		else if( boolval($args['required']) )
		{
			if( isset($args['format']) && is_array($args['format']) )
			{
				foreach( $args['format'] as $key => $f )
				{
					if( !isset($args['values'][$key]) )
						return $this->error($args, sprintf(_x("Missing entry %s", "Input array validation", 'lws-adminpanel'), $this->label($args, $key)));
				}
			}
			foreach( $args['values'] as $key => $value )
			{
				if( $this->isEmpty($value) )
					return $this->error($args, sprintf(_x("Missing value for %s", "Input array validation", 'lws-adminpanel'), $this->label($args, $key)));
			}
		}
	}

	protected function isRequiredValue(&$args, $key)
	{
		if( isset($args['required']) )
		{
			if( is_array($args['required']) )
			{
				if( isset($args['required'][$key]) )
					return boolval($args['required'][$key]);
			}
			else if( boolval($args['required']) )
				return true;
		}
		return false;
	}

	/** Provided a special function Since empty() will mix up a string '0' and ''. */
	protected function isEmpty($value)
	{
		if( is_array($value) )
			return empty($value);
		else
			return (strlen(trim($value)) <= 0);
	}

	/** must be called after format. Feed missing values with default ones. */
	protected function defaults(&$args)
	{
		if( is_array($args['defaults']) )
		{
			foreach( $args['defaults'] as $key => $dft )
			{
				if( !isset($args['values'][$key]) || $this->isEmpty($args['values'][$key]) )
					$args['values'][$key] = $dft;
			}
		}
		else
		{
			foreach( $args['values'] as $key => &$val )
			{
				if( $this->isEmpty($val) )
					$val = $args['defaults'];
			}
			if( isset($args['format']) && is_array($args['format']) )
			{
				foreach( $args['format'] as $key => $f )
				{
					if( !isset($args['values'][$key]) )
						$args['values'][$key] = $args['defaults'];
				}
			}
		}
		return true;
	}

	/** do not accept any undescribed value. All keys in values must be in format. */
	protected function nomore(&$args)
	{
		if( isset($args['format']) && is_array($args['format']) )
		{
			foreach( $args['values'] as $key => $value )
			{
				if( !isset($args['format'][$key]) )
					return $this->error($args, sprintf(_x("Unknown entry %s", "Input array validation", 'lws-adminpanel'), $this->label($args, $key)));
			}
		}
		return true;
	}

	protected function label($args, $key)
	{
		if( isset($args['labels']) && is_array($args['labels']) && isset($args['labels'][$key]) )
			return $args['labels'][$key];
		return $key;
	}

	protected function error(&$args, $error)
	{
		$args['valid'] = false;
		$args['error'] = $error;
		return false;
	}

	/** for editlist source compatibility. */
	public static function invalidArray(&$array, $format, $strictFormat=true, $strictArray=true, $translations=array())
	{
		$args = self::fromOldFormat($format, $strictFormat, $strictArray, $translations);
		$args['values'] = $array;
		$args = (new self())->parse($args);

		foreach( $args['values'] as $k => $v )
			$array[$k] = $v;
		return $args['valid'] ? false : $args['error'];
	}

	/** @param $array the associative array to validate.
	 * @param $format an associative array with same keys as $array and value describing format:
	 ** if the format starts by a
	 ***	s : string,
	 *** i : number
	 *** a slash, it is assumed to be a regex (eg. look for a php string case insensitive "/php/i").
	 *** A : an array, you could append a / with array value format.
	 ** it can be followed by options:
	 *** 0 : equal or greater than zero,
	 *** + : not empty string or number greater than zero,
	 *** o : optional,
	 *** a : add empty or 0 if not exists in $array.
	 * @param $strictFormat (bool) all key in array must be in format.
	 * @param $strictArray (bool) all key in format must be in array.
	 * @param $translations (array) use same key as $format, if isset, replace the key in error string.
	 * @return false if ok, or a string with error if not. */
	protected static function fromOldFormat($format, $strictFormat=true, $strictArray=true, $translations=array())
	{
		$formats = array();
		$defaults = array();
		$required = array();
		// convert this complicated format for backward compatibility.
		foreach( $format as $k => $value )
		{
			$v = substr($value, 0, 1);
			if( $v == 'A' ) // expect an array
			{
				$formats[$k] = array();
				$opt = explode('/', $value);

				if( count($opt) > 1 )
				{
					$f = $opt[1];
					$sub = str_replace(array('a', 'o', '+', '0'), '', $f);
					if( strpos($sub, 'i') !== false && strpos($f, '0') !== false ) // {int} ge 0
						$sub = '0';
					else if( strpos($f, '+') !== false ) // not empty value
						$sub = strtoupper($sub);
					$formats[$k][] = $sub;
				}

				$f = $opt[0];
				if( ($strictArray || strpos($f, '+') !== false) && strpos($f, 'o') === false )
				{
					$required[$k] = true;
					$defaults[$k] = array();
				}
			}
			else if( $v == '/' ) // expect a regex pattern
			{
				$formats[$k] = $value;
				if( $strictArray )
					$required[$k] = true;
			}
			else
			{
				$opt = explode(':', $value);
				$f = $opt[0];

				$formats[$k] = str_replace(array('a', 'o', '+', '0'), '', $f);
				if( strpos($formats[$k], 'i') !== false && strpos($f, '0') !== false ) // {int} ge 0
					$formats[$k] = '0';
				else if( strpos($f, '+') !== false ) // not empty value
					$formats[$k] = strtoupper($formats[$k]);


				if( $strictArray && (strpos($f, 'o') === false) ) // required, 'o' => means optional anyway
					$required[$k] = true;

				if( strpos($f, 'a') !== false ) // default value
				{
					$required[$k] = false;
					$i = (strpos($f, 'i') !== false);
					if( count($opt) > 1 )
						$defaults[$k] = ($i ? intval($opt[1]) : $opt[1]);
					else
						$defaults[$k] = ($i ? 0 : '');
				}
			}
		}

		return array(
			'valid'    => true,
			'error'    => 'error',
			'format'   => $formats,
			'required' => $required,
			'defaults' => $defaults,
			'shallow'  => !$strictFormat,
			'labels'   => $translations
		);
	}
}
