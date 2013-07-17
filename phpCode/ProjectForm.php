<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML
1.0 Transitional//EN" "http://www.w3.org/
TR/xhtml1/DTD/xhtml1-transitional.dtd>

<?php
	if(array_key_exists('submit', $_POST)) {
		session_start(); //start the session
		/* *********************POINTS CONSTANTS********************* */
			$POINTS_FOR_CITATION = 10;
			$POINTS_FOR_PARENT = 5;
			$POINTS_FOR_PROJECT = 100;
		/* ********************************************************** */
		$user = $_SESSION['Act_ID']; //store the current user's Act_ID as a local $user variable that can be used in the script
		
		//arrays for input processing
		$required = array('name', 'description', 'target');
		$expected = array('name', 'link', 'parents', 'description', 'funding', 'target');
		$missing = array();
		
		echo "$target";
		
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
		
		//if the array 'missing' is empty, then the form is filled out correctly with all the required fields
		//we can try to submit the query
		if(empty($missing)) {
			//the database connection file
			include('/local/data/www/cabect/htdocs/backend/connection.php');			
			$conn = dbConnect();
			if(isset($conn)) { //if the connection went through, advance
				//check if there are any adaptations cited. if there are adaptations cited, then we need to explode the array
				//in case there are many listed.
				if(!empty($parents)) { //if there are adaptations cited
					$parents2 = $parents; //this second parents array will enable the user input to be output to the form correctly without being in all caps
					$parents = trim($parents); //trim any beginning and ending whitespace
					$parentArray = explode(', ', $parents); //this explodes the form field into an array based on comma delimiters
					$parentLength = count($parentArray); //length of the array
					
					//convert each Project_Name in the array to all uppercase to facilitate searching and comparing
					for($i = 0; $i < $parentLength; $i++) {
						$parentArray[$i] = strtoupper($parentArray[$i]);
					}
					
					//now we need to see if the projects listed are real
					//loop through the parents array and query the database to see if that project is already there. we want it to be, because it is a citation
					//the sql code that will be used for the selects
					$sql = 'SELECT "Project_Caps" FROM "PROJECT" WHERE "Project_Caps" = $1';
					
					//flag variable to see if all the adaptation checks are passed
					$adaptValid = true;
					
					for($i = 0; $i < $parentLength; $i++) {
						//prepare and execute the selection query
						$result = pg_query_params($conn, $sql, array($parentArray[$i])) or die(header("Location: redirect.php?location=ProjectForm.php"));
						$row = pg_fetch_row($result);
						if(empty($row)) { //if the query does not have any results returned, the project listed as a citation does not exist and is invalid
							$adaptValid = false; 
						}
					}
					
					//adaptValid will be false if there are invalid adaptations listed
					if(!$adaptValid) {
						$str = 'adaptIsFalse'; //create a dummy string
						array_push($missing, $str); //push onto missing array to stop processing the input
					}
					
					if($adaptValid && empty($missing)) { //if the citations listed exist and the input fields that are required are filled out
						
						//convert the current project's name into all uppercase
						$name1 = strtoupper($name);
						
						//if it makes it here, the projects cited exist in the database
						//insert the project into the database
						//base insert project query
						$sql = 'INSERT INTO "PROJECT" ("Project_Name", "Project_Description", "Link", "Funding", "Project_Caps", "Targeted_Audience")
								VALUES ($1, $2, $3, $4, $5, $6)';
						$result = pg_prepare($conn, "baseInsertionQuery", $sql) or die(header("Location: redirect.php?location=ProjectForm.php"));
						$result = pg_execute($conn, "baseInsertionQuery", array($name, $description, $link, $funding, $name1, $target)) or die(header("Location: redirect.php?location=ProjectForm.php"));
													
						//the project has been submitted successfully
						//since there are adaptations, we need to update the adaptations table in the database correspondingly
						//sql to insert the parent project's ID and the child project's (current project) ID into the adaptations table
						$sql = 'INSERT INTO "ADAPTATIONS" ("Parent_ID", "Child_ID")
								VALUES ($1, $2)';
						//sql to get the ID of the parent projects listed
						$sqlGetId = 'SELECT "Project_ID" FROM "PROJECT" WHERE "Project_Caps" = $1';
					
						//query to get the ID of the user's project
						$sqlGetChildId = "SELECT \"Project_ID\" FROM \"PROJECT\" WHERE \"Project_Caps\" = $1";
						
						//query to add points to the Parent
						$sqlGetParentPoints = 'SELECT "Point_Total" FROM "USER" AS "U" WHERE "U"."Act_ID" IN( SELECT "Act_ID" FROM "OWNS_A" WHERE "Project_ID" = $1)';
						
						//query to update points of the Parent
						$sqlGetParentAccountId = 'SELECT "Act_ID" FROM "OWNS_A" WHERE "Project_ID" = $1';
						$sqlUpdateParentPoints = 'UPDATE "USER" SET "Point_Total" = $1 WHERE "Act_ID" = $2';
						
						//prepare and execute the query so we can get the ID
						$childID = pg_query_params($conn, $sqlGetChildId, array($name1)) or die ("Could not prepare the query");
						$childIDResult = pg_fetch_row($childID);
						
						//now, we need to get the IDs of the parent projects to update the ADAPTATIONS table
					
						for($i = 0; $i < $parentLength; $i++) {
							//this code block queries the database to get the Project_IDs of any associated parent projects and stores them in parent project
							$parentProject = pg_query_params($conn, $sqlGetId, array($parentArray[$i])) or die (header("Location: redirect.php?location=ProjectForm.php"));
							$parentProjectID = pg_fetch_row($parentProject);
							
							//this will add the child and parent IDs into the ADAPTATIONS table
							//this query will add backlinks to child projects and parent projects
							//this query will have a variable name to prevent errors from preparing the same query
							$adaptInsert = pg_prepare($conn, "adaptQuery".$i, $sql) or die(header("Location: redirect.php?location=ProjectForm.php"));
							$adaptInsert = pg_execute($conn, "adaptQuery".$i, array($parentProjectID[0], $childIDResult[0])) or die (header("Location: redirect.php?location=ProjectForm.php"));
							
							//now we need to get the Act_ID of the parent project owners
							//use the sql
							$parentQuery = pg_query_params($conn, $sqlGetParentAccountId, array($parentProjectID[0]));
							$parentQResult = pg_fetch_row($parentQuery);
							
							//Now we must give points to all the parent projects
							$retrievePoints = pg_query_params($conn, $sqlGetParentPoints, array($parentProjectID[0])) or die(header("Location: redirect.php?location=ProjectForm.php"));
							$retrievePointsArray = pg_fetch_row($retrievePoints);
							
							//point calculations to correctly update the point totals for the owners of the citations
							$currentParentPoints = $retrievePointsArray[0];
							$currentParentPoints += ($POINTS_FOR_PARENT);
							
							//queries to update the point totals
							$updatePoints = pg_prepare($conn, "updatePointQuery".$i, $sqlUpdateParentPoints) or die(header("Location: redirect.php?location=ProjectForm.php"));
							$updatePoints = pg_execute($conn, "updatePointQuery".$i, array($currentParentPoints, $parentQResult[0]));
						}
					
						//now, we need to update the user's OWNS_A table to represent the addition of this project
						//sql that will insert the id of the current project next to the id of the currently-logged in user
						$ownerSQL = 'INSERT INTO "OWNS_A" ("Act_ID", "Project_ID") VALUES ($1, $2)';
						$userQ = pg_prepare($conn, "insertOwnerQuery", $ownerSQL) or die(header("Location: redirect.php?location=ProjectForm.php"));
						$userQ = pg_execute($conn, "insertOwnerQuery", array($user, $childIDResult[0])) or die(header("Location: redirect.php?location=ProjectForm.php"));
						
						//We must now award points for the project submission as well as the number of adaptations they have listed.
						$sqlGetPoints = 'SELECT "Point_Total" FROM "USER" WHERE "Act_ID" = $1';
						$points = pg_query_params($conn, $sqlGetPoints, array($user)) or die (header("Location: redirect.php?location=ProjectForm.php"));
						$pointsArray = pg_fetch_row($points);
						
						//point calculations to correctly update the point total of the currently-logged in user
						$currentPoints = $pointsArray[0];
						$currentPoints += ($POINTS_FOR_PROJECT + ($POINTS_FOR_CITATION * $parentLength));
						
						//sql to update the user's point total
						$sqlInsertPoints = 'UPDATE "USER" SET "Point_Total" = $1 WHERE "Act_ID" = $2';
						$pointQuery = pg_prepare($conn, "pointInsertQuery", $sqlInsertPoints) or die(header("Location: redirect.php?location=ProjectForm.php"));
						$pointQuery = pg_execute($conn, "pointInsertQuery", array($currentPoints, $user)) or die(header("Location: redirect.php?location=ProjectForm.php"));
					}
				} //end of parents if
				else {
					//convert the current project's name to uppercase
					$name1 = strtoupper($name);
					
					//this sql will insert the current project into the PROJECT table
					$sql = 'INSERT INTO "PROJECT" ("Project_Name", "Project_Description", "Link", "Funding", "Project_Caps", "Targeted_Audience")
							VALUES ($1, $2, $3, $4, $5, $6)';		
							
					//prepares and executes the query that will insert the project		
					$result = pg_prepare($conn, "baseInsertionQuery", $sql) or die(header("Location: redirect.php?location=ProjectForm.php"));
					$result = pg_execute($conn, "baseInsertionQuery", array($name, $description, $link, $funding, $name1, $target)) or die(header("Location: redirect.php?location=ProjectForm.php"));
					
					//prepare and execute the query so we can get the ID of the just-inserted project
					$sqlGetChildId = "SELECT \"Project_ID\" FROM \"PROJECT\" WHERE \"Project_Caps\" = $1";
					$childID = pg_query_params($conn, $sqlGetChildId, array($name1)) or die (header("Location: redirect.php?location=ProjectForm.php"));
					$childIDResult = pg_fetch_row($childID); //store the id of the just-inserted project
					
					//WE NEED TO PREPARE THIS
					//this will insert info into the OWNS_A table that will reflect the user's ownership of the current project
					//sql that will insert the current user's act id next to the value that was returned from the previous query--the uploaded project's ID
					$ownerSQL = 'INSERT INTO "OWNS_A" ("Act_ID", "Project_ID") VALUES ($1, $2)';
					$userQ = pg_prepare($conn, "insertOwnerQuery", $ownerSQL)  or die(header("Location: redirect.php?location=ProjectForm.php"));
					$userQ = pg_execute($conn, "insertOwnerQuery", array($user, $childIDResult[0]))  or die(header("Location: redirect.php?location=ProjectForm.php"));
					
					//We must now award points for uploading the project to the user
					$sqlGetPoints = 'SELECT "Point_Total" FROM "USER" WHERE "Act_ID" = $1';
					$points = pg_query_params($conn, $sqlGetPoints, array($user)) or die (header("Location: redirect.php?location=ProjectForm.php"));
					$pointsArray = pg_fetch_row($points);
						
					//calculate the update to the current user's point total	
					$currentPoints = $pointsArray[0];
					$currentPoints += ($POINTS_FOR_PROJECT);
						
					//execute the query to update the user's point total	
					$sqlInsertPoints = 'UPDATE "USER" SET "Point_Total"=$1 WHERE "Act_ID" = $2';
					$pointQuery = pg_prepare($conn, "pointInsertQuery", $sqlInsertPoints) or die(header("Location: redirect.php?location=ProjectForm.php"));
					$pointQuery = pg_execute($conn, "pointInsertQuery", array($currentPoints, $user)) or die(header("Location: redirect.php?location=ProjectForm.php"));
				}
				
			} //end of connection if 
			else {
				//redirect to the base database error page
				header('Location: redirect.php?location=ProjectForm.php');
				echo "<p style='color;red'>There is a problem with the database. Try again later.</p>";
			}
		} 
	}
?>

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>

	<meta http-equiv="Content-Type"content="text/html; charset=utf-8" />

	<title>Project Upload</title>
	<style>
		.failure {
			color:red;
		}
	</style>
</head>

<body>
<form name="submitProject" action = "" method = "post">
	<div>
		<p><label for="name">Name:</label>
		<input type="text" name="name" id="name" <?php if(isset($name) && !empty($missing)) { echo 'value="'.htmlentities($name).'"';} else { echo 'value=""'; } ?> /><?php if(!isset($name) && !empty($missing)) { echo '<span class="failure">Required</span>';}?></p>
	</div>
	<div>
		<p><label for="link">Link:</label>
		<input type="text" name="link" id="link" <?php if(isset($link) && !empty($missing)) { echo 'value="'.htmlentities($link).'"';} else { echo 'value=""'; } ?> /></p>
	</div>
	<div>
		<p><label for="funding">Funding:</label>
		<input type="text" name="funding" id="funding" <?php if(isset($funding) && !empty($missing)) { echo 'value="'.htmlentities($funding).'"';} else { echo 'value=""'; } ?> /></p>
	</div>
	<div>
		<p><label for="target">Targeted Audience:</label>
		<input type="text" name="target" id="target" <?php if(isset($target) && !empty($missing)) { echo 'value="'.htmlentities($target).'"';} else { echo 'value=""'; } ?> /> <?php if(!isset($target) && !empty($missing)) echo '<span class="failure">Required</span';?></p>
	</div>
	<div>
		<p><label for="parents">Cited Projects:</label>
		<input type="text" name="parents" id="parents" <?php if(isset($parents) && !empty($missing)) { echo 'value="'.htmlentities($parents).'"';} else { echo 'value=""'; } ?> /></p><?php if(isset($adaptValid) && !$adaptValid) { echo "<p class='failure'>One or more of these projects listed is invalid</p>"; } else { ; }?>
	</div>
	<div>
		<p><label for="description">Project Description</label></p>
		<textarea style="resize:vertical;" name="description" id="description" rows="3" cols="40"><?php if(isset($description) && !empty($missing)) { echo htmlentities($description); }?></textarea> <?php if(!isset($description) && !empty($missing)) { echo '<span class="failure">Required</span>';}?></p>
	</div>
	<div>
		<input type="submit" id="submit" name="submit" value="Submit a Project" />
	</div>
</form>

</body>

</html>