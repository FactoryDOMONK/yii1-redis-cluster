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

    'import' => array(
            ...
            'ext.redisCluster.*',
            ...
            ),
  
    ...
    'redis_conn_timeout' => 2,//连接redis超时时间
    
	 'config_redis_servers' => array(
                'caipiao_redis_1' => array(
                    'host' => '127.0.0.1',
                    'port' => '6379',
                    'auth' => '123123',
                    'db' => 1,
                ),
            ),
     //队列redis配置
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
