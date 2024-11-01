<?php
namespace LWS\Adminpanel\Tools;

class Cache
{
	public static $cacheExpire = 604800; /// in second. 604800 is a week (7*60*60*24)

	/** @param $protectDir (bool) true: deny acces from any - then take care to place your file in a subdirectory.
	 * @param $filename (string) relative path+filename to save/read in cache, take care to use a subdir if you plan to protect it. */
	public function __construct($filename, $protectDir=false)
	{
		$this->protectDir = $protectDir;
		$this->filename = $filename;

		$this->cache = sys_get_temp_dir();
		if( defined('WP_CONTENT_DIR') )
			$this->cache = WP_CONTENT_DIR . '/cache/lws';
		$this->cache = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $this->cache);

		$this->path = $this->cache . DIRECTORY_SEPARATOR . $this->filename;
	}

	public function url()
	{
		if( !file_exists($this->cache) )
			return '';
		else
			return \content_url('/cache/lws/' . $this->filename);
	}

	private function buildDir()
	{
		if( !file_exists($this->cache) )
		{
			if( false === @mkdir($this->cache, 0755, true) )
			{
				error_log("Cannot create cache directory : " . $this->cache);
				return false;
			}
		}
		if( !is_dir($this->cache) )
		{
			error_log("Cache path is already used : " . $this->cache);
			return false;
		}
		if( $this->protectDir )
		{
			$htaccess = dirname($this->path) . '/.htaccess';
			if( !file_exists($htaccess) )
			{
				if( false === file_put_contents($htaccess, "deny from all") )
					error_log("Cannot restrict access to cache dir : " . $htaccess);
			}
		}
		return true;
	}

	/** create cache file */
	public function put($content)
	{
		if( !$this->buildDir() )
			return false;

		if( file_exists($this->path) )
		{
			if( false == unlink($this->path) )
			{
				error_log("Old cached file version cannot be removed. See " . $this->path);
				return false;
			}
		}

		if( false === @file_put_contents($this->path, $content, LOCK_EX) )
			error_log("Writing cached file failed (check permission) on " . $this->path);
		else
		{
			@chmod($this->path, 444); // set readonly for anyone
			return true;
		}
		return false;
	}

	public function isValid($failIfExpire=true, $expireDelay=0)
	{
		if( !file_exists($this->path) )
			return false;

		if( !is_readable($this->path) )
		{
			error_log("Cannot read cached file : " . $this->path);
			return false;
		}

		if( $expireDelay <= 0 )
			$expireDelay = self::$cacheExpire;
		if( $expireDelay > 0 && $failIfExpire )
		{
			$delay = time() - @filemtime($this->path);
			if( $delay > $expireDelay )
				return false;
		}

		if( defined('LWS_NO_CACHE') && LWS_NO_CACHE )
			return false;
		else
			return true;
	}

	/** @return the cached content, empty string if no cache, false if expire or error.
	 * @param $expireDelay in second, if <= 0 use default @see $this->cacheExpire.
	 */
	public function pop($failIfExpire=true, $expireDelay=0, $failIfNoCache=false)
	{
		if( !file_exists($this->path) )
			return ($failIfNoCache?false:'');

		if( !is_readable($this->path) )
		{
			error_log("Cannot read cached file : " . $this->path);
			return false;
		}

		if( $expireDelay <= 0 )
			$expireDelay = self::$cacheExpire;
		if( $expireDelay > 0 && $failIfExpire )
		{
			$delay = time() - @filemtime($this->path);
			if( $delay > $expireDelay )
				return false;
		}

		if( defined('LWS_NO_CACHE') && LWS_NO_CACHE )
			return ($failIfNoCache?false:'');
		else
			return @file_get_contents($this->path);
	}

	/** create cache file */
	public function del()
	{
		if( file_exists($this->path) )
		{
			if( false == unlink($this->path) )
			{
				error_log("Cached file cannot be removed. See " . $this->path);
				return false;
			}
		}
		return true;
	}
}
