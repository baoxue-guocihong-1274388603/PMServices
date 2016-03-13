<?php
	header('Content-type:text/html;charset=utf-8');
	header('Content-type:text/json');
	
	$DeviceID = $_GET["DeviceID"];
	$DeviceID = str_replace(".",",",$DeviceID);
	$DeviceID = "(" . $DeviceID . ")";
	
	$link = @mysqli_connect ("139.196.12.213","root","Shengjie123","pminfo") or 
		die("Could not connect:".mysqli_connect_error());
	@mysqli_set_charset($link,"utf8") or 
		die("Error loading character set utf8:".mysqli_error($link));
	
	$FieldData = array();
	$ValueData = array();
	$TableData = array();
	
	$sql = "SELECT device_id,`pmvalue`,triggertime FROM dtm_pminfo_multiparam_table WHERE device_id in ".$DeviceID." ORDER BY triggertime DESC LIMIT 0,100";
	$result = mysqli_query($link,$sql);
	while($row = mysqli_fetch_array($result)){
		$res = mysqli_query($link,"SELECT monitor_point FROM dtm_deviceinfo_multiparam_table WHERE device_id = '".$row[0]."'");
		$r = mysqli_fetch_array($res);

		$Field = array("title"=>$r[0],"valueField"=>"AgentID".$row[0]);
		$Data = array("AgentID".$row[0] => $row[1], "TriggerTime" => $row[2]);

		$FieldData[] = $Field;
		$ValueData[] = $Data;
	}

	$sql = "SELECT * FROM (SELECT * FROM dtm_pminfo_multiparam_table WHERE device_id in ".$DeviceID." ORDER BY triggertime desc) pcc GROUP BY device_id";
	$result = mysqli_query($link,$sql);
	while($row = mysqli_fetch_array($result)){
		$res = mysqli_query($link,"SELECT monitor_point FROM dtm_deviceinfo_multiparam_table WHERE device_id = '".$row[0]."'");
		$r = mysqli_fetch_array($res);

		$TableData[] = array("AddressName"=>$r[0],"PmValue"=>$row[1],"CO2"=>$row[2],"TVOC"=>$row[3],"SO2"=>$row[4],"PmQuality"=>$row[6],"BgColor"=>$row[7]);
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
		
		$jsonStr["TableData"] = $TableData;
		
		echo json_encode($jsonStr);
	} else {
		//当数据库中没有记录时返回一个空的json数据
		echo json_encode(array("Field"=>array(),"Value"=>array(),"TableData"=>array()));
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