<?php 

require "dbconn.php"; 

# Get total number of questions
$sql = "SELECT count(*) FROM questions";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$totalQns = $row['count(*)'];

# Get current state of team
$sql = "SELECT currstate FROM teams WHERE id=$teamId";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$currstate = $row['currstate'];

if (isset($_POST["qnnum"])) {
	$qnnum = htmlspecialchars($_POST["qnnum"]);
	if ($qnnum > 0) {
		if ($currstate == 'answering') {
			# Process question submission
		}
		elseif ($currstate == 'voting') {
			# Process voting submission
		}
		else {
			die("ERROR: Invalid state - Question number in POST is $qnnum but teams.currstate is $currstate (It should be ANSWERING or VOTING)");
		}
	}
	else {
		die("ERROR: Invalid question number in POST - $qnnum");
	}
}
elseif (isset($_POST["startqn"])) {
	if ($currstate == "notstart") {
		# Set the state to answering and update currq to 1
		echo "Currstate is notstart and startqn is set";
		$sql = "UPDATE teams SET currq=1,currstate='answering' WHERE id=$teamId";
		if ($conn->query($sql) == TRUE) {
			#echo "Record updated successfully to set Currq to 1 and state to answering";
			$currstate="answering";
		}
		else {
			die("Error updating record to set Currq to 1 and state to answering: " . $conn->error);
		}
	}
	else {
		die("ERROR: Invalid state - Startqn is set but teams.currstate is $currstate (It should be NOTSTART)");
	}
}
elseif (isset($_POST["startvoting"])) {
	if ($currstate == "waiting") {
		# Set the state to voting and update currq to 1
	}
	else {
		die("ERROR: Invalid state - Startvoting is set but teams.currstate is $currstate (It should be WAITING)");
	}
}
else {
	echo "No relevant post data";
}

#-------
#if (isset($_POST["qnnum"])) {
#	if ($qnnum > 0) {
#		$sql = "SELECT * FROM questions WHERE id=$qnnum";
#		$result = $conn->query($sql);
#		if ($result->num_rows == 1) {
#			$row = $result->fetch_assoc();
#			$answer = $row["answer"];
#			if (strcmp(strtolower($givenAns), strtolower($answer)) == 0) {
#				$correct = TRUE;
#				# In attempts table,
#				# 	update endtime of $qnnum, but only if hasn't already been set before (in case the user has pressed back on the browser button and answered it again). This is done using the coalesce function which returns the first non-NULL value.
#				#	increment numattempts
#				$sql = "UPDATE attempts SET numattempts=IF(ISNULL(endtime),numattempts+1,numattempts),endtime=COALESCE(endtime, now()) WHERE questionid=$qnnum AND teamid=$teamId";
#				if ($conn->query($sql) == TRUE) {
#					#echo "Set endtime on numattempts for $teamId and $qnnum";
#				}
#				else {
#					die("Unable to set attempts for team $teamId Question $qnnum: " . $conn->error);
#				}
#				if ($qnnum == $totalQns) {
#					$nextq = $qnnum+1;
#					$hooray = TRUE;
#					$sql = "UPDATE teams SET currq=$nextq,completed=true WHERE id=$teamId";
#				}
#				else {
#					$nextq = $qnnum+1;
#					# In attempts table
#					#	create a new entry for nextq (by default start time is set to current time and numattempts is set to 0)
#					$sql = "INSERT INTO attempts (teamid, questionid) VALUES ($teamId, $nextq) ON DUPLICATE KEY UPDATE numattempts=numattempts";
#					if ($conn->query($sql) == TRUE) {
#						#echo "Created new record for attempts by $teamId on Question $nextq";
#					}
#					else {
#						die("Error inserting into attempts table for team $teamId and Question $nextq: " . $conn->error);
#					}
#					
#					# update the teams table to update currq to the next question
#					$sql = "UPDATE teams SET currq=$nextq WHERE id=$teamId";
#				}
#				if ($conn->query($sql) == TRUE) {
#					#echo "Record updated successfully to set Currq to $nextq";
#				}
#				else {
#					die("Error updating record to set Currq to $nextq: " . $conn->error);
#				}
#			}
#			else {
#				# In attempts table
#				#	Increment numattempts
#				$sql = "UPDATE attempts SET numattempts=numattempts+1 WHERE questionid=$qnnum AND teamid=$teamId";
#				if ($conn->query($sql) == TRUE) {
#					#echo "Increment numattempts for $teamId and $qnnum done";
#				}
#				else {
#					die("Unable to increment numattempts for team $teamId Question $qnnum: " . $conn->error);
#				}
#				$correct = FALSE;
#			}
#		}
#		else {
#			die ("Error getting answer for $qnnum");
#		}
#	}
#	elseif ($qnnum == 0) {
#		$correct = TRUE;
#		$nextq = $qnnum+1;
#		$sql = "UPDATE teams SET currq=$nextq,completed=false WHERE id=$teamId";
#		if ($conn->query($sql) == TRUE) {
#			#echo "Record updated successfully to set Currq to $nextq";
#		}
#		else {
#			die("Error updating record to set Currq to $nextq: " . $conn->error);
#		}
#		# In attempts table
#		#	create a new entry for nextq (by default start time is set to current time and numattempts is set to 0)
#		$sql = "INSERT INTO attempts (teamid, questionid) VALUES ($teamId, $nextq) ON DUPLICATE KEY UPDATE numattempts=numattempts";
#		if ($conn->query($sql) == TRUE) {
#			#echo "Created new record for attempts by $teamId on Question $nextq";
#		}
#		else {
#			die("Error inserting into attempts table for team $teamId and Question $nextq: " . $conn->error);
#		}
#	}
#}
#else {
#	#echo "No qnnum in POST"
#	#Check if team has completed, then set the Hooray flag, otherwise just ignore and let it display the currq again
#	$sql = "SELECT completed FROM teams WHERE id=$teamId";
#	$result = $conn->query($sql);
#	$row = $result->fetch_assoc();
#	$hooray = $row['completed'];
#}
?>

