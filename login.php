<?php
	header('Content-type:application/text;charset=utf-8');
	
	$UserName = $_GET['UserName'];  
	$Password = $_GET['Password'];
	
	$link = @mysqli_connect ("139.196.12.213","root","Shengjie123","pminfo") or 
		die("Could not connect:".mysqli_connect_error());
	@mysqli_set_charset($link,"utf8") or 
		die("Error loading character set utf8:".mysqli_error($link));
		
	$sql = "SELECT passwd,url FROM dtm_userinfo_table WHERE user_name = '".$UserName."'";
	if ($result = mysqli_query($link,$sql)) {
		$row = mysqli_fetch_row($result);
		if (empty($row)) {
			echo "用户名不存在";//用户名不存在
		} else {
			if ($Password === $row[0]) {
				echo $row[1];
			} else {
				echo "密码不正确";//密码不正确
			}
		}
	} else {
		echo "数据库错误";
	}
	
	mysqli_free_result($result);
	mysqli_close($link);
?>