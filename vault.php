<?php
/**
 * Vault - A simple PHP caching library 
 * 
 * Copyright (c) 2012 Phil Newton
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without 
 * modification, are permitted provided that the following conditions are met:
 *    
 *    1. Redistributions of source code must retain the above copyright 
 *       notice, this list of conditions and the following disclaimer.
 *     
 *    2. Redistributions in binary form must reproduce the above copyright 
 *       notice, this list of conditions and the following disclaimer in the 
 *       documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 * 
 * @package    Vault
 * @author     Phil Newton <phil@sodaware.net>
 * @copyright  2012 Phil Newton <phil@sodaware.net>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 */


// ----------------------------------------------------------------------
// -- Cacheable Object
// ----------------------------------------------------------------------

class Vault_Object
{
    private $_data;
    private $_modified;
    private $_lifespan;
    
    public function __construct($data, $lifespan)
    {
        $this->_data = $data;
        $this->_modified = time();
        $this->_lifespan = $lifespan;
    }
    
    /**
     * Check if the object is expired.
     * @return bool True if expired, false if not.
     */
    public function isExpired()
    {
        return (time() >= $this->getExpires());
    }
    
    /**
     * Get the timestamp for this object's expiration.
     * @return int Expiration timestamp.
     */
    public function getExpires()
    {
        return $this->_modified + $this->_lifespan;
    }
    
    /**
     * Get the actual cached data.
     */
    public function getContents()
    {
        return $this->_data;
    }
    
    /**
     * Get the timestamp this object was last modified at.
     * @return int Modification timestamp.
     */
    public function getModified()
    {
        return $this->_modified;
    }
    
}


// ----------------------------------------------------------------------
// -- Cache Implementations
// ----------------------------------------------------------------------

/**
 * Interface that all cache providers must use.
 */
interface Vault_Cache
{
    
    public function isAvailable();
    
    public function clear();
    public function clearExpired();
    
    public function fetch($key);
    public function store($key, $value, $lifespan = 3600);
    public function remove($key);
    
    public function __construct($options = array());
}

/**
 * Adds APC cache support
 */
class Vault_Cache_APC implements Vault_Cache
{
    
    public function isAvailable()
    {
        return extension_loaded('apc');
    }    
    
    public function clear()
    {
        
    }
    
    public function clearExpired()
    {
        
    }
    
    /**
     * @return Vault_Object
     */
    public function fetch($key)
    {
        return apc_fetch($key);
    }
    
    public function store($key, $value, $lifespan = 3600)
    {
        apc_store($key, $value, $lifespan);
    }
    
    public function remove($key)
    {
        apc_delete($key);
    }
    
    public function __construct($options = array())
    {
        if (!$this->isAvailable()) {
            error_log('Vault: Cannot use Vault_Cache_APC - APC is not installed');
        }
    }
    
}

/**
 * Fallback cache method - does not save between sessions
 */
class Vault_Cache_Volatile implements Vault_Cache
{
    private $_data;

    public function isAvailable()
    {
        return true;
    }
    
    public function clear()
    {
        $this->_data = array();
    }
    
    public function clearExpired()
    {
        
    }
    
    public function fetch($key)
    {
        return $this->_data[$key];
    }
    
    public function store($key, $value, $lifespan = 3600)
    {
        $this->_data[$key] = $value;
    }
        
    public function remove($key)
    {
        apc_delete($key);
    }
    
    public function __construct($options = array())
    {
        $this->_data = array();
    }
    
}


// ----------------------------------------------------------------------
// -- Main Vault implementation
// ----------------------------------------------------------------------

class Vault
{
    
    private static $_cache;
    
    /**
     * Initialise vault.
     *
     * @param string $cacheType The cache type to use. Default is APC.
     * @param array $cacheOptions An optional array of options.
     */
    public static function setup($cacheType = 'APC', $cacheOptions = array())
    {
        $cacheClass = 'Vault_Cache_' . $cacheType;
        if (class_exists($cacheClass)) {
            self::$_cache = new $cacheClass($cacheOptions);
            if (!self::$_cache->isAvailable()) {
                self::$_cache = new Vault_Cache_Volatile($cacheOptions);
            }
        }
    }
    
    /**
     * Get the "last modified" timestamp for a vault entry.
     *
     * @param string $key The name of the value to check.
     * @return int The last modified timestamp, or false if value not present.
     */
    public function getLastModified($key)
    {
        $data = self::_getCache()->fetch($key);
        return ($data) ? $data->getModified() : false;
    }
    
    /**
     * Fetch a value from the cache.
     * @param string $key The name of the value to fetch.
     * @return mixed The found value, or null if not present.
     */
    public static function fetch($key)
    {
        $data = self::_getCache()->fetch($key);
        return ($data) ? $data->getContents() : null;
    }
    
    /**
     * Store a value in the cache.
     * 
     * @param string $key Key name for this entry.
     * @param mixed $value The value to cache.
     * @param int $lifespan Lifespan for this item. Default is 1 hour.
     */
    public static function store($key, $value, $lifespan = 3600)
    {
        self::_getCache()->store($key, new Vault_Object($value, $lifespan), $lifespan);
    }
    
    /**
     * Remove a single cached value.
     * 
     * @param string $key The cahe key to remove.
     */
    public static function remove($key)
    {
        $this->_getCache()->remove($key);
    }
    
    /**
     * Clear _all_ cached values.
     */
    public static function clear()
    {
        self::_getCache()->clear();
    }
    
    /**
     * Get the internal cache object. Will use the volatile cache
     * if vault has not been initialised.
     *
     * @return Vault_Cache A Vault_Cache implementation.
     */
    private static function _getCache()
    {
        if (!self::$_cache) {
            self::$_cache = new Vault_Cache_Volatile();
        }
        return self::$_cache;
    }    
    
}
