<?php
header('Content-type:text/html;charset=utf-8');
header('Content-type:text/json');

$DeviceID = $_GET['DeviceID'];
$DeviceIDList = explode('.', $DeviceID);

$link = @mysqli_connect('139.196.12.213', 'root', 'Shengjie123', 'pminfo') or
die('Could not connect:' . mysqli_connect_error());
@mysqli_set_charset($link, 'utf8') or
die('Error loading character set utf8:' . mysqli_error($link));

$FieldData = array();
$ValueData = array();
$TableData = array();
$jsonStr   = array();
$sql       = array();

foreach ($DeviceIDList as $Value) {
    $sql[] = '(
	SELECT
		t1.monitor_point,
		t2.device_id,
		t2.pmvalue,
		t2.triggertime
	FROM
		`dtm_pminfo_multiparam_table` AS t2
	INNER JOIN dtm_deviceinfo_multiparam_table AS t1 ON t1.device_id = t2.device_id
	WHERE
		t2.device_id = ' . $Value . '
	ORDER BY
		triggertime DESC
	LIMIT 100)';
}
$sqlStr = join('UNION ALL', $sql);
$sqlStr = $sqlStr . 'ORDER BY triggertime DESC LIMIT 100';

$result = mysqli_query($link, $sqlStr);
while ($row = mysqli_fetch_array($result)) {
    $ValueData[] = array('AgentID' . $row[1] => $row[2], 'TriggerTime' => $row[3]);
}
$ValueData = array_reverse($ValueData,false);
mysqli_free_result($result);

foreach ($DeviceIDList as $Value) {
    $sql = 'SELECT
	t1.monitor_point,
	t2.pmvalue,
	t2.co2,
	t2.tvoc,
	t2.so2,
	t2.noise,
	t2.ch2o,
	t2.pm_quality,
	t2.bg_color
FROM
	`dtm_pminfo_multiparam_table` AS t2
INNER JOIN dtm_deviceinfo_multiparam_table AS t1 
ON t1.device_id = t2.device_id
WHERE
	t2.device_id = ' . $Value . '
ORDER BY
	triggertime DESC
LIMIT 1';

    $result = mysqli_query($link, $sql);
    while ($row = mysqli_fetch_array($result)) {
        $FieldData[] = array('title' => $row[0], 'valueField' => 'AgentID' . $Value);
        $TableData[] = array("AddressName" => $row[0], "PmValue" => $row[1], "CO2" => $row[2], "TVOC" => $row[3], "SO2" => $row[4], "Noise" => $row[5], "CH2O" => $row[6], "PmQuality" => $row[7], "BgColor" => $row[8]);
    }
    mysqli_free_result($result);
}

$jsonStr['Field'] = $FieldData;
$jsonStr['Value'] = $ValueData;
$jsonStr['TableData'] = $TableData;

echo json_encode($jsonStr);

mysqli_close($link);