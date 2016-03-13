<?php
	header('Content-type:text/html;charset=utf8');

	error_reporting(E_ALL);
	set_time_limit(0);
	extension_loaded('swoole') or die('The sockets extension is not loaded.' . PHP_EOL);
	extension_loaded('PDO') or die('The PDO extension is not loaded.' . PHP_EOL);
	date_default_timezone_set("Asia/Shanghai"); 
	
	//启动服务器
	$server = new Server();
	
	class Server {
		private $serv;
		private $pdo; 
		
		public function __construct() {
			$this->serv = new swoole_server("139.196.12.213",8899,SWOOLE_PROCESS,SWOOLE_TCP);
			$this->serv->set(array(
				'worker_num' => 3,//设置启动的worker进程数量
				'daemonize' => 1,//守护进程化
				'max_request' => 100,//worker进程在处理完1000次请求后结束运行.manager会重新创建一个worker进程.此选项用来防止worker进程内存溢出.
				'max_conn' => 100,//设置Server最大允许维持多少个tcp连接.超过此数量后,新进入的连接将被拒绝
				'dispatch_mode' => 2,
				'debug_mode'=> 1, 
				'task_worker_num' => 4, //MySQL连接的数量
				'package_max_length' => 256,
				'open_tcp_keepalive' => 1,//启用TCP-Keepalive死连接检测,踢掉死链接
				'tcp_keepidle' => 600,//连接在10分钟内没有数据请求,将开始对此连接进行探测
				'tcp_keepcount' => 3,//探测的次数,超过次数后将close此连接
				'tcp_keepinterval' => 3,//探测的间隔时间,单位秒
				'log_file' => '/DataDisk/log/beijing/swoole_modbus.log',
				'enable_reuse_port' => true,
				'open_cpu_affinity' => 1,//启用CPU亲和设置
			));

			$this->serv->on('WorkerStart', array($this, 'onWorkerStart')); 
			$this->serv->on('Start', array($this, 'onStart'));
			$this->serv->on('Connect', array($this, 'onConnect'));
			$this->serv->on('Receive', array($this, 'onReceive'));
			$this->serv->on('Task', array($this, 'onTask')); 
			$this->serv->on('Finish', array($this, 'onFinish')); 
			$this->serv->on('Close', array($this, 'onClose'));

			$this->serv->start();
		}

		public function onWorkerStart($serv , $worker_id) { 
			echo "onWorkerStart" . PHP_EOL; 
			// 判定是否为Task Worker进程 
			if($worker_id >= $serv->setting['worker_num']) { 
				try {
					$this->pdo = new PDO( 
						"mysql:host=139.196.12.213;port=3306;dbname=pminfo",  
						"root",  
						"Shengjie123",  
						array( 
							PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'UTF8';", 
							PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
							PDO::ATTR_PERSISTENT => true 
						) 
					); 
				} catch (PDOException $e) {
					echo  'Could not connect mysql: ' . $e->getMessage() . PHP_EOL;
				}
			} 
		}
		
		public function onStart($serv) {
			echo "Waiting for clients to connect" . PHP_EOL;
		}

		public function onConnect($serv, $fd, $from_id ) {
			echo "Client connected:" . PHP_EOL;
			$info = $serv->connection_info($fd, $from_id);
			echo "server_port = " . $info['server_port'] . PHP_EOL;
			echo "remote_port = " . $info['remote_port'] . PHP_EOL;
			echo "remote_ip = " . $info['remote_ip'] . PHP_EOL;
		}

		public function onReceive(swoole_server $serv, $fd, $from_id, $data) {
			list($DeviceID, $PmValue, $TriggerTime, $iPmQuality, $BgColor) = explode(',', $data); 
						
			echo "Receive Data:" . $data . PHP_EOL;
			echo "Device ID:" . $DeviceID . PHP_EOL;
			echo "PM2.5 Value:" . $PmValue . PHP_EOL;
			echo "TriggerTime:" . $TriggerTime . PHP_EOL;

			$PmQuality = "";
			
			if ($iPmQuality === '1') {
				$PmQuality = "优";
			} else if ($iPmQuality === '2') {
				$PmQuality = "良";
			} else if ($iPmQuality === '3') {
				$PmQuality = "轻度污染";
			} else if ($iPmQuality === '4') {
				$PmQuality = "中度污染";
			} else if ($iPmQuality === '5') {
				$PmQuality = "重度污染";
			} else if ($iPmQuality === '6') {
				$PmQuality = "严重污染";
			}
			
			$sql = array('sql' => 'INSERT INTO dtm_pminfo_beijing_table VALUES(?, ?, ?, ?, ?)', 'param' => array($DeviceID, $PmValue, $TriggerTime, $PmQuality, $BgColor),
			'fd' => $fd); 
			
			$serv->task(json_encode($sql)); 	
		}

		public function onTask($serv, $task_id, $from_id, $data) { 
			$sql = json_decode($data, true); 
			
			//判断设备有没有注册
			$res = $this->pdo->query("SELECT * FROM dtm_deviceinfo_beijing_table WHERE device_id = '".$sql['param'][0]."'");
			if ($res->rowCount() == 1) {
				try { 				
					$insert = $this->pdo->prepare($sql['sql']); 
					$result = $insert->execute($sql['param']);

					if ($result) {
						printf ("%d Row inserted" . PHP_EOL . PHP_EOL . PHP_EOL, $insert->rowCount());
						$serv->send($sql['fd'],"数据写入成功\n");
						
						return true;
					} else {
						echo "insert data failed" . PHP_EOL . PHP_EOL . PHP_EOL;	
						$serv->send($sql['fd'],"数据写入失败\n");

						return false;
					}
				} catch (PDOException $e) { 
					var_dump($e); 
					echo 'onTask failed: ' . $e->getMessage() . PHP_EOL . PHP_EOL . PHP_EOL;
					$serv->send($sql['fd'],"数据写入失败\n");
					return false; 
				} 
			} else {
				echo "device no register" . PHP_EOL . PHP_EOL . PHP_EOL;
				$serv->send($sql['fd'],"设备没有注册\n");
				return true;
			}
		} 
  
		public function onFinish($serv, $task_id, $data) { 
		// finish操作是可选的，也可以不返回任何结果
		} 	

		public function onClose($serv, $fd, $from_id) {
			$info = $serv->connection_info($fd, $from_id);
			echo "Close connection:\t" . $info['server_port'] . "\t" . $info['remote_ip'] . PHP_EOL . PHP_EOL . PHP_EOL;		
		}		
	}
?>

