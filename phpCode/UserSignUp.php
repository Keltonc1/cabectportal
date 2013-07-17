<?php
//User sign-up form
/*
	Check if the email is valid
	Check if the username already exists
	Check if the email already exists
	Add the username, password, email, point total (0), and affiliation to the database
	Start the session with the user ID
*/
	//instantiate the necessary arrays for processing user input
	$required = array('username', 'email', 'password', 'affiliation');
	$expected = array('username', 'email', 'password', 'affiliation');
	$missing = array();
	
	//if the form has been submitted execute
	if(array_key_exists('submit', $_POST)) {	
		//include file to open database connection
		include('/local/data/www/cabect/htdocs/backend/connection.php');	
		$conn = dbConnect();
		if(isset($conn)) {
			foreach($_POST as $key=>$value) {
				$temp = is_array($value) ? $value : trim($value);
				if(empty($temp) && in_array($key, $required)) {
					//means the field is empty and it is required. add to missing array
					array_push($missing, $key);
				}
				elseif(in_array($key, $expected)) {
					//create variable with same name
					${$key} = $temp;
				}
			}
			$emailExists = false;
			$userValid = true;
			if(empty($missing))
			{
				//check if the email is valid
				if(!empty($email)) {
					//regex to ensure no illegal characters are present
					$checkEmail = '/^[^@]+@[^\s\r\n\'";,@%]+\.[a-zA-Z]+$/';
					//reject the email address if it doesn't match
					if (!preg_match($checkEmail, $email)) {
						array_push($missing, 'email');
						unset($email);
						$emailInvalid = true;
					}
				}	
			
				//check if the username already exists in the database
				$usernameCheck = 'SELECT "Username" FROM "USER" WHERE "Username" = $1';
				$userCheckResult = pg_query_params($conn, $usernameCheck, array($username));
				$result = pg_fetch_row($userCheckResult);
				if(empty($result)) {
					$userValid = true;
				}
				else {
					$userValid = false;
					unset($username);
					array_push($missing, 'username');
				}

				//check if the email already exists in the database
				if(isset($email)) {
					$emailCheck = 'SELECT "Email" FROM "USER" WHERE "Email" = $1';
					$emailCheckResult = pg_query_params($conn, $emailCheck, array($email));
					$emailResult = pg_fetch_row($emailCheckResult);
					if(empty($emailResult)) {
						$emailExists = false;
					}
					else {
						$emailExists = true;
						unset($email);
						array_push($missing, 'email');
					}
				}
				
				if(!$emailInvalid &&  empty($missing) && $userValid && !$emailExists) {		
					//create the sql for insertion
					$insertSQL = 'INSERT INTO "USER" ("Username", "Email", "Password", "Affiliation") VALUES ($1, $2, $3, $4)';
					//prepare the statement
					$insertQuery = pg_prepare($conn, 'insertionQuery', $insertSQL) or die(header("Location: redirect.php?location=UserSignUp.php"));
					//execute the statement
					$insertQuery = pg_execute($conn, 'insertionQuery', array($username, $email, $password, $affiliation)) or die(header("Location: redirect.php?location=UserSignUp.php"));
			
					//right now, the new user should have their account pushed into the database!
					if($insertQuery) {
						//the user now has an account
						//we need to select the Act_ID for the user to put the act_id as a session value
						//the sql select code
						$selectSQL = 'SELECT "Act_ID" FROM "USER" WHERE "Username" = $1';
						//prepare and execute the select query
						$selectQuery = pg_query_params($conn, $selectSQL, array($username)) or die(header("Location: redirect.php?location=UserSignUp.php"));
						$result = pg_fetch_row($selectQuery);
						session_start();
						ob_start();
						$_SESSION['Act_ID'] = $result[0];
						$_SESSION['location'] = "UserSignUp.php";
						header('Location: ProjectForm.php');
						exit;
					}
				}
			} // end of IS EMPTY if
		} //end IS CONNECTED if
		else {
			echo 'Could not connect!';
		}
	} //end ARRAY KEY EXISTS if
?>

<html>
	<title>Sign Up</title>
	<head>
		<style>
			.failure {
				color:red;
			}
		</style>
	</head>
	<body>
		<form name="signup" action="" method="post">
			<div>
				<p><label for="username">Username:</label>
				<input type="text" name="username" id="username" <?php if(isset($username) && !empty($missing)) { echo 'value="'.htmlentities($username).'"';} else { echo 'value=""'; } ?> /><?php if(!isset($username) && !empty($missing) && $userValid) { echo '<span class="failure">Required</span>';}?><?php if(!isset($username) && !empty($missing) && !$userValid) { echo '<span class="failure">Username is already in use</span>';} else { ; }?></p>
			</div>
			<div>
				<p><label for="password">Password:</label>
				<input type="password" name="password" id="password" <?php if(isset($password) && !empty($missing)) { echo 'value="'.htmlentities($password).'"';} else { echo 'value=""'; } ?> /><?php if(!isset($password) && !empty($missing)) { echo '<span class="failure">Required</span>';}?></p>
			</div>
			<div>
				<p><label for="email">Email:</label>
				<input type="text" name="email" id="email" <?php if(isset($email) && !empty($missing)) { echo 'value="'.htmlentities($email).'"';} else { echo 'value=""'; } ?> /><?php if(!isset($email) && !empty($missing) && !$emailInvalid && !$emailExists) { echo '<span class="failure">Required</span>';}?> <?php if(!isset($email) && !empty($missing) && $emailInvalid && !$emailExists) { echo '<span class="failure">Invalid email</span>'; } else {; } ?><?php if(!isset($email) && !empty($missing) && !$emailInvalid && $emailExists) { echo '<span class="failure">Email is already in use</span>';} else { ; }?></p>
			</div>
			<div>
				<p><label for="affiliation">Affiliation</label>
				<input type="text" name="affiliation" id="affiliation" <?php if(isset($affiliation) && !empty($missing)) { echo 'value="'.htmlentities($affiliation).'"';} else { echo 'value=""'; } ?> /><?php if(!isset($affiliation) && !empty($missing)) { echo '<span class="failure">Required</span>';}?></p>
			</div>
			<div>
				<input type="submit" id="submit" name="submit" value="Create Account" />
			</div>
		</form>
	</body>
<html>