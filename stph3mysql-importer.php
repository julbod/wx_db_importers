<?php
// This script updates the viu hydromet mysql data base tables for the just steph 3
require 'config.php';
require 'functions.php';

$tbl = "steph3";
$maxRows = -12;
$numToClean = 6; 
$url = "https://datagarrison.com/users/300234010412670/300234010497280/temp/300234010497280_live.txt";
$data = array_slice(file($url), $maxRows);
$fields = "DateTime, Lysimeter, Rain, Solar_Rad, Snow_Depth, Current, Air_Temp, RH, Wind_Speed, Gust_Speed, Wind_Dir, Backup_Batt";

$conn = mysqli_connect(MYSQLHOST, MYSQLUSER, MYSQLPASS, MYSQLDB);

if (mysqli_connect_errno()) {
    echo "Failed to connect to MySQL: " . mysqli_connect_error();
}

if (filter_var($url, FILTER_VALIDATE_URL) ==FALSE) {
    echo("$url is not a valid URL");
}

# loop through each line in the $data array number of lines can be controlled by the max rows param
foreach ($data as $line) {
        # remove new line character from line
        $line = str_replace("\r\n", "", $line);

        # sep by tabs
        $datArray = explode("\t",$line);

        # format datetime to mysql time format, time is PST
        $datArray[0] = date("Y-m-d H:i:s", strtotime($datArray[0])); 

        $datString = implode("','", $datArray);

        // use the first entry of the linearray array to find the appropriate table. 
         $query = "insert ignore into `raw_$tbl` ($fields) values('$datString');";

		if (!mysqli_query($conn, $query)) {
			exit("Insert Query Error description: " . mysqli_error($conn));
		}
	}

// Then update clean table //

// get rows from mysql
$rawRows = getMySQLRows($conn, "raw_$tbl", $numToClean);

$lineNum = 0;
foreach ($rawRows as $line) {
    $curDateTime = $line["DateTime"];
	$curWatYr = wtr_yr($curDateTime, 10); // calc wat yr
	
	$cleanRow = array(
		"DateTime" => $line["DateTime"],
		"WatYr" =>  $curWatYr,
		"Air_Temp" => $line["Air_Temp"],
		"Rh" => $line["RH"],
		"Wind_Speed" => $line["Wind_Speed"],
		"Wind_Dir" => $line["Wind_Dir"],
		"Pk_Wind_Speed" => $line["Gust_Speed"],
		"PP_Tipper" => $line["Rain"],
		"Snow_Depth" => -$line["Snow_Depth"] + 234.91, // as of 2021-10 logger reporting positive distance to snow, 234.91 is eyeballed off the summer bare ground depth 
		"Lysimeter" => $line['Lysimeter'],
		"Solar_Rad" => $line["Solar_Rad"],
		"Batt" => $line["Backup_Batt"]
	);

	if (count($cleanRow) > 0) {
		$fields = implode(", ", array_keys($cleanRow));
		$values = implode("','", array_values($cleanRow));
	}

    //$query = "UPDATE `clean_$tbl` SET WatYr = $curWatYr WHERE DateTime = '$curDateTime'";
    $query = "INSERT IGNORE into `clean_$tbl` ($fields) values('$values')";

    if (!mysqli_query($conn, $query)) {
        exit("Insert Query Error description: " . mysqli_error($conn));
    }
}

mysqli_close($conn);


?>