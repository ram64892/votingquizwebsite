<?php
//$servername = "sql309.epizy.com";
$servername = "127.0.0.1";

//$username = "epiz_30805852";
$username ="bingopuz_web";

//$password = "4p2KzPnhk5wojYg";
$password = "VMwar3!!VMwar3!!";

//$dbname = "epiz_30805852_2780";
$dbname = "bingopuz_jun23";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
  die("Connection to MySQL failed: " . $conn->connect_error);
}

?>