<html>

<head>
<title>Bingo Puzzle Challenge</title>
<script src="https://code.jquery.com/jquery-3.5.1.js"></script>
<script>
$(document).ready(function(){
setInterval(function(){
	$("#status").load(window.location.href + " #status" );
}, 30000);
});
$(document).ready(function($) {
    $('#qnanswerform').on('submit', function(evt) {
        $('#qnanswersubmit').hide();
    });
});
</script>
<link rel="stylesheet" href="../stylesheet.css">
</head>

<body id="body">
<!--------- HEADER ---------------->
<?php 
$sql = "SELECT name, currq FROM teams WHERE id=$teamId";
$result = $conn->query($sql);
if ($result->num_rows == 1) {
	$row = $result->fetch_assoc();
	$teamName = $row["name"];
	$currq = $row["currq"];
}
else {
	die("Error in getting team details");
}
?>

<div id="header">

	<h1 id="hdrteamname">Bingo A.I. Challenge<br />Team <?php echo "$teamName";?></h1>

	<div class="hdrlinks">
		<p>Useful links</p>
		<ul>
			<li><a href="https://google.com" target="_blank">Google Search</a></li>
			<li><a href="https://maps.google.com" target="_blank">Google Maps</a></li>
			<li><a href="https://images.google.com" target="_blank">Google Image Search</a></li>
			<li><a href="https://support.google.com/assistant/answer/7554088?hl=en&co=GENIE.Platform%3DAndroid" target="_blank">Google Music Search</a></li>
			<li><a href="https://translate.google.com/?sl=auto&tl=en&op=translate" target="_blank">Google Translate</a></li>
			<li><a href="https://www.google.com/maps/@10.7834468,79.1336221,2a,75y,256.72h,97.89t/data=!3m6!1e1!3m4!1szZP9z5OwBT2NppmtH0p9og!2e0!7i13312!8i6656" target="_blank">Google Street View</a></li>
			<li><a href="https://archive.org/web/" target="_blank">Internet Wayback Machine</a></li>
			<li><a href="https://www.myfonts.com/WhatTheFont/" target="_blank">WhatTheFont</a></li>
			<li><a href="https://support.microsoft.com/en-us/windows/use-snipping-tool-to-capture-screenshots-00246869-1843-655f-f220-97299b865f6b" target="_blank">Take Screenshots</a></li>
		</ul>
		<br />
	</div>
</div>

<!--------- QUESTION ---------------->

<div id="question">


<?php

if ($currstate == "notstart") {
?>
	<div id="welcomehdr">
		Welcome team <?php echo "$teamId - $teamName"; ?>
	</div>
	<div id="welcometxt">
	<p>Please wait till you are given the instruction to start the quiz, then click the button below to start.</p>

		<video src="../binary.mp4" autoplay muted loop>
		</video>
		<br>
		
		<form action="index.php" method="post" target="_self" id="qnanswerform" class="qnanswerform">
			<input type="hidden" name="startqn" value="0" />
			<input id="welcomebtn" type="submit" value="Let's Start!!!" id="submit" class="submit"/>
		</form>
	</div>
<?php
}
elseif ($currstate == "answering") {
	echo "answering";
	$sql = "SELECT * FROM questions WHERE id=$currq";
	$result = $conn->query($sql);
	if ($result->num_rows == 1) {
		$row = $result->fetch_assoc();
		$qnid = $row["id"];
		$questionurl = $row["questionurl"];
		$title = $row["title"];
?>
	<div>
		<h2 id="qntitle">Question <?php echo "$qnid - $title"; ?></h2>
	
		<iframe src='<?php echo "$questionurl"; ?>' width="100%" scrolling='yes' allow="autoplay" style='overflow:scroll' id='qniframe'></iframe>
		
		<script>
			// Selecting the iframe element
			var frame = document.getElementById("qniframe");
			
			// Adjusting the iframe height onload event
			frame.onload = function()
			// function execute while load the iframe
			{
			// set the height of the iframe as 
			// the height of the iframe content
			frame.style.height = 
			frame.contentWindow.document.body.scrollHeight + 20 + 'px';
			
	
			// set the width of the iframe as the 
			// width of the iframe content
			//frame.style.width  = 
			//frame.contentWindow.document.body.scrollWidth+'px';
			}
        </script>

		<form id="qnanswerform" action="index.php" method="post" target="_self" class="qnanswerform" enctype="multipart/form-data">
			<input type="hidden" name="qnnum" value=<?php echo "$qnid"; ?> />
			<p id="qnanswertxt">Submit your content here:</p> <br />
			<!--<input id="qnanswerbox" type="text" name="givenAns" required />-->
			<input id="fileToUpload" type="file" name="fileToUpload">
			<input id="qnanswersubmit" type="submit" value="Submit" class="qnanswersubmit" />
		</form>
	</div>
<?php
		
	}
	else {
		die("Error in getting question details for $currq");
	}
}
elseif ($currstate == "waiting") {
	echo "waiting";
}
elseif ($currstate == "voting") {
	// display submissions from all the other teams
	echo "voting";
}
elseif ($currstate == "completed") {
?>
	<div id="hooray">
		<br /><br />
		<h2 id="hooraytxt">Congratulations!!! You have conquered AI!</h2>
		<br />
		<img id="hoorayimg" src="../hooray.gif" />
	</div>
<?php

}
else {
	die("ERROR: Invalid state found in display");
}
?>




<!--
----------
<?php
if ($hooray == TRUE) {
?>
	<div id="hooray">
		<br /><br />
		<h2 id="hooraytxt">Congratulations!!! You have conquered the challenge!</h2>
		<br />
		<img id="hoorayimg" src="../hooray.gif" />
	</div>

<?php
}
elseif ($currq == 0) {
?>
	<div id="welcomehdr">
		Welcome team <?php echo "$teamId - $teamName"; ?>
	</div>
	<div id="welcometxt">
	<p>Please wait till you are given the instruction to start the quiz, then click the button below to start.</p>

		<video src="../binary.mp4" autoplay muted loop>
		</video>
		<br>
		<audio src="../intromusic.m4a" autoplay loop controls>
		</audio>
		

		<form action="index.php" method="post" target="_self" id="qnanswerform" class="qnanswerform">
			<input type="hidden" name="qnnum" value="0" />
			<input id="welcomebtn" type="submit" value="Let's Start!!!" id="submit" class="submit"/>
		</form>
	</div>
<?php
}
else {
	$sql = "SELECT * FROM questions WHERE id=$currq";
	$result = $conn->query($sql);
	if ($result->num_rows == 1) {
		$row = $result->fetch_assoc();
		$qnid = $row["id"];
		$questionurl = $row["questionurl"];
		$title = $row["title"];		
?>
	<div>
		<h2 id="qntitle">Question <?php echo "$qnid - $title"; ?></h2>
<?php
		if ($correct == FALSE) {
?>
		<p id="qnincorrect">Incorrect! Please try again.</p>
<?php	
		}
?>
	
		<iframe src='<?php echo "$questionurl"; ?>' width="100%" scrolling='yes' allow="autoplay" style='overflow:scroll' id='qniframe'></iframe>
		
		<script>
			// Selecting the iframe element
			var frame = document.getElementById("qniframe");
			
			// Adjusting the iframe height onload event
			frame.onload = function()
			// function execute while load the iframe
			{
			// set the height of the iframe as 
			// the height of the iframe content
			frame.style.height = 
			frame.contentWindow.document.body.scrollHeight + 20 + 'px';
			
	
			// set the width of the iframe as the 
			// width of the iframe content
			//frame.style.width  = 
			//frame.contentWindow.document.body.scrollWidth+'px';
			}
        </script>

		<form id="qnanswerform" action="index.php" method="post" target="_self" class="qnanswerform">
			<input type="hidden" name="qnnum" value=<?php echo "$qnid"; ?> />
			<p id="qnanswertxt">Answer:</p> 
			<input id="qnanswerbox" type="text" name="givenAns" required />
			<input id="qnanswersubmit" type="submit" value="Submit" class="qnanswersubmit" />
		</form>
	</div>
<?php
		
	}
	else {
		die("Error in getting question details for $currq");
	}
}
?>
-->
	<br />
</div>

<!--------- STATUS ---------------->

<div id="status">

	<h3 id="statustitle"> Current team status:</h3>
	<table class="statustable">
		<tr id="statusfirstrow">
			<th id="statusfirstrowcell"/>
<?php
	for ($i = 1; $i <= $totalQns; $i++) {
?>
			<th id="statusfirstrowcell"><?php echo "Question $i"; ?></th>
<?php		
	}
?>
		</tr>
<?php
	# Get teams info
	$sql = "SELECT * FROM teams ORDER BY id ASC";
	$result = $conn->query($sql);
	while($row = mysqli_fetch_array($result)) {
		$teamCurrQn = $row["currq"];
		$teamName = $row["name"];
		$teamId = $row["id"];
		$completed = $row["completed"];
?>
		<tr>
			<th id="statusteamnamecell"><?php echo "$teamName   "; ?></th>
<?php
		if ($completed) {
?>
			<td id="statuscompletedcell" colspan="<?php echo "$totalQns"; ?>" align="center"><?php echo "-- Team $teamName - COMPLETED!!! --"; ?></td>
<?php
		}
		elseif ($teamCurrQn > 0) {
			#for ($i = 1; $i <= $teamCurrQn; $i++) {
?>

			<td colspan="<?php echo "$teamCurrQn"; ?>" align="right"><?php echo "-- Qn $teamCurrQn --"; ?></td>
<?php
			#}
		}
?>
		</tr>
<?php
	}
?>

	</table>
</div>

</body>
</html>
