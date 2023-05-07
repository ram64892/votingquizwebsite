<?php
	require "dbconn.php";
	
	$sql = "DELETE FROM attempts";
	$result = $conn->query($sql);
	
	$sql = "UPDATE teams SET currq=0, completed=FALSE WHERE 1";
	$result = $conn->query($sql);
	
	echo "Databases have been reset to default";
?>
