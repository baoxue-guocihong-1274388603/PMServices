<?php
	header('Content-type:text/html;charset=utf-8');
	header('Content-type:text/json');
	
	$Type      = $_GET["Type"];
	$StartTime = $_GET["StartTime"];
	$EndTime   = $_GET["EndTime"];
	$DeviceID  = $_GET["DeviceID"];

	$link = @mysqli_connect ("139.196.12.213","root","Shengjie123","pminfo") or 
		die("Could not connect:".mysqli_connect_error());
	@mysqli_set_charset($link,"utf8") or 
		die("Error loading character set utf8:".mysqli_error($link));
	
	$FieldData = array();
	$ValueData = array();
	
	if($Type === "Normal"){
		$sql = "SELECT pmvalue,triggertime FROM dtm_pminfo_multiparam_table WHERE device_id = '".$DeviceID."' AND triggertime BETWEEN '".$StartTime."' AND '".$EndTime."' ORDER BY triggertime DESC";

		$result = mysqli_query($link,$sql);
		if($result){
			if(mysqli_num_rows($result)){
				$res = mysqli_query($link,"SELECT monitor_point FROM dtm_deviceinfo_multiparam_table WHERE device_id = '".$DeviceID."'");
				$r = mysqli_fetch_array($res);
					
				while($row = mysqli_fetch_array($result)){
					$Field = array("title"=>$r[0],"valueField"=>"AgentID".$DeviceID);
					$Data  = array("AgentID".$DeviceID => $row[0], "TriggerTime" => $row[1]);

					$FieldData[] = $Field;
					$ValueData[] = $Data;
				}
			}
		}
		
		if (!empty($FieldData) && !empty($ValueData)) {
			//将二维数组去除重复并按valueField升序排序
			$FieldData = unique_arr($FieldData,true);
			foreach ($FieldData as $key => $row) {
				$volume[$key]  = $row['valueField'];
			}
			array_multisort($volume, SORT_ASC, $FieldData);
			$jsonStr["Field"] = $FieldData;

			//将二维数组反转,按照时间升序排序
			$jsonStr["Value"] = array_reverse($ValueData,false);
					
			echo json_encode($jsonStr);
		} else {
			//当数据库中没有记录时返回一个空的json数据
			echo json_encode(array("Field"=>array(),"Value"=>array()));
		}
	}else if($Type === "Avarge"){
		$Days = 0;
		$sql = "SELECT datediff('".$EndTime."','".$StartTime."')";
		$result = mysqli_query($link,$sql);
		if($result){
			if(mysqli_num_rows($result)){
				while($row = mysqli_fetch_array($result)){
					$Days = $row[0];//得到起始时间和结束时间之间相差的天数
				}	
			}
		}

		//循环获取每天的平均值,按时间升序排序
		for($i = 0; $i <= $Days; $i++){
			$sql = "SELECT FLOOR(AVG(pmvalue)),date(triggertime) FROM dtm_pminfo_multiparam_table WHERE	date(triggertime) in (SELECT date(DATE_ADD('".$StartTime."',INTERVAL '".$i."' DAY))) AND device_id = '".$DeviceID."'";

			$result = mysqli_query($link,$sql);
			if($result){
				if(mysqli_num_rows($result)){
					while($row = mysqli_fetch_array($result)){
						if($row[0]){
							$Data = array("AgentID".$DeviceID => $row[0], "TriggerTime" => $row[1]);
							$ValueData[] = $Data;	
						}
					}	
				}
			}
		}
		
		$sql = "SELECT monitor_point FROM dtm_deviceinfo_multiparam_table WHERE device_id = '".$DeviceID."'";
		$result = mysqli_query($link,$sql);
		if($result){
			if(mysqli_num_rows($result)){
				while($row = mysqli_fetch_array($result)){
					$Field = array("title"=>$row[0],"valueField"=>"AgentID".$DeviceID);
					$FieldData[] = $Field;		
				}	
			}
		}
		
		if (!empty($FieldData) && !empty($ValueData)) {
			$jsonStr["Field"] = $FieldData;
			$jsonStr["Value"] = $ValueData;
			echo json_encode($jsonStr);
		} else {
			//当数据库中没有记录时返回一个空的json数据
			echo json_encode(array("Field"=>array(),"Value"=>array()));
		}
	}

	mysqli_free_result($result);
	mysqli_close($link);
	
	function unique_arr($array2D, $stkeep = false, $ndformat = true) {   
		// 判断是否保留一级数组键 (一级数组键可以为非数字)   
		if($stkeep) {
			$stArr = array_keys($array2D);   
		}
		
		// 判断是否保留二级数组键 (所有二级数组键必须相同)   
		if($ndformat) {
			$ndArr = array_keys(end($array2D));   
		}
		
		//降维,也可以用implode,将一维数组转换为用逗号连接的字符串   
		foreach ($array2D as $v) {   
			$v = join(",",$v);    
			$temp[] = $v;   
		}   
	   
		//去掉重复的字符串,也就是重复的一维数组   
		$temp = array_unique($temp);    
	   
		//再将拆开的数组重新组装   
		foreach ($temp as $k => $v) {   
			if ($stkeep) {
				$k = $stArr[$k];   
			}
			
			if ($ndformat) {   
				$tempArr = explode(",",$v);    
				foreach($tempArr as $ndkey => $ndval) {
					$output[$k][$ndArr[$ndkey]] = $ndval;   
				}
			} else {
				$output[$k] = explode(",",$v);    
			}
		}   
		return $output;   
	} 
?>