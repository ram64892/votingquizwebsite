<?php
	require "dbconn.php";
	
	$sql = "DELETE FROM submissions";
	$result = $conn->query($sql);
	
	$sql = "UPDATE teams SET currq=0, currstate='notstart' WHERE 1";
	$result = $conn->query($sql);
	
	$list = glob("*/submissions/*");

	foreach($list as $file) {		
		if (unlink ($file)) {
			echo "Deleted File: $file<br />";
		}
		else {
			echo "Could not delete File: $file<br />";
		}
	}
	echo "Databases have been reset to default";
?>
