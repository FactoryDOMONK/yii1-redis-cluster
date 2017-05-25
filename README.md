# yii1-redis-cluster
================================

redis cluster extension for yii1.1

Installation
------------

The preferred way to install this extension is through https://github.com/king52311/yii1-redis-cluster

## Setting up the extension

In order to use the extension you first need to set it up. The first thing to do is to download the source code and place it somewhere accessible within your applications structure, I have chosen
`protected/extensions/redisCluster`.

Once you have the source code in place you need to edit your `main.php` configuration file (`console.php` will need modifying too if you intend to use this extension in the console) with
the following type of configuration:
```php
    'import' => array(
            ...
            'ext.redisCluster.*',
            ...
            ),

    ...
```
connect redis time out config
```php
    'redis_conn_timeout' => 2,
```
redis servers config
```php
	 'config_redis_servers' => array(
                'redis_1' => array(
                    'host' => '127.0.0.1',
                    'port' => '6379',
                    'auth' => '123123',
                    'db' => 1,
                ),
				'redis_2' => array(
                    'host' => '127.0.0.1',
                    'port' => '6379',
                    'auth' => '123123',
                    'db' => 1,
                ),
            ),


```
redis queues config
```php
    'config_redis_queue'=>array(
        'queue_default' => array(
            'host'=>'127.0.0.1'',
            'port'=>'6379',
            'auth'=>'123123',
            'db'=>3,
            'limit' => 0
        ),
        'queue_sms' => array(
            'host'=>'127.0.0.1'',
            'port'=>'6379',
            'auth'=>'123123',
            'db'=>3,
            'limit' => 0
        ),
```
Basic Usage
-----------



```php
 public static function example()
    {
        $hashName = REDIS_HASH_KEY;
        $redis = new RedisDB();
        return $redis->hGetAll($hashName);
    }
```

Here's how to send a task into queue:

```php
    /**
     * @param $type ;http_post,http_get,class
     * @queueName string
     * @url string
     * @params array
     * @return boolen
     */
    public static function registerQueue($type, $queueName, $url, $params)
    {
        $callback = array(
            'type' => $type,
            'queue' => $queueName,
            'url' => $url,
            'params' => $params,
            //'timestamp' => $timestamp,
        );
        $queue = new RedisQueue($queueName);
        $ret = $queue->putin($callback, $queueName);
        if (!is_int($ret)) {
            return FALSE;
        } else {
            return TRUE;
        }


    }
```
Here's how to send a timed task into queue:

```php
 /**
     * @param $type ;http_post,http_get,class
     * @queueName string
     * @url string
     * @params array
     * @return boolen
     */
    public static function registerSchedule($type, $queueName, $url, $params, $timestamp)
    {
        $callback = array(
            'type' => $type,
            'queue' => $queueName,
            'url' => $url,
            'params' => $params,
            'timestamp' => $timestamp,
        );
      
        //$queue = new RedisSchedule();
        //$ret = $queue->putin($callback,$queueName);
        $queue = new RedisQueue($queueName);
        $res = $queue->putin($callback, $queueName);
        if (!is_int($res)) {
            return FALSE;
        } else {
            return TRUE;
        }

    }
```
Here's how to receive task from queue
```php
public function actionWorker($queue = 'default') {
        if (!$this->redisqueue->exists($queue)) {
            echo "\n" . date("Y-m-d H:i:s") . "---Not found the name of " . $queue . " queue.\n";
            return;
        }

        $timestamp = time();
        $quit_time = rand(5, 10) * 60;

        $i = 0;
        while (true) {
            if (time() - $timestamp > $quit_time) {
                echo "\n" . "the worker over max times {$i} or over define process time: runed {$quit_time}s\n";
                break;
            } else {
                $task = $this->redisqueue->getit($queue);
                if (!empty($task)) {
                    echo '[NOTICE] ' . sprintf(time() . " Call %s [type=%s] [params=%s]\n", __FUNCTION__, $task['type'], json_encode($task));
                    $result = $this->queue_run($task);
                    echo '[RESULT] ' . json_encode($result) . "\n";
                    $i++;
                } else {
                    //echo time() ." blpop timeout,execute {$i}" . "\n";
                    break;
                }
            }
        }
    }
```
Here's how to receive timed task from queue
```php
public function actionSchedule($queue = 'default') {
        if (!$this->redisschedule->exists($queue)) {
            echo "\n" . date("Y-m-d H:i:s") . "---Not found the schedule name of " . $queue . ".\n";
            return;
        }

        $timestamp = time();
        $quit_time = rand(5, 10) * 60;

        $i = 1;
        while (true) {
            if (time() - $timestamp > $quit_time) {
                //echo "\n"."the worker over max times {$i} or over define process time: runed {$quit_time}s\n";
                break;
            } else {
                $tasks = $this->redisschedule->getit($queue);
                if ($tasks) {
                    foreach ($tasks as $key => $schedule_task) {
                        if (!$schedule_task) {
                            $schedule_task = $this->redisdb->get($key);
                            $this->redisdb->del($key);
                        }

                        echo '[NOTICE] ' . sprintf(date('Y-m-d H:i:s') . " Schedule Task [%s] [task=%s]\n", __FUNCTION__, $schedule_task);

                        $task = json_decode($schedule_task, true);

                        if (!isset($task['queue'])) {
                            $task_queue = QUEUE_DEFAULT;
                        } else {
                            $task_queue = $task['queue'];
                        }

                        unset($task['queue']);
                        unset($task['timestamp']);

                        $result = $this->redisqueue->putin($task, $task_queue);
                    }
                }
                sleep(1);
            }
        }
    }
```
