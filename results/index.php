<?php 

	require "../dbconn.php";
	
	$sql = "SELECT id,shorttitle FROM questions ORDER BY id ASC";
	$qnTitles = $conn->query($sql);
	$numQuestions = $qnTitles->num_rows;
	while($row = mysqli_fetch_array($qnTitles)) {
		$qnTitlesArr[$row['id']] = $row['shorttitle'];
	}
		
	
	#echo "Number of Questions is $numQuestions\n";
	
	$sql = "SELECT id,name,completed,currq FROM teams ORDER BY id ASC";
	$result = $conn->query($sql);
	$numTeams = $result->num_rows;
	while ($row = mysqli_fetch_array($result)) {
			$teamCurrQn[$row['id']] = $row['currq'];
			$teamNames[$row['id']] = $row['name'];
			$teamCompleted[$row['id']] = $row['completed'];
	}
	
	#echo "Number of teams is $numTeams\n";
	
	
	for ($i=1; $i <= $numTeams; $i++) {
			# Create a variable for cumulative time from the start for each team
			$cumTime[$i] = 0;
			# Create a variable to store the total number of attempts for each team for all 10 qns combined
			$totalNumAttempts[$i] = 0;
	}
	
	# initialise the 2D arrays used to store the attempts information with Null so that any unfilled item will default to null
	for ($i = 1; $i <= $numQuestions; $i++) {
		for ($j = 1; $j <= $numTeams; $j++) {
			$ansTimes[$i][$j] = "null";
			$ansCumTimes[$i][$j] = "null";
			$ansTimesByTeam[$j][$i] = "null";
			$numAttempts[$i][$j] = "null";
		}
	}
	
	# Get the attempts data and put it into a 2D array indexed by teamid
	# At the same time, get the info about the total number of attempts, and identify the team with the fastest question answer, longest question answer
	$currFastestTime = 9999;
	$currLongestTime = 0;
	
	for ($i = 1; $i <= $numTeams; $i++) {
		#echo "for loop team $i\n";
		for ($j = 1; $j <= $teamCurrQn[$i]; $j++) {
			#echo "for loop question $j\n";
			if ($j <= $numQuestions) {
				if ($j == $teamCurrQn[$i]) {
					$sql = "SELECT ROUND(TIMESTAMPDIFF(second, starttime, now())/60,2) AS timediff, numattempts FROM attempts WHERE questionid=$j AND teamid=$i";
				}
				else {
					$sql = "SELECT ROUND(TIMESTAMPDIFF(second, starttime, endtime)/60,2) AS timediff, numattempts FROM attempts WHERE questionid=$j AND teamid=$i";
				}
				$result = $conn->query($sql);
				if ($result->num_rows == 1) {
					$row = mysqli_fetch_array($result);
					$ansTimes[$j][$i] = $row["timediff"];
					$cumTime[$i] += $row["timediff"];
					$ansCumTimes[$j][$i] = $cumTime[$i];
					$ansTimesByTeam[$i][$j] = $row["timediff"];
					$numAttempts[$j][$i] = $row["numattempts"];
					
					# computations for most/least number of attempts
					$totalNumAttempts[$i] += $row["numattempts"];
					
					# computations for fastest question answer
					if ($row["timediff"] < $currFastestTime) {
						$currFastestTime = $row["timediff"];
						$fastestTimeWinner = array(array($i,$j,$row["timediff"]));
					}
					elseif ($row["timediff"] == $currFastestTime) {
						array_push($fastestTimeWinner, array($i,$j,$row["timediff"]));
					}
					
					# computations for longest question answer
					if ($row["timediff"] > $currLongestTime) {
						$currLongestTime = $row["timediff"];
						$longestTimeWinner = array(array($i,$j,$row["timediff"]));
					}
					elseif ($row["timediff"] == $currLongestTime) {
						array_push($longestTimeWinner, array($i,$j,$row["timediff"]));
					}
				}
				else {
					die ("Error getting the timediff result for Team $i and Question $j");
				}
			}
		}
	}
	
	#compute Quiz Winner
	$currFastestTime = 9999.0;
	for ($i = 1; $i <= $numTeams; $i++) {
		if ($cumTime[$i] > 0) {
			if ($cumTime[$i] < $currFastestTime) {
				$currFastestTime = $cumTime[$i];
				$quizWinner = array($i);
				echo "found team $i with time $currFastestTime";
			}
			elseif ($cumTime[$i] == $currFastestTime) {
				array_push($quizWinner, $i);
				echo "found team $i with equal time $currFastestTime";
			}
		}
	}
	
	#compute most total number of attempts
	$currMostTotalAttempts = 0;
	for ($i = 1; $i <= $numTeams; $i++) {
		if ($totalNumAttempts[$i] > $currMostTotalAttempts) {
			$currMostTotalAttempts = $totalNumAttempts[$i];
			$mostTotalAttempts = array($i);
		}
		elseif ($totalNumAttempts[$i] == $currMostTotalAttempts) {
			array_push($mostTotalAttempts, $i);
		}
	}

	$currLeastTotalAttempts = 9999;
	for ($i = 1; $i <= $numTeams; $i++) {
		if ($totalNumAttempts[$i] > 0) {
			if ($totalNumAttempts[$i] < $currLeastTotalAttempts) {
				$currLeastTotalAttempts = $totalNumAttempts[$i];
				$leastTotalAttempts = array($i);
			}
			elseif ($totalNumAttempts[$i] == $currLeastTotalAttempts) {
				array_push($leastTotalAttempts, $i);
			}
		}
	}

	#echo "Got the attempts\n";
	#print_r($ansTimes);
	#print_r($ansCumTimes);
