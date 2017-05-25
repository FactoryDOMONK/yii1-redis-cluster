<?php
/**
 * Redis 定时队列
 * @author linming
 * @date 2014-11-24
 */

class RedisSchedule
{
    protected $_connectPool=null;
    private   $_schedule_prefix='schedule_';
    private   $_key_prefix='S_SCHEDULE_';
    private   $timeout;

    public function __construct(){
        $this->timeout = Yii::app()->params['redis_conn_timeout'];
    }
    /**
     * 在指定队列中添加任务
     * @param array $params
     * @param string $queue
     * @return NULL|string|number
     */
    public function putin($params,$queue='default')
    {
        if(empty($params)){
            return null;
        }
  
        if(!isset($params['timestamp'])){
            return null;
        }
  
        if(!self::exists($queue)){
            Yii::log(" RedisQueue [queue=$queue] [errmsg=$queue NOT exist]",'error');
            return null;
        }
  
        return $this->add($params, $queue);
    }
 
    /**
     * 获取定时任务
     *
     * @param string $queue         
     * @return string
     */
    public function getit($queue='default')
    {
        if(!self::exists($queue)){
            Yii::log(" RedisQueue [queue=$queue] [errmsg=$queue NOT exist]", 'error');
            return null;
        }
  
        $result=self::get_task($queue);
        return $result;
    }
 
    /**
     * 删除定时任务
     * @param array $params
     * @param string $queue
     * @return string
     */
    public function delete($params,$queue='default')
    {
        if(!self::exists($queue)){
            Yii::log(" RedisQueue [queue=$queue] [errmsg=$queue NOT exist]",'error');
            return null;
        }
        $data=json_encode($params);
        $task_key= sprintf('%s%s_%s_%s',$this->_key_prefix, strtoupper($params['queue']),$params['timestamp'] , sha1($data));
  
        $queue_config=self::getConfig($queue);
        $redis=self::getNode($queue_config['name'],$queue_config['host'],$queue_config['port']);
  
        $ret=$redis->del($task_key);
        return $redis->zRem($queue_config['name'],$task_key);
    }
 
 
    /**
     * 返回指定队列中的任务总数
     * @param string $queue
     * @return string
     */
    public function length($queue='default') 
    {
        if(!self::exists($queue)){
            Yii::log(" RedisQueue [queue=$queue] [errmsg=$queue NOT exist]", 'error');
            return null;
        }
        $queue_config=self::getConfig($queue);
        $redis=self::getNode($queue_config['name'], $queue_config['host'], $queue_config['port']);
        return $redis->zCount($queue_config['name'],0,time());
    }
 
 
    /**
     * 
     * 检查queue名是否已经定义
     * @param unknown $queue
     * @return boolean
     */
    public function exists($queue)
    {
        if(!in_array(self::getScheduleName($queue),array_keys(Yii::app()->params['config_redis_schedule']))){
            return false;
        }
        return true;
    }
 
    /**
     * 获取redis队列配置信息
     * @param string $name
     * @return unknown
     */
    private function getConfig($queue)
    {
        $schedule_name=self::getScheduleName($queue);
        $schedule_config=Yii::app()->params['config_redis_schedule'][$schedule_name];
        $schedule_config['name']=$schedule_name;
        return $schedule_config;
    }

 
    /**
     * 返回队列名称
     * @param string $queue
     * @return string
     */
    private function getScheduleName($queue)
    {
        return $this->_schedule_prefix.strtolower($queue);
    }
 
    /**
     * 获取配置定义的redis实例
     * @param string $node_key
     * @param string $host
     * @param int $port
     * @return NULL|Redis
     */
    private function getNode($node_key,$host,$port)
    {
        if(isset($this->_connectPool[$node_key]) && $this->_connectPool[$node_key]!=null){
            $_redis = $this->_connectPool[$node_key];
            return $_redis;
        }
        $_redis=new Redis();
        $retry=3;
        while($retry > 0){
            $retry-=1;
            $result = $_redis->connect($host,$port,$this->timeout);
            if (empty($result)||!is_object($_redis)) {
                //redis连接失败 返回FALSE 并打印fatal日志
                Yii::log("RedisQueue connect FAIL.[redis_node:$node_key][host:$host][port:$port]", 'error');
                usleep(rand(1000, 9999));
                continue;
            }
//            $_redis->auth('UZAjrz7s2KndqV6');
            $this->_connectPool[$node_key]=$_redis;
            break;
        }
        return $_redis;
    } 
 
    /**
     * 往队列中添加一个任务
     * @param string $queue
     * @param array $params
     * @return int
     */
    private function add($params, $queue)
    {
        $data=json_encode($params);
        $task_key= sprintf('%s%s_%s_%s',$this->_key_prefix, strtoupper($params['queue']),$params['timestamp'] , sha1($data));

        //数据写入redis
        $queue_config=self::getConfig($queue);
        $redis=self::getNode($queue_config['name'],$queue_config['host'],$queue_config['port']);

        try{
        $result = $redis->set($task_key, $data);
        if($result){
        $result=$redis->zAdd($queue_config['name'],$params['timestamp'],$task_key);
        }
        }catch (Exception $e){
            Yii::log("RedisQueue call FAIL,", 'error');
            return null;
        }
        Yii::log("RedisQueue add {$queue_config['name']} {$params['timestamp']} $task_key $data",'info');

        return $result;
    }
 
    /**
     * 从队列中获取到时的任务
     * @param string $queue
     * @return array
     */
    private function get_task($queue)
    {
        $end_time = time();

        $queue_config=self::getConfig($queue);
        $redis=self::getNode($queue_config['name'],$queue_config['host'],$queue_config['port']);

        //redis事务开始
        $redis->multi();
        $redis->zRangeByScore($queue_config['name'],'-inf', $end_time);
        $redis->zDeleteRangeByScore($queue_config['name'],'-inf', $end_time);
        $result=$redis->exec();
        //redis事务完成
        $tasks = array();
        if($result[0]){
            foreach($result[0] as $task_key){
                $task_define = $redis->get($task_key);
                $tasks[$task_key] = $task_define;
                $redis->del($task_key);
            }
        }

        return $tasks;
    }
}
