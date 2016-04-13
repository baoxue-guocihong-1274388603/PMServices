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
			$this->serv = new swoole_server("139.196.12.213",8082,SWOOLE_PROCESS,SWOOLE_TCP);
			$this->serv->addlistener("139.196.12.213", 8083, SWOOLE_TCP);
			$this->serv->addlistener("139.196.12.213", 8084, SWOOLE_TCP);
			//$this->serv->addlistener("139.196.12.213", 8085, SWOOLE_TCP);
			$this->serv->addlistener("139.196.12.213", 8086, SWOOLE_TCP);
			$this->serv->addlistener("139.196.12.213", 8087, SWOOLE_TCP);
			
			$this->serv->set(array(
				'worker_num' => 3,//设置启动的worker进程数量
				'daemonize' => 1,//守护进程化
				'max_request' => 100,//worker进程在处理完1000次请求后结束运行.manager会重新创建一个worker进程.此选项用来防止worker进程内存溢出.
				'max_conn' => 100,//设置Server最大允许维持多少个tcp连接.超过此数量后,新进入的连接将被拒绝
				'dispatch_mode' => 2,
				'debug_mode'=> 1, 
				'task_worker_num' => 4, //MySQL连接的数量
				'package_max_length' => 256,
				//每5秒侦测一次心跳，Swoole会轮询所有TCP连接，将超过15s心跳时间的连接关闭掉
				//'heartbeat_check_interval' => 5,
				//'heartbeat_idle_time' => 15,
				//'open_tcp_keepalive' => 1,//启用TCP-Keepalive死连接检测,踢掉死链接
				//'tcp_keepidle' => 300,//连接在300秒内没有数据请求,将开始对此连接进行探测
				//'tcp_keepcount' => 3,//探测的次数,超过次数后将close此连接
				//'tcp_keepinterval' => 10,//探测的间隔时间,单位秒
				'log_file' => '/DataDisk/log/beijing/swoole_gprs.log',
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
			$str = bin2hex($data);
			
			for ($i = 0; $i < strlen($str); $i += 8)  {
				$ReceiveData = substr($str, $i, 8);
				$DeviceID = hexdec(substr($ReceiveData, 0, 4));
				$PmValue = hexdec(substr($ReceiveData, 4, 4));
				$TriggerTime = date("Y-m-d H:i:s");
				$PmQuality = "优";
				$BgColor = "pjadt_quality_bglevel_1";
				
				echo "Receive Data:" . $ReceiveData . PHP_EOL;
				echo "Device ID:" . $DeviceID . PHP_EOL;
				echo "PM2.5 Value:" . $PmValue . PHP_EOL;
				echo "TriggerTime:" . $TriggerTime . PHP_EOL;
				
				if ($PmValue >= 1000) {
					return;
				}
				
				if (($PmValue > 0) && ($PmValue < 35)) {
					$PmQuality = "优";
					$BgColor = "pjadt_quality_bglevel_1";
				} else if (($PmValue >= 35) && ($PmValue < 75)) {
					$PmQuality = "良";
					$BgColor = "pjadt_quality_bglevel_2";
				} else if (($PmValue >= 75) && ($PmValue < 115)) {
					$PmQuality = "轻度污染";
					$BgColor = "pjadt_quality_bglevel_3";
				} else if (($PmValue >= 115) && ($PmValue < 150)) {
					$PmQuality = "中度污染";
					$BgColor = "pjadt_quality_bglevel_4";
				} else if (($PmValue >= 150) && ($PmValue < 250)) {
					$PmQuality = "重度污染";
					$BgColor = "pjadt_quality_bglevel_5";
				} else if ($PmValue > 250) {
					$PmQuality = "严重污染";
					$BgColor = "pjadt_quality_bglevel_6";
				} 
				
				$sql = array('sql' => 'INSERT INTO dtm_pminfo_beijing_table VALUES(?, ?, ?, ?, ?)', 'param' => array($DeviceID, $PmValue, $TriggerTime, $PmQuality, $BgColor)); 
				
				$serv->task(json_encode($sql)); 
				
				if ($DeviceID === 6) {
					if (($PmValue >= 0) && ($PmValue < 50)) {
						$VirtualPmValue = $PmValue * 0.7 + rand(0,5);
					} else if (($PmValue >= 50) && ($PmValue < 100)) {
						$VirtualPmValue = $PmValue * 0.5 + rand(0,10);
					} else if (($PmValue >= 100) && ($PmValue < 200)) {
						$VirtualPmValue = $PmValue * 0.4 + rand(0,20);
					} else if (($PmValue >= 200) && ($PmValue < 500)) {
						$VirtualPmValue = $PmValue * 0.3 + rand(0,20);
					} else if ($PmValue >= 500) {
						$VirtualPmValue = rand(80,150);
					}
					
					if (($VirtualPmValue > 0) && ($VirtualPmValue < 35)) {
						$PmQuality = "优";
						$BgColor = "pjadt_quality_bglevel_1";
					} else if (($VirtualPmValue >= 35) && ($VirtualPmValue < 75)) {
						$PmQuality = "良";
						$BgColor = "pjadt_quality_bglevel_2";
					} else if (($VirtualPmValue >= 75) && ($VirtualPmValue < 115)) {
						$PmQuality = "轻度污染";
						$BgColor = "pjadt_quality_bglevel_3";
					} else if (($VirtualPmValue >= 115) && ($VirtualPmValue < 150)) {
						$PmQuality = "中度污染";
						$BgColor = "pjadt_quality_bglevel_4";
					} else if (($VirtualPmValue >= 150) && ($VirtualPmValue < 250)) {
						$PmQuality = "重度污染";
						$BgColor = "pjadt_quality_bglevel_5";
					} else if ($VirtualPmValue >= 250) {
						$PmQuality = "严重污染";
						$BgColor = "pjadt_quality_bglevel_6";
					}

                    sleep(1);
                    
					$sql = array('sql' => 'INSERT INTO dtm_pminfo_beijing_table VALUES(?, ?, ?, ?, ?)', 'param' => array(2, $VirtualPmValue, $TriggerTime, $PmQuality, $BgColor)); 
					$serv->task(json_encode($sql)); 
				}
				
				sleep(1);//主要用来优化一次可能接收到多个数据包
			}
		}

		public function onTask($serv, $task_id, $from_id, $data) { 
			$sql = json_decode($data, true);

			try {
				$insert = $this->pdo->prepare($sql['sql']);
				$result = $insert->execute($sql['param']);

				if ($result) {
					printf("%d Row inserted" . PHP_EOL . PHP_EOL . PHP_EOL, $insert->rowCount());
				}

				return true;
			} catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    echo 'device id ' . $sql['param'][0] . ' is no register' . PHP_EOL . PHP_EOL . PHP_EOL;
                } else {
                    echo 'onTask failed: ' . $e->getMessage() . PHP_EOL . PHP_EOL . PHP_EOL;
                }

                return false;
            }
		} 
  
		public function onFinish($serv, $task_id, $data) { 
		// finish操作是可选的，也可以不返回任何结果
		} 	

		public function onClose($serv, $fd, $from_id) {
			$info = $serv->connection_info($fd, $from_id);
			echo 'Close connection:\t' . $info['server_port'] . '\t' . $info['remote_ip'] . PHP_EOL . PHP_EOL . PHP_EOL;		
		}		
	}
?>

