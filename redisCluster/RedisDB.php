<?php
/**
 * @redis类
 * 
 * @author linming
 * @date 2014-11-24
 */

include_once dirname(__FILE__) . '/Flexihash.php';

class RedisDB
{
    protected static $_models=array();
    protected $_connectPool=null;
    protected $hash=null;
    private   $timeout;
  
    public static function model($className=__CLASS__)
    {
        $model=null;
        if (isset(self::$_models[$className]))
            $model=self::$_models[$className];
        else {
            $model=self::$_models[$className]=new $className();
        }
        return $model;
    }
  
    public function __construct()
    {
        $configRedisServers = Yii::app()->params['config_redis_servers'];
        $this->hash=new Flexihash();
        $nodes=array_keys($configRedisServers);
        $this->hash->addTargets($nodes);
        $this->timeout = Yii::app()->params['redis_conn_timeout'];
    }

    public function __call($method_name, $arguments)
    {
        //对不带参数的redis方法没有处理
        if (empty($arguments)) {
            Yii::log("Redis [method:$method_name] args is empty!",'error');
            return null;
        }
        if(strcasecmp($method_name,'delete') == 0){
            Yii::log("Redis [method:$method_name] [arguments]:". serialize($arguments),'error');
        }

        $key = $arguments[0];
        $redis = $this->lookup($key);
        if (!$redis) {
            Yii::log("Redis not available!!",'error');
            return null;
        }

        if (!method_exists($redis, $method_name)) {
            Yii::log("Redis method not exist [method:$method_name] [arguments]:". serialize($arguments),'error');
            return null;
        }

        try{
            return call_user_func_array(array($redis, $method_name), $arguments);
        }catch (Exception $e){
            Yii::log("Redis call FAIL [method:$method_name][arguments]:". serialize($arguments),'error');
            return null;
        }
        Yii::log("Redis impossiable [method:$method_name] [arguments]:". serialize($arguments),'error');
        return null;
    }

    /**
     * 获取多个key的value
     * @param array $keys
     * @return array $values
     */
    public function mget(array $keys)
    {
        if(!is_array($keys)){
            return null;
        }

        $result = array();

        foreach($keys as $key){
            $result[$key] = $this->get($key);
        }

        return $result;
    }
 
 
    /**
     * 批量将多个值写入redis中，参数数组必须为命名数组
     * @param array $values
     * @return boolean
     */
    public function mset(array $data)
    {
        if(!is_array($data)){
            return null;
        }

        $result = array();
        foreach($data as $key=>$value){
            $result[] = $this->set($key,$value);
        }

        return $result;
    }
 
    /**
     * 获取key对应的redis实例
     * @param string $node_key
     * @param string $host
     * @param string $port
     * @return Redis
     */
    public function getNode($node_key,$host,$port,$auth,$dbIndex)
    {
        if(!isset($this->_connectPool[$node_key])||$this->_connectPool[$node_key]==null){
            $_redis=new Redis();
            $result = $_redis->connect($host, $port, $this->timeout);
            if (empty($result)||!is_object($_redis)) {
                //redis连接失败 返回FALSE 并打印fatal日志
                Yii::log("Redis connect FAIL. [redis_node:$node_key][host:$host][port:$port]",'error');
                return FALSE;
            }
            $_redis->auth($auth);
            $_redis->select($dbIndex);
            $this->_connectPool[$node_key]=$_redis;
        }else{
            $_redis = $this->_connectPool[$node_key];
        }
        return $_redis;
    }


    /**
     * 检查$key是否可丢失,返回对应的server
     * @param $key
     */
    private function lookup($key)
    {

        $node_key = $this->hash->lookup($key);
        $configRedisServers = Yii::app()->params['config_redis_servers'];
        $config = $configRedisServers[$node_key];
        $host = $config['host'];
        $port = $config['port'];
        $dbIndex = $config['db'];
        $auth = $config['auth'];

        return $this->getNode($node_key,$host,$port,$auth,$dbIndex);
    }
}
