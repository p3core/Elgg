<?php
/**
 * ElggHMACCache
 * Store cached data in a temporary database, only used by the HMAC stuff.
 *
 * @package    Elgg.Core
 * @subpackage HMAC
 */
class ElggHMACCache extends ElggCache {
	/**
	 * Set the Elgg cache.
	 *
	 * @param int $max_age Maximum age in seconds, 0 if no limit.
	 */
	function __construct($max_age = 0) {
		$this->setVariable("max_age", $max_age);
	}

	/**
	 * Save a key
	 *
	 * @param string $key          Name
	 * @param string $data         Value
	 * @param int    $expire_after Number of seconds to expire cache after
	 *
	 * @return boolean
	 */
	public function save($key, $data, $expire_after = null) {
		$dbprefix = elgg_get_config('dbprefix');
		$key = sanitise_string($key);
		$time = time();

		$query = "INSERT into {$dbprefix}hmac_cache (hmac, ts) VALUES ('$key', '$time')";
		return insert_data($query);
	}

	/**
	 * Load a key
	 *
	 * @param string $key    Name
	 * @param int    $offset Offset
	 * @param int    $limit  Limit
	 *
	 * @return string
	 */
	public function load($key, $offset = 0, $limit = null) {
		$dbprefix = elgg_get_config('dbprefix');
		$key = sanitise_string($key);

		$row = get_data_row("SELECT * from {$dbprefix}hmac_cache where hmac='$key'");
		if ($row) {
			return $row->hmac;
		}

		return false;
	}

	/**
	 * Invalidate a given key.
	 *
	 * @param string $key Name
	 *
	 * @return bool
	 */
	public function delete($key) {
		$dbprefix = elgg_get_config('dbprefix');
		$key = sanitise_string($key);

		return delete_data("DELETE from {$dbprefix}hmac_cache where hmac='$key'");
	}

	/**
	 * Clear out all the contents of the cache.
	 *
	 * Not currently implemented in this cache type.
	 *
	 * @return true
	 */
	public function clear() {
		return true;
	}

	/**
	 * Clean out old stuff.
	 *
	 */
	public function __destruct() {
		$dbprefix = elgg_get_config('dbprefix');
		$time = time();
		$age = (int) $this->getVariable("max_age");

		$expires = $time - $age;

		delete_data("DELETE from {$dbprefix}hmac_cache where ts<$expires");
	}
}