?>


<html>
	<head>
	<!--Load the AJAX API-->
	<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
	<script type="text/javascript">
		// Load the Visualization API and the corechart package.
		google.charts.load('current', {'packages':['corechart']});
		
		// Set a callback to run when the Google Visualization API is loaded.
		google.charts.setOnLoadCallback(drawProgressLineChart);
		google.charts.setOnLoadCallback(drawAnswerTimesChart);
		google.charts.setOnLoadCallback(drawTeamQuestionsPercentageChart);
		google.charts.setOnLoadCallback(drawNumAttemptsChart);
<?php
	for ($i=1; $i <= $numTeams; $i++) {
?>
		google.charts.setOnLoadCallback(drawPercentageTimeTeam<?php echo "$i"; ?>Chart);
<?php 
	}
?>		
		// Callback functions that creates and populates a data table,
		// instantiates the charts, pass in the data and draw it.
		
		///////////////////////////////////////////////////////////////////////////////
		// drawProgressLineChart
		///////////////////////////////////////////////////////////////////////////////
		function drawProgressLineChart() {
		
			// Create the data table.
			var data = new google.visualization.DataTable();
			
			data.addColumn('string', 'Question');
<?php
	for ($i = 1; $i <= $numTeams; $i++) {
		$teamName = $teamNames[$i];
?>
			data.addColumn('number', <?php echo "'$teamName'"; ?>);
<?php
	}
?>
			data.addRows([
<?php
	for ($i = 1; $i <= $numQuestions; $i++) {
		$qnTitle = $qnTitlesArr[$i];
		
		#for the last row of the array, don't put the ",' after the "]"
		if ($i == $numQuestions) {
?>
			[<?php echo "'Q$i-$qnTitle', " . implode(",",$ansCumTimes[$i]); ?>]
<?php 
		}
		else {
?>
			[<?php echo "'Q$i-$qnTitle', " . implode(",",$ansCumTimes[$i]); ?>],
<?php
		}
?>

			
<?php
	}
?>
			]);

			// Set chart options
			var options = {/*title:'Teams Progress', */
//							backgroundColor: '#FBE4D5',
//							chartarea: {backgroundColor: 'yellow'}
						/*vaxis: {title: 'Minutes', titleTextStyle: {color: '#FF0000'} }*/ };	

			
			// Instantiate and draw our chart, passing in some options.
			var progressLineChart = new google.visualization.LineChart(document.getElementById('ProgressLineChart_div'));
			progressLineChart.draw(data, options);
		}
		
		
		
		
		///////////////////////////////////////////////////////////////////////////////
		// drawAnswerTimesChart
		///////////////////////////////////////////////////////////////////////////////
		
		function drawAnswerTimesChart() {
			// Create the data table.
			var data = new google.visualization.DataTable();
			
			data.addColumn('string', 'Question');
<?php
	for ($i = 1; $i <= $numTeams; $i++) {
		$teamName = $teamNames[$i];
?>
			data.addColumn('number', <?php echo "'$teamName'"; ?>);
<?php
	}
?>		
			data.addRows([
<?php
	for ($i = 1; $i <= $numQuestions; $i++) {
		$qnTitle = $qnTitlesArr[$i];
		
		#for the last row of the array, don't put the ",' after the "]"
		if ($i == $numQuestions) {
?>
			[<?php echo "'Q$i-$qnTitle', " . implode(",",$ansTimes[$i]); ?>]
<?php 
		}
		else {
?>
			[<?php echo "'Q$i-$qnTitle', " . implode(",",$ansTimes[$i]); ?>],
<?php
		}
?>

<?php
	}
?>

			]);
			// Set chart options
			var options = {vaxis: {title: 'Minutes', titleTextStyle: {color: '#FF0000'} } };
			
			// Instantiate and draw our chart, passing in some options.
			var answerTimesChart = new google.visualization.ColumnChart(document.getElementById('AnswerTimesChart_div'));
			answerTimesChart.draw(data, options);
		
		}



		///////////////////////////////////////////////////////////////////////////////
		// drawTeamQuestionsPercentageChart
		///////////////////////////////////////////////////////////////////////////////
		function drawTeamQuestionsPercentageChart() {
			
			// Create the data table.
			var data = new google.visualization.DataTable();
			
			data.addColumn('string', 'Team');
			
			<?php
	for ($i = 1; $i <= $numQuestions; $i++) {
		$qnTitle = $qnTitlesArr[$i];
?>
			data.addColumn('number', <?php echo "'$qnTitle'"; ?>);
<?php
	}
?>
			data.addColumn({ role: 'annotation' });
			data.addRows([
<?php
	for ($i = 1; $i <= $numTeams; $i++) {
		$teamName = $teamNames[$i];
		$totTeamTime = round($cumTime[$i],2);
		
		#for the last row of the array, don't put the ",' after the "]"
		if ($i == $numTeams) {
?>
			[<?php echo "'$teamName', " . implode(",",$ansTimesByTeam[$i]) . ", 'Total: $totTeamTime mins'"; ?>]
<?php 
		}
		else {
?>
			[<?php echo "'$teamName', " . implode(",",$ansTimesByTeam[$i]) . ", 'Total: $totTeamTime mins'"; ?>],
<?php
		}
?>

<?php
	}
?>
			]);
			
			// Set chart options
			var options = {/*title:'Time Distribution for each team', */
						isStacked: true,
						annotations: { alwaysOutside: true },
						vaxis: {title: 'Minutes', titleTextStyle: {color: '#FF0000'} } };	
			
			
			// Instantiate and draw our chart, passing in some options.
			var teamQuestionsPercentageChart = new google.visualization.ColumnChart(document.getElementById('TeamQuestionsPercentageChart_div'));
			teamQuestionsPercentageChart.draw(data, options);			
		}
		

		///////////////////////////////////////////////////////////////////////////////
		// drawNumAttemptsChart
		///////////////////////////////////////////////////////////////////////////////
		function drawNumAttemptsChart() {
			// Create the data table.
			var data = new google.visualization.DataTable();
			
			data.addColumn('string', 'Question');
<?php
	for ($i = 1; $i <= $numTeams; $i++) {
		$teamName = $teamNames[$i];
?>
			data.addColumn('number', <?php echo "'$teamName'"; ?>);
<?php
	}
?>		
			data.addRows([
<?php
	for ($i = 1; $i <= $numQuestions; $i++) {
		$qnTitle = $qnTitlesArr[$i];
		
		#for the last row of the array, don't put the ",' after the "]"
		if ($i == $numQuestions) {
?>
			[<?php echo "'Q$i-$qnTitle', " . implode(",",$numAttempts[$i]); ?>]
<?php 
		}
		else {
?>
			[<?php echo "'Q$i-$qnTitle', " . implode(",",$numAttempts[$i]); ?>],
<?php
		}
?>

<?php
	}
?>

			]);
			// Set chart options
			var options = {/*title:'Number of attempts for each question',*/
							vaxis:{gridlines:{multiple:1}}};
			
			// Instantiate and draw our chart, passing in some options.
			var numAttemptsChart = new google.visualization.ColumnChart(document.getElementById('NumAttemptsChart_div'));
			numAttemptsChart.draw(data, options);
			
		}
		
