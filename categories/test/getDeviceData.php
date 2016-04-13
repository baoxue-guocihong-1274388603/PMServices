<?php
	header('Content-type:text/html;charset=utf-8');
	header('Content-type:text/json');
	
	$OperationType = $_GET["OperationType"];

	$link = @mysqli_connect ("139.196.12.213","root","Shengjie123","pminfo") or 
		die("Could not connect:".mysqli_connect_error());
	@mysqli_set_charset($link,"utf8") or 
		die("Error loading character set utf8:".mysqli_error($link));
	
	$jsonStr = array();
	
	if ($OperationType === "AddDevice") {
		$DeviceID = $_GET["DeviceID"];
		$MonitorPoint = urldecode($_GET["MonitorPoint"]);
		
		//$MonitorPoint = iconv("gbk","utf-8",$MonitorPoint);  
		
		$sql = "INSERT INTO dtm_deviceinfo_test_table VALUES('".$DeviceID."','".$MonitorPoint."')";
		$result = mysqli_query($link,$sql);
		
		if ($result) {
			$jsonStr["status"] = "添加成功";
		} else {
			$jsonStr["status"] = "设备已添加,不能重复添加";
		}
	} else if ($OperationType === "DeleteDevice") {
		$DeviceID = $_GET["DeviceID"];
		
		$sql = "DELETE FROM dtm_deviceinfo_test_table WHERE device_id = '".$DeviceID."'";
		$result = mysqli_query($link,$sql);
		
		if ($result) {
			$jsonStr["status"] = "删除成功";
		} else {
			$jsonStr["status"] = "删除失败";
		}
	} else {
		//什么都不做
	}
	
	$DeviceData = array();
	$sql = "SELECT * FROM dtm_deviceinfo_test_table ORDER BY device_id ASC";
	$result = mysqli_query($link,$sql);
	while($row = mysqli_fetch_array($result)){
		$DeviceData[] = array("device_id"=>$row[0],"monitor_point"=>$row[1]);
	}
	
	$jsonStr["value"] = $DeviceData;
	echo json_encode($jsonStr);
	mysqli_free_result($result);
	mysqli_close($link);
?>