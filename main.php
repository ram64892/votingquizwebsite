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

$incorrectType = FALSE;
$tooLarge = FALSE;
$emptyTextAnswer = FALSE;
$maxFileSize = 10; // file size in MB
$uploadBasePath = "../team$teamId/submissions/";
$updateSubmissionsTable = FALSE;
$needToWaitMore = FALSE;
$missingVote = FALSE;

if (isset($_POST["qnnum"])) {
	$qnnum = htmlspecialchars($_POST["qnnum"]);
	if ($qnnum > 0) {
		if ($currstate == 'answering') {
			$sql = "SELECT submissiontype FROM questions WHERE id=$qnnum";
			$result = $conn->query($sql);
			$row = $result->fetch_assoc();
			$expectedType = $row['submissiontype'];
			
			if ($expectedType == 'text') {
				// ***************************************TODO****************
				// check if text answer has been submitted
				if (isset($_POST["givenAns"])) {
					// save the text to a text file
					$answerText = htmlspecialchars($_POST["givenAns"]);
				
					if ($answerText == "" || $answerText == NULL) {
						$emptyTextAnswer = TRUE;
					}
					else {
						// save the text to a file
						$newFileName = "Team$teamId-Qn$qnnum.txt";
						$uploadPath = $uploadBasePath . $newFileName;
						if (file_put_contents($uploadPath, $answerText)) {
							$updateSubmissionsTable = TRUE;

						}
						else {
							die("ERROR: Unable to write to text file $uploadPath for Qn $qnnum");
						}
					}
				}
				else {
					die("ERROR: QnNum is posted, but givenAns is missing for a question of type $expectedType for Qn $qnnum");
				}
			}
			else {
				if (isset($_FILES["fileToUpload"])) {
				// Get the file information
				$submissionName = $_FILES["fileToUpload"]["name"];
				$submissionType = $_FILES["fileToUpload"]["type"];
				$submissionSize = $_FILES["fileToUpload"]["size"];
				$submissionTmp = $_FILES["fileToUpload"]["tmp_name"];
				
					if ($expectedType == 'video') {
						// Check if the file is an allowed type
						$allowedTypes = [
							"video/mp4",
							"video/quicktime",
							"video/mpeg",
							"video/x-msvideo",
							"video/x-ms-wmv"
						];

					}
					elseif ($expectedType == 'audio') {
						// Check if the file is an allowed type
						$allowedTypes = [
							"audio/mpeg",
							"audio/wav",
							"audio/x-m4a"
						];

					}
					elseif ($expectedType == 'image') {
						// Check if the file is an allowed type
						$allowedTypes = [
							"image/jpeg",
							"image/png",
							"image/gif"
						];
					}
					else {
						die("ERROR: Invalid submissiontype in questions table ($expectedType) for question $qnnum");
					}
				}
				else {
					die("ERROR: QnNum is posted, but fileToUpload is missing for a question of type $expectedType for Qn $qnnum");
				}
				if (in_array($submissionType, $allowedTypes)) {
					// check the file size 
					$maxFileSizeInBytes = $maxFileSize * 1024 * 1024;
					if ($submissionSize <= $maxFileSizeInBytes) {						
						// upload the file
						$submissionExt = pathinfo($submissionName, PATHINFO_EXTENSION);
						$newFileName = "Team$teamId-Qn$qnnum.$submissionExt";
						$uploadPath = $uploadBasePath . $newFileName;
						if (move_uploaded_file($submissionTmp,$uploadPath)) {
							// update the sumissions table
							$updateSubmissionsTable = TRUE;
						}
						else {
							die("ERROR: Could not upload file. Error moving file to $uploadPath");
						}
					}
					else {
						$tooLarge = TRUE;
						// don't upload anything and let the question display again with the error message about file size
					}
				}
				else {
					$incorrectType = TRUE;
					// don't upload anything and let the question display again with the error message about file type
				}
				
			}
			if ($updateSubmissionsTable) {
				$sql = "INSERT INTO submissions (teamid, questionid, submissionurl) VALUES ($teamId, $qnnum, '$uploadPath')";
				if (mysqli_query($conn, $sql)) {
					// check if the team has reached the end of the questions
					if ($qnnum == $totalQns) {
						// set currq to 0 and update currstate to "waiting"
						$sql = "UPDATE teams SET currq=0,currstate='waiting' WHERE id=$teamId";
					}
					else {
						// update currq to next question number
						$qnnum++;
						$sql = "UPDATE teams SET currq=$qnnum WHERE id=$teamId";
					}
					if (mysqli_query($conn, $sql)) {
						// all good. Do nothing here as qnnum and state is already updated
					}
					else {
						die("ERROR: Could not update teams database for team $teamid to move to Qn $qnnum: " . $conn->error);
					}
				}
				else {
					die("ERROR: Could not update submissions table for qn $qnnum: " . $conn->error);
				}
				
			}
		}
		elseif ($currstate == 'voting') {
			# Process voting submission
			if (isset($_POST["voting"])) {
				/*********** TODO ***************/
				$voteTeamId = htmlspecialchars($_POST["voting"]);
				$sql = "UPDATE submissions SET numvotes=numvotes+1 WHERE questionid=$qnnum AND teamid=$voteTeamId";
				if (mysqli_query($conn, $sql)) {
					// check if the team has reached the end of the questions
					if ($qnnum == $totalQns) {
						// set currq to 0 and update currstate to "waiting"
						$sql = "UPDATE teams SET currq=0,currstate='completed' WHERE id=$teamId";
					}
					else {
						// update currq to next question number
						$qnnum++;
						$sql = "UPDATE teams SET currq=$qnnum WHERE id=$teamId";
					}
					if (mysqli_query($conn, $sql)) {
						// all good. Do nothing here as qnnum and state is already updated
					}
					else {
						die("ERROR: Could not update teams database for team $teamid to move to Qn $qnnum: " . $conn->error);
					}
				}
				else {
					die("ERROR: Could not increment numvotes in submissions table for team $voteTeamId for Question $qnnum: " . $conn->error);
				}
			}
			else {
				$missingVote = TRUE;
			}
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
		# echo "Currstate is notstart and startqn is set";
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
		# check if all the teams have finished submitting their answers
		$sql = "SELECT id FROM teams WHERE (currstate='notstart') OR (currstate='answering')";
		$result = $conn->query($sql);
		if ($result->num_rows > 0) {
			$needToWaitMore = TRUE;
		}
		else {
			# Set the state to voting and update currq to 1
			$sql = "UPDATE teams SET currq=1,currstate='voting' WHERE id=$teamId";
			if ($conn->query($sql) == TRUE) {
				#echo "Record updated successfully to set Currq to 1 and state to answering";
				$currstate="voting";
			}
			else {
				die("Error updating record to set Currq to 1 and state to voting: " . $conn->error);
			}
		}
	}
	else {
		die("ERROR: Invalid state - Startvoting is set but teams.currstate is $currstate (It should be WAITING)");
	}
}
else {
	#echo "No relevant post data";
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
$sql = "SELECT name, currq, currstate FROM teams WHERE id=$teamId";
$result = $conn->query($sql);
if ($result->num_rows == 1) {
	$row = $result->fetch_assoc();
	$teamName = $row["name"];
	$currq = $row["currq"];
	$currstate = $row["currstate"];
}
else {
	die("Error in getting team details");
}
?>

<div id="header">

	<h1 id="hdrteamname">Bingo A.I. Challenge<br />Team <?php echo "$teamName";?></h1>
<!--
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
-->
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
	<p>In today's quiz, your creativity will be put to the test.</p> 
	<p>This time, you will not be answering questions. Instead, you will be given 4 projects.</p>
	<p>For each project, you will use an A.I. tool to create content!</p>
	<p>Once you have completed your project, submit the creation on this website.</p>
	<p>The goal is to create content that impresses the other teams enough to vote for your content.</p>
	<p>You have a total of 45 minutes to complete all 4 projects. Budget your time wisely.</p>
	<p>Once all teams have completed their 4 projects, each team will then vote on everyone else's submissions.</p>
	<p><strong>The team with the most votes wins!</strong></p><br />
	<p>Click the button below to start.</p>

		<video src="../binary.mp4" autoplay muted loop>
		</video>
		<br>
		
		<form action="index.php" method="post" target="_self" id="qnanswerform" class="qnanswerform">
			<input type="hidden" name="startqn" value="0" />
			<input id="welcomebtn" type="submit" value="Let's Get Started!" id="submit" class="submit"/>
		</form>
	</div>
<?php
}
elseif ($currstate == "answering") {
	#echo "answering";
	$sql = "SELECT * FROM questions WHERE id=$currq";
	$result = $conn->query($sql);
	if ($result->num_rows == 1) {
		$row = $result->fetch_assoc();
		$qnid = $row["id"];
		$questionurl = $row["questionurl"];
		$title = $row["title"];
		$qnType = $row["submissiontype"];
	
	
		if ($incorrectType) { 
?> 
		<p id="qnincorrect">You have submitted an incorrect file type. The file submission is supposed to be <?echo "$qnType"?> but the file found is of type <?echo "$submissionType"?></p>
<?php
		}
		elseif ($tooLarge) {
?> 
		<p id="qnincorrect">You have submitted a file that is too large. The maximum file size is <?echo "$maxFileSize"?> MB</p>
<?php
		}
		elseif ($emptyTextAnswer) {
?> 
		<p id="qnincorrect">You have not submitted any text in the textbox - Please resubmit with your text answer</p>
<?php
		}
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
		
		<form id="qnanswerform" action="index.php" method="post" target="_self" class="qnanswerform" <?php if ($currstate != 'text') {?> enctype="multipart/form-data" <?php } ?>>
			<input type="hidden" name="qnnum" value=<?php echo "$qnid"; ?> />
			<p id="qnanswertxt">Submit your content here:</p> <br />
<?php
		if ($qnType == 'text') {
?>
			<!--<input id="qnanswerbox" type="textarea" name="givenAns" required />-->
			<textarea id="qnanswertextarea" name="givenAns" rows=20 required></textarea>
<?php
		}
		elseif ($qnType == 'video' || $qnType == 'audio' || $qnType == 'image') {
?>
			<input id="fileToUpload" type="file" name="fileToUpload">
<?php
		}
		else {
			die("ERROR: Invalid Question type ($qnType) for Question $qnid");
		}
?>
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
	if ($needToWaitMore) { 
?> 
		<p id="qnincorrect">Other teams have not completed their submissions yet. Please continue to wait.</p>
<?php
		}
?>
		<div id="welcomehdr">
		Congratulations team <?php echo $teamName ?>!
		</div>
		<div id="voteintrotxt">
			<p>You have completed your projects.</p>
			<p>The next step will be to view the submissions of all the other teams and vote for the ones that you like the best.</p>
			<p>We have to wait for all the teams to complete their submissions before starting the voting process.</p>
			<p>When voting, you will not know which team each submission belongs to.</p>
			<br />
			<p>Please wait here till you are given the instruction from the host to contine, then click the button below to start voting.</p>
			<br />
			
			<form action="index.php" method="post" target="_self" id="qnanswerform" class="qnanswerform">
				<input type="hidden" name="startvoting" value="0" />
				<input id="welcomebtn" type="submit" value="Let's Vote!!!" id="submit" class="submit"/>
			</form>
		</div>
<?php
}
elseif ($currstate == "voting") {
	// display submissions from all the other teams
	echo "voting";
	$sql = "SELECT * FROM questions WHERE id=$currq";
	$result = $conn->query($sql);
	if ($result->num_rows == 1) {
		$row = $result->fetch_assoc();
		$qnid = $row["id"];
		$questionurl = $row["questionurl"];
		$title = $row["title"];
		$qnType = $row["submissiontype"];
		
		if ($missingVote) { 
?> 
	<p id="qnincorrect">Please vote for one of the submissions before submitting.</p>
<?php
		}
?>
	<div>
		<h2 id="qntitle">Question <?php echo "$qnid - $title"; ?></h2>
<?php
		$subNum = 1;
		$subLabels = ["A","B","C"];
		// Get submissions from all teams except the current team for this specific question
		$sql = "SELECT * FROM submissions WHERE teamid!=$teamId AND questionid=$qnid";
		$result = $conn->query($sql);
		while($row = mysqli_fetch_array($result)) {
			$subUrl = $row["submissionurl"];
			$subTeam = $row["teamid"];
?>
		<div id="dispsubmission">
			<h3 id="submissionnum">Submission <?php echo $subNum; ?></h3>
<?php
			if ($qnType == 'image') {
?>
			<img id="submissionimg" src="<?php echo $subUrl; ?>" />
			<br />
<?php
			}
			elseif ($qnType == 'video') {
?>
			<video id="submissionvid" src="<?php echo $subUrl; ?>" controls>
			</video>
			<br />
<?php				
			}
			elseif ($qnType == 'audio') {
?>
			<audio id="submissionaud" src="<?php echo $subUrl; ?>" controls>
			</audio>
			<br />
<?php
			}
			elseif ($qnType == 'text') {
?>
			<pre id="submissiontxt">
<?php
				$subText = file_get_contents($subUrl);
				if ($subText) {
					echo "$subText\n";
				}
				else {
					die("ERROR: Unable to open text file $subURL of team $subTeam for question $qnid");
				}
?>
			</pre>
<?php
			}
?>
		</div>
<?php
			$subTeams[$subNum] = $subTeam;
			$subNum++;
		}
?>
		<div id="submissionvote">
			<form id="votingform" action="index.php" method="post" target="_self" class="votingform">
				<input type="hidden" name="qnnum" value=<?php echo "$qnid"; ?> />
				<p id="qnanswertxt">Vote for your favourite submission:</p> <br />
			
<?php
		for ($i = 1; $i < $subNum; $i++) {
?>
				<label id="votelabel">
				<input type="radio" name="voting" value="<?php echo $subTeams[$i]; ?>" required>
				Submission <?php echo $i; ?>
				</label>
<?php
		}
?>
				<input id="votesubmit" type="submit" value="Submit" class="votesubmit" />
			</form>
		</div>
<?php
	}
	else {
		die("Error in getting question details for $currq");
	}
	
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
	die("ERROR: Invalid state found in display section ($currstate)");
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
<!--
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
-->
</body>
</html>
