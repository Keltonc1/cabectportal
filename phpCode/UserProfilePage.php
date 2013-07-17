<?php
	//USER PROFILE PAGE
	/*
		this page will display all of the user's information that they entered as part of their account
		also, it will display their total points
		
		in addition to their basic user information, the page will also have displayed a list of their projects.
		this project will need to incorporate pagination techniques if the user has a list greater than 10
		
		need to lookup how to do something like that
		
		for now, all that is needed will be the basic queries to get the user's information and display it
	*/
	
	//start a session to access session variables
	session_start();
	if(isset($_SESSION['Act_ID'])) { //if there is a user logged in, proceed
		$notLoggedIn = false;
		//include the database connection file and establish a connection
		include('/local/data/www/cabect/htdocs/backend/connection.php');		
		$conn = dbConnect();
		if(isset($conn)) {
			//since the user is logged in, we just need a simple query to get their information
			$sqlGetInformation = 'SELECT "Username","Point_Total","Email","Affiliation" FROM "USER" WHERE "Act_ID" = $1';
			//execute the query
			$infoQuery = pg_query_params($conn, $sqlGetInformation, array($_SESSION['Act_ID'])) or die("Could not execute query. ".pg_last_error());
			//store the results in another variable so it can be used for output
			$infoQueryResults = pg_fetch_row($infoQuery);
		} //end of is connected if
		else {
			//redirect them to the redirect page if there is a connection error
			header('Location: redirect.php?UserProfilePage.php');
		}
	} //end of is logged in if
	else {
		//flag variable to change what is display on the page
		$notLoggedIn = true;
	}
?>

<html>
	<title>Profile Page</title>
	<head></head>
	<body>
		<?php if($notLoggedIn) { ?>
		<div>
			<h1>User Not Logged In!</h1>
			<a href="UserLogIn.php">Sign in here</a>
		</div>
		<?php }
		else {
		?>
		<div>
			<h1><?php echo "$infoQueryResults[0]'";?>s Profile</h1>
		</div>
		<div>
			<h3><?php echo "Email: $infoQueryResults[2]";?></h3>
		</div>
		<div>
			<p><?php echo "Points: $infoQueryResults[1]";?></p>
			<p><?php echo "Affiliation: $infoQueryResults[3]";?></p>
		</div>
		<div>
			<p><a href="ProjectForm.php">Submit a Project</a></p>
		</div>
		<div>
			<br/><br/><p>Project List Will Go Here</p>
		</div>
		<?php
		}
		?>
	</body>
</html>