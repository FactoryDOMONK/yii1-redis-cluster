<?php

/**
 * Redis队列类
 * @author linming
 * @date 2014-11-24
 */
class RedisQueue {

    protected $_quit_time = 0; //阻塞队列超时时间，无限时
    protected $_connectPool = null;
    private $_queue_prefix = 'queue_';
    private $timeout;

    public function __construct() {
        $this->timeout = Yii::app()->params['redis_conn_timeout'];
    }

    /**
     * 在指定队列中添加任务
     * @param array $params
     * @param string $queue
     * @return NULL|string|number
     */
    public function putin($params, $queue = 'default') {
        Yii::log('call putin ' . __FUNCTION__ . " RedisQueue [queue=$queue] ".json_encode($params), 'info');
        if (empty($params)) {
            return null;
        }

        if (!self::exists($queue)) {
            Yii::log('cal putin ' . __FUNCTION__ . " RedisQueue [queue=$queue] [errmsg=队列 $queue 不存在]", 'error');
            return '队列' . $queue . '不存在';
        }

        return $this->add($queue, $params);
    }

    /**
     * 获取队列的任务，队列为空时阻塞quit_time定义的时间后退出
     *
     * @param string $queue        	
     * @return string
     */
    public function getit($queue = 'default') {
        if (!self::exists($queue)) {
            Yii::log('call ' . __FUNCTION__ . " RedisQueue [queue=$queue] [errmsg=队列 $queue 不存在]", 'error');
            return '队列' . $queue . '不存在';
        }

        try {
            $result = $this->get($queue);
            Yii::log('call get ' . __FUNCTION__ . " RedisQueue [queue=$queue] ". $result, 'info');
            if (!empty($result)) {
                return json_decode($result[1], true);
            } else {
                return null;
            }

        } catch (Exception $e) {
            // 报警
            // die('redis timeout.'."\n");
            return null;
        }
    }

    /**
     * 返回各个队列中的任务总数
     * @param string $queue
     * @return string
     */
    public function length($queue = 'default') {
        $queue_name = self::getQueuename($queue);
        if (isset(Yii::app()->params['config_redis_queue'][$queue_name])) {
            $config = Yii::app()->params['config_redis_queue'][$queue_name];
            $redis = self::getNode($queue_name, $config['host'], $config['port'], $config['auth'], $config['db']);
            return $redis->lLen($queue_name);
        }
        return 0;
    }

    /**
     * 
     * 检查queue名是否已经定义
     * @param unknown $queue
     * @return boolean
     */
    public function exists($queue) {
        if (!in_array(self::getQueuename($queue), array_keys(Yii::app()->params['config_redis_queue']))) {
            return false;
        }
        return true;
    }

    /**
     * 获取redis队列配置信息
     * @param string $queue
     * @return unknown
     */
    private function getConfig($queue) {
        $queue_name = self::getQueuename($queue);
        $queue_config = Yii::app()->params['config_redis_queue'][$queue_name];
        $queue_config['name'] = $queue_name;
        return $queue_config;
    }

    /**
     * 返回队列名称
     * @param string $queue
     * @return string
     */
    private function getQueuename($queue) {
        return $this->_queue_prefix . strtolower($queue);
    }

    /**
     * 获取配置定义的redis实例
     * @param string $node_key
     * @param string $host
     * @param int $port
     * @return NULL|Redis
     */
    private function getNode($node_key, $host, $port, $auth, $dbIndex) {
        if (!isset($this->_connectPool[$node_key]) || $this->_connectPool[$node_key] == null) {
            $_redis = new Redis();
            $result = $_redis->connect($host, $port);
            //$_redis->auth('UZAjrz7s2KndqV6');
            if (!$result) {
                Yii::log('cal getnode ' . __FUNCTION__ . " connect redis fail. [redis_node]:$node_key [host]:$host [port]:$port", 'error');
                return $result;
            }
            $_redis->auth($auth);
            $_redis->select($dbIndex);
            $this->_connectPool[$node_key] = $_redis;
        } else {
            $_redis = $this->_connectPool[$node_key];
        }
        return $_redis;
    }

    /**
     * 往队列中添加一个任务
     * @param string $queue
     * @param array $params
     * @return int
     */
    private function add($queue, $params) {
        $queue_config = self::getConfig($queue);
        $redis = self::getNode($queue_config['name'], $queue_config['host'], $queue_config['port'], $queue_config['auth'], $queue_config['db']);
        Yii::log('call add ' . __FUNCTION__ . " RedisQueue [queue=$queue] ", 'info');
        $result = $redis->rPush($queue_config['name'], json_encode($params));
        Yii::log('call rpush ' . __FUNCTION__ . " RedisQueue [queue=$queue] ".json_encode($result), 'info');
        // 如果定义了队列的limit，修剪队列不超过最大值
        if ($result && $queue_config['limit'] > 0 && self::length($queue) > $queue_config['limit']) {
            $redis->lTrim($queue_config['name'], 1, intval($queue_config['limit']));
            echo 'trim queue to ' . $queue_config['limit'] . "\n";
        }
        return $result;
    }

    /**
     * 从队列中获取一个任务
     * @param string $queue
     * @return array
     */
    private function get($queue) {
        $queue_config = self::getConfig($queue);
        $redis = self::getNode($queue_config['name'], $queue_config['host'], $queue_config['port'], $queue_config['auth'], $queue_config['db']);
        $result = $redis->blPop($queue_config['name'], $this->_quit_time);
        return $result;
    }

}
