<?php
/**
 * CiiRedisCache class file
 * @author Charles R. Portwood II <charlesportwoodii@etheal.net>
 * 
 * CiiRedisCache uses phpredis client {@link https://github.com/nicolasff/phpredis phpredis}.
 */
class CiiRedisCache extends CiiCache
{
	/**
	 * @var Redis the Redis instance
	 */
	protected $_redis=null;

	/**
	 * @var array default server data
	 */
	private $_serverDefaults=array(
		'host' => '127.0.0.1',
		'port' => 6379,
		'timeout' => 2.5,
		'db' => NULL
	);	

    /**
	 * @var array list of servers 
	 */
	private $_server=array();

	/**
	 * Initializes this application component.
	 * This method is required by the {@link IApplicationComponent} interface.
	 * It creates the redis instance and adds redis servers.
	 * @throws CException if redis extension is not loaded
	 */
	public function init()
	{
		$this->_server = CMap::mergeArray($this->_serverDefaults, $this->_server);
		parent::init();
        $this->getRedis();
	}

	/**
	 * @return Redis|null the redis instance used by this component.
	 */
	public function getRedis()
	{
		if($this->_redis!==null)
			return $this->_redis;
		else
		{
            $this->_redis = new Redis();
			if (isset($this->_server['socket']))
				$val = $this->_redis->connect($this->_server['socket']);
			else
			{
            	$connection = $this->_redis->pconnect(
					$this->_server['host'],
					$this->_server['port'],
					$this->_server['timeout']
				);

				if ($connection === false || $connection === NULL)
				{
					Yii::log('Unable to connect to Redis instance using data: ' . print_r($this->_server, true), 'warning', 'cii.cache.CiiRedisCache');
					throw new CException('Unable to connect to Redis instance for caching');
				}
			}
				
            if (!empty($this->_server['db']))
            	$this->_redis->select($this->_server['db']);
        }
	}

	/**
	 * REtrieves the servers
	 **/
	public function getServer()
	{
		return $this->_server;
	}
	
	/**
	 * Sets servers from config file
	 * @param array $config		config file
	 */
	public function setServer($config)
	{
		$this->_server = $config;
	}
	
	/**
	 * Retrieves a value from cache with a specified key.
	 * This is the implementation of the method declared in the parent class.
	 * @param string $key a unique key identifying the cached value
	 * @return string the value stored in cache, false if the value is not in the cache or expired.
	 */
	protected function getValue($key)
	{
		return $this->_redis->get($key);
	}

	/**
	 * Retrieves multiple values from cache with the specified keys.
	 * @param array $keys a list of keys identifying the cached values
	 * @return array a list of cached values indexed by the keys
	 * @since 1.0.8
	 */
	protected function getValues($keys)
	{
		return $this->_redis->mget($keys);
	}

	/**
	 * Stores a value identified by a key in cache.
	 * This is the implementation of the method declared in the parent class.
	 *
	 * @param string $key the key identifying the value to be cached
	 * @param string $value the value to be cached
	 * @param integer $expire the number of seconds in which the cached value will expire. 0 means never expire.
	 * @return boolean true if the value is successfully stored into cache, false otherwise
	 */
	protected function setValue($key,$value,$expire)
	{
		if($expire>0)
			return $this->_redis->setex($key,$expire,$value);
		else
			return $this->_redis->set($key,$value);
	}

	/**
	 * Stores a value identified by a key into cache if the cache does not contain this key.
	 * This is the implementation of the method declared in the parent class.
	 *
	 * @param string $key the key identifying the value to be cached
	 * @param string $value the value to be cached
	 * @param integer $expire the number of seconds in which the cached value will expire. 0 means never expire.
	 * @return boolean true if the value is successfully stored into cache, false otherwise
	 */
	protected function addValue($key,$value,$expire)
	{
		if($expire>0)
		{
            if($this->_redis->setnx($key,$expire,$value))
                return $this->_redis->expire($key,$expire);
            return false;
		}
		else
			return $this->_redis->setnx($key,$value);
	}

	/**
	 * Deletes a value with the specified key from cache
	 * This is the implementation of the method declared in the parent class.
	 * @param string $key the key of the value to be deleted
	 * @return boolean if no error happens during deletion
	 */
	protected function deleteValue($key)
	{
		return $this->_redis->del($key);
	}

	/**
	 * Deletes all values from cache.
	 * This is the implementation of the method declared in the parent class.
	 * @return boolean whether the flush operation was successful.
	 * @since 1.1.5
	 */
	protected function flushValues()
	{
		// As part of CiiMS 1.8, we only delete keys related to CiiMS rather than everything in the system
		$keys = $this->_redis->getKeys($this->generateUniqueIdentifier() . '*');
		foreach ($keys as $k)
			$this->deleteValue($k);

		return true;
	}
	
    /**
     * call unusual method
     * */
    public function __call($method,$args)
    {
        return call_user_func_array(array($this->_redis,$method),$args);
    }
    
    /**
	 * Returns whether there is a cache entry with a specified key.
	 * This method is required by the interface ArrayAccess.
	 * @param string $id a key identifying the cached value
	 * @return boolean
	 */
	public function offsetExists($id)
	{
		return $this->_redis->exists($id);
	}
}
