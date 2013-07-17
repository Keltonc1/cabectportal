<?php
	session_start();
	//the user sign-in page
	/*
		this page needs to check if the username is valid
		then it needs to check if the password is valid
		if the combination of password and username given is valid, the user is logged in and a session is created
		
		when it is made, the user will be redirected to his homepage
		
		if the user is already signed in, then there will need to be an option to sign out which will destroy the session
	*/
	
	//the signing in process
	//the signin button will not be displayed if there is a user logged in, so the log in form cannot be submitted
	if(array_key_exists('signin', $_POST)) {
		//all the fields are required
		$required = array('username', 'password');
		$expected = array('username', 'password');
		$missing = array();
	
		//check the input
		foreach($_POST as $key=>$value) {
			$temp = is_array($value) ? $value : trim($value);
			if(empty($temp) && in_array($key, $required)) {
				array_push($missing, $key);
			}
			elseif(in_array($key, $expected)) {
				${$key} = $temp;
			}
		}
	
		//if all the fields are filled in, move on
		if(empty($missing)) {
			//include the code to get a connection to the database
			include('/local/data/www/cabect/htdocs/backend/connection.php');
			$conn = dbConnect();
			if(isset($conn)) {
				//the database connection was successful if in here
				//now we need to query the database
			
				//this query will get the username of the user's account if the username they entered is correct
				$usernameSQL = 'SELECT "Username" FROM "USER" WHERE "Username" = $1';
				$usernameQ = pg_query_params($conn, $usernameSQL, array($username)) or die("Location: redirect.php?location=UserLogIn.php");
				$usernameResult = pg_fetch_row($usernameQ);
				if(!empty($usernameResult)) {
					//if it has made it this far, the username is entered correctly
					//query to match the password
					$passwordSQL = 'SELECT "Password" FROM "USER" WHERE "Password" = $1 AND "Username" = $2';
					$passwordQ = pg_query_params($conn, $passwordSQL, array($password, $usernameResult[0])) or die("Location: redirect.php?location=UserLogIn.php");
					$passwordResult = pg_fetch_row($passwordQ);
					if(!empty($passwordResult)) {
						//if it makes it here, the username and password combination are most likely correct
						//next, we should get the act_id of the user so that it can be stored for the logged in session
						//if the query returns a valid result, the username and password combination is correct
						$idSQL = 'SELECT "Act_ID" FROM "USER" WHERE "Username" = $1 AND "Password" = $2';
						$idQ = pg_query_params($conn, $idSQL, array($usernameResult[0], $passwordResult[0])) or die("Location: redirect.php?location=UserLogIn.php");
						$idResult = pg_fetch_row($idQ);
						if(!empty($idResult)) {
							//the user's information combination is correct, will now be logged into the system. VICTORY!
							//start the session, start the overflow buffer so the logout process can happen
							session_start();
							ob_start();
							$_SESSION['Act_ID'] = $idResult[0];
							$noLog = false;
							//the user is now officially signed in, redirect them to their profile
							header('Location: UserProfilePage.php');
						} //end idResult if
						else {
							$noLog = true;
						}
					} //end passwordResult if
					else {
						$noLog = true;
					}
				} //end usernameResult if
				else {
					$noLog = true;
				}
			} //end isset($conn) if	
			else {
				header("Location: redirect.php?location=UserSignIn.php");
				exit;
			}
		} //end empty($missing) if
	} //end in array signin if
	
	//manage the signing out process
	//a signout button will be displayed if there is a user logged in
	elseif(array_key_exists('signout', $_POST)) {
		//we need to destroy the session and unset any cookies and then flush the buffer
	    unset($_SESSION['Act_ID']); //unset the session variables
		if(isset($_COOKIE[session_name()])) { //set the cookie for this session to be expired
			setcookie(session_name(), '', time() - 86400, '/');
		}
		ob_end_flush(); //flush the buffer
		$_SESSION = array();
		session_destroy(); //destroy the session
	}
?>


<html>
	<?php
	if(!isset($_SESSION['Act_ID'])) {
	?>
	<title>Sign In</title>
	<?php
	}
	else {
	?>
	<title>Sign Out</title>
	<?php
	}
	?>
	<head>
		<style>
			.failure {
				color:red;
			}
			.container {
				width:250px;
			}
		</style>
	</head>
	<body>
		<?php
		if(!isset($_SESSION['Act_ID'])) {
		?>
		<form name="signin" action="" method="post">
			<div class="container">
				<legend><h3>Sign In</h3></legend>
				<hr />
				<?php
					if(isset($noLog) && $noLog) {
						echo "
				<div>
					<p class='failure'>The username and password combination you entered is incorrect.</p>
				</div>";
					}
				?>
				<div>
					<p><label for="username">Username:</label></p>
					<p><input type="text" id="username" name="username" <?php if(isset($username) && !empty($missing)) { echo "value=\"".htmlentities($username)."\""; }?> /> </p> <?php if(!isset($username) && !empty($missing)) { echo "<p class='failure'>Required</p>"; } ?>
				</div>
				<div>
					<p><label for="pasword">Password:</label></p>
					<p><input type="password" id="password" name="password"  <?php if(isset($password) && !empty($missing)) { echo "value=\"".htmlentities($password)."\""; }?> /></p> <?php if(!isset($password) && !empty($missing)) { echo "<p class='failure'>Required</p>"; } ?>
				</div>
				<div>
					<p><input type="submit" name="signin" id="signin" value="Sign In"/></p>
				</div>
			</div>
		</form>
		<?php
		}
		else {
		?>
		<form name="signout" action="" method="post">
			<div class="container">
				<legend><h3>Sign Out</h3></legend>
				<hr />
				<div>
					<p><input type="submit" name="signout" id="signout" value="Sign Out"/></p>
				</div>
			</div>
		</form>
		<?php
		}
		?>
	</body>
<html>