<?php
	for ($i=1; $i <= $numTeams; $i++) {
?>
		function drawPercentageTimeTeam<?php echo "$i"; ?>Chart() {
			
		}
<?php 
	}
?>		
		
	</script>
<link rel="stylesheet" href="../stylesheet.css">
<style>
#resultswinner ul {
	list-style-type: none;
	margin: 0;
	margin-top: 100px;
	padding: 0;
	overflow: hidden;
	display: inline-flex;
	width: 100%
}
#resultswinner li {
	float: left;
	/*border-right: 1px solid #bbb;
	border-bottom: 1px solid #bbb;
	border-left: 1px solid #bbb;*/
	text-align:center;
	width: 100%;
}
#resultswinteamname {
	color:orange; 
	opacity:0;
}

#resultstimes table {
	border: 1px solid rosybrown;
	border-collapse: collapse;
	color: white;
	padding: 10px;
    width: 75%;
    margin-top: 100px;
	margin-bottom: 300px;
    margin-left: auto;
    margin-right: auto;
}
#resultstimes th {
	border: 1px solid rosybrown;
	border-collapse: collapse;
	color: white;
	padding: 10px;
	font-family: papyrus;
	font-size: 125%;
	padding: 10px;
}
#resultstimes td {
	border: 1px solid rosybrown;
	border-collapse: collapse;
	color: white;
	padding: 10px;
	background-color: black;
	font-family: copperplate gothic;
	font-size: 125%;
}
#resultstimes tr {
	height: 80px;
}

#resultsawards table {
	color: white;
	padding: 10px;
    width: 100%;
    margin-top: 100px;
	margin-bottom: 300px;
}
#resultsawards tr {
	color: white;
	padding: 10px;
	font-family: papyrus;
	font-size: 125%;
	padding: 10px;
}
#resultsawards th {
	color: white;
	padding: 10px;
	background-color: black;
	font-family: Papyrus;
	font-size: 125%;
	/*opacity: 0;*/
}
#resultsawards td {
	color: orange;
	padding: 10px;
	background-color: black;
	font-family: copperplate gothic;
	font-size: 125%;
	/*opacity: 0;*/
}

#resultsawards ul {
	list-style-type: none;
	margin: 0;
	padding: 0;
	overflow: visible;
	display: block;
	width: 100%
}
#resultsawards li {
	float: left;
	/*border-right: 1px solid #bbb;
	border-bottom: 1px solid #bbb;
	border-left: 1px solid #bbb;*/
	text-align:center;
	width: 100%;
	padding: 20px;
}
#resultsawardsteamname {
	font-family: Algerian;
	font-size: 150%;
}
#resultsawardslongesttime {
	opacity: 0;
}
#resultsawardsmostattempts {
	opacity: 0;
}
#resultsawardsleastattempts {
	opacity: 0;
}
#resultsawardsfastesttime {
	opacity: 0;
}
</style>
<script> 
var winneropacity = 0;
var longestopacity = 0;
var mostattemptsopacity = 0;
var fastestopacity = 0;
var leastattemptsopacity = 0;

function changewinneropacity() {
	if (winneropacity > 1) {
		clearInterval(winnert);
	}
	var elem = document.getElementById("resultswinteamname");
	winneropacity += 0.01;
	elem.style.opacity = winneropacity;
}
function changelongestopacity() {
	if (longestopacity > 1) {
		clearInterval(longestt);
	}
	var elem = document.getElementById("resultsawardslongesttime");
	longestopacity += 0.01;
	elem.style.opacity = longestopacity;
}
function changemostattemptsopacity() {
	if (mostattemptsopacity > 1) {
		clearInterval(mostattemptst);
	}
	var elem = document.getElementById("resultsawardsmostattempts");
	mostattemptsopacity += 0.01;
	elem.style.opacity = mostattemptsopacity;
}
function changeleastattemptsopacity() {
	if (leastattemptsopacity > 1) {
		clearInterval(leastattemptst);
	}
	var elem = document.getElementById("resultsawardsleastattempts");
	leastattemptsopacity += 0.01;
	elem.style.opacity = leastattemptsopacity;
}
function changefastestopacity() {
	if (fastestopacity > 1) {
		clearInterval(fastestt);
	}
	var elem = document.getElementById("resultsawardsfastesttime");
	fastestopacity += 0.01;
	elem.style.opacity = fastestopacity;
}


function revealwinner() {
	var winnert = setInterval(changewinneropacity, 25);
}
function reveallongesttime() {
	var longestt = setInterval (changelongestopacity, 25);
}
function revealmostattempts() {
	var mostattemptst = setInterval (changemostattemptsopacity, 25);
}
function revealleastattempts() {
	var leastattemptst = setInterval (changeleastattemptsopacity, 25);
}
function revealfastesttime() {
	var fastestt = setInterval (changefastestopacity, 25);
}


</script>

</head>

<body id="resultsbody">
	<div id="resultswinner">
		<h1 onclick="revealwinner()">Winning Team</h1>
		<ul>
			<li id="resultswinimg">
				<img height="50%" src="trophy1-o.png">
			</li>
			<li id="resultswinteamname">
<?php 
	for($i = 0; $i < count($quizWinner); $i++) {
		$winnerId = $quizWinner[$i];
?>
				<p style="font-family: Algerian; font-size: 400%; margin-top:auto"><?php echo "$teamNames[$winnerId]";?></p>
<?php
	}
?>
				<p style="font-family: Calibri; font-size: 200%;">Total time: <?php echo "$cumTime[$winnerId] minutes"; ?></p>
			</li>
		</ul>
	</div>

	<div id="resultstimes">
		<h1>Completion Times</h1>
		<table>
			<tr>
				<th>Team Name</th>
				<th>Total Time (Minutes)</th>
			</tr>
<?php 
	for ($i = 1; $i <= $numTeams; $i++) {
?>
			<tr>
				<td><?echo "$teamNames[$i]"; ?></td>
				<td><?echo "$cumTime[$i]"; ?></td>
			</tr>
<?php
	}
?>
		</table>

	</div>

	<div id="resultsawards">
		<h1>Special Awards:</h1>
		<table>
			<tr>
				<th onclick="reveallongesttime()">Longest time to answer a question</th>
				<th onclick="revealmostattempts()">Largest total number of attempts</th>
				<th onclick="revealleastattempts()">Least total number of attempts</th>
				<th onclick="revealfastesttime()">Shortest time to answer a question</th>
			</tr>
			<tr>
			
<!-- ****** Display Longest time to answer question ***** -->
				<td>
					<ul>
<?php
	for ($i = 0; $i < count($longestTimeWinner); $i++) {
		$longestQn = $longestTimeWinner[$i];
		$longestQnTeam = $teamNames[$longestQn[0]];
		$longestQnQuestion = $longestQn[1];
		$longestQnTime = $longestQn[2];
?>
						<li id="resultsawardslongesttime">
							<span id="resultsawardsteamname"><?php echo "$longestQnTeam"; ?></span><br>
							Question: <?php echo "$longestQnQuestion"; ?><br>
							Duration: <?php echo "$longestQnTime"; ?>
						</li>
<?php
	}
?>
					</ul>
				</td>

<!-- ****** Display Most total number of attempts ***** -->
				<td>
					<ul>
<?php
	for ($i = 0; $i < count($mostTotalAttempts); $i++) {
		$mostTotalAttemptsTeamId = $mostTotalAttempts[$i];
		$mostTotalAttemptsNum = $totalNumAttempts[$mostTotalAttemptsTeamId];
		$mostTotalAttemptsTeamName = $teamNames[$mostTotalAttemptsTeamId];
?>
						<li id="resultsawardsmostattempts">
							<span id="resultsawardsteamname"><?php echo "$mostTotalAttemptsTeamName"; ?></span><br>
							Total Attempts: <?php echo "$mostTotalAttemptsNum"; ?><br>
						</li>
<?php
	}
?>
					</ul>
				</td>

<!-- ****** Display Least total number of attempts ***** -->
				<td>
					<ul>
<?php
	for ($i = 0; $i < count($leastTotalAttempts); $i++) {
		$leastTotalAttemptsTeamId = $leastTotalAttempts[$i];
		$leastTotalAttemptsNum = $totalNumAttempts[$leastTotalAttemptsTeamId];
		$leastTotalAttemptsTeamName = $teamNames[$leastTotalAttemptsTeamId];
?>
						<li id="resultsawardsleastattempts">
							<span id="resultsawardsteamname"><?php echo "$leastTotalAttemptsTeamName"; ?></span><br>
							Total Attempts: <?php echo "$leastTotalAttemptsNum"; ?><br>
						</li>
<?php
	}
?>
					</ul>
				</td>


<!-- ****** Display shortest time to answer question ***** -->
				<td>
					<ul>
<?php
	for ($i = 0; $i < count($fastestTimeWinner); $i++) {
		$shortestQn = $fastestTimeWinner[$i];
		$shortestQnTeam = $teamNames[$shortestQn[0]];
		$shortestQnQuestion = $shortestQn[1];
		$shortestQnTime = $shortestQn[2];
?>
						<li id="resultsawardsfastesttime">
							<span id="resultsawardsteamname"><?php echo "$shortestQnTeam"; ?></span><br>
							Question: <?php echo "$shortestQnQuestion"; ?><br>
							Duration: <?php echo "$shortestQnTime"; ?>
						</li>
<?php
	}
?>
					</ul>
				</td>





			</tr>
		</table>
	</div>
  
	<h1>Teams' Progress Chart</h1>
    <!--Div that will hold the Worm progress chart -->
    <div id="ProgressLineChart_div"></div>
	<br /><br />
	
	<h1>Time Taken For Each Question</h1>
	<!--Div that will hold the Bar chart showing answer times per question -->
	<div id="AnswerTimesChart_div"></div>
	<br /><br />
	
	<h1>Time Distribution for each Team</h1>
	<!--Div that will hold the Stacked Bar chart showing answer times distribution per team -->
	<div id="TeamQuestionsPercentageChart_div"></div>
	<br /><br />
	
	<h1>Number of Attempts for Each Question</h1>
	<!--Div that will hold the Bar chart showing number of attempts for each question per team -->
	<div id="NumAttemptsChart_div"></div>

  </body>
  
  
</html>