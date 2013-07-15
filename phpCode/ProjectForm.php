<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML
1.0 Transitional//EN" "http://www.w3.org/
TR/xhtml1/DTD/xhtml1-transitional.dtd>

<?php
	if(array_key_exists('submit', $_POST)) {
		$required = array('name', 'user', 'description');
		$expected = array('name', 'user', 'link', 'parents', 'description', 'funding');
		$missing = array();
		
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
		
		//if the array missing is empty, then the form is filled out correctly with all the required fields
		//we can submit the query
		if(empty($missing)) {
			include('/local/data/www/cabect/htdocs/connection.php');			
			$conn = dbConnect();
			if(isset($conn)) {
				//check if there are any adaptations cited. if there are adaptations cited, then we need to explode the array
				//in case there are many listed.
				if(!empty($parents)) {
					//this second parents array will enable the user input to be output to the form correctly without being in all caps
					$parents2 = $parents;
					$parents = trim($parents); //trip any beginning and ending whitespace
					$parentArray = explode(', ', $parents); //this explodes the form field into an array based on comma delimiters
					$parentLength = count($parentArray); //length of the array
					
					//convert each Project_Name in the array to all uppercase to facilitate searching and comparing
					for($i = 0; $i < $parentLength; $i++) {
						$parentArray[$i] = strtoupper($parentArray[$i]);
					}
					
					//now we need to see if the projects listed are real
					//loop through the parents array and query the database to see if it is there
					//the sql code that will be used for the selects
					$sql = 'SELECT "Project_Caps" FROM "PROJECT" WHERE "Project_Caps" = $1';
					
					//flag variable to see if all checks are passed
					$adaptValid = true;
					
					for($i = 0; $i < $parentLength; $i++) {
						$result = pg_query_params($conn, $sql, array($parentArray[$i])) or die("Could not prepare statement successfully! 1");
						$row = pg_fetch_row($result);
						if(empty($row[0])) {
							$adaptValid = false;
						}
					}
					
					//adaptValid will be false if there are invalid adaptations listed
					if(!$adaptValid) {
						$str = 'adaptIsFalse';
						array_push($missing, $str);
					}
					
					if($adaptValid && empty($missing)) {
						
						//convert the current project's name into all uppercase
						$name1 = strtoupper($name);
						
						//if it makes it here, the projects cited exist in the database
						//insert the project into the database--base project query
						$sql = 'INSERT INTO "PROJECT" ("Project_Name", "Project_Description", "Link", "Funding", "Project_Caps")
								VALUES ($1, $2, $3, $4, $5)';
						$result = pg_prepare($conn, "baseInsertionQuery", $sql) or die("Could not prepare statement successfully 2 ". pg_last_error());
						$result = pg_execute($conn, "baseInsertionQuery", array($name, $description, $link, $funding, $name1)) or die("Could not execute database query successfully! 2");
							
						//the project has been submitted successfully with adaptations.
						//since there are adaptations, we need to update the adaptations table in the database correspondingly
						//converting the current project's name to all caps
						$name = strtoupper($name);
						$sql = 'INSERT INTO "ADAPTATIONS" ("Parent_ID", "Child_ID")
								VALUES ($1, $2)';
						$sqlGetId = 'SELECT "Project_ID" FROM "PROJECT" WHERE "Project_Caps" = $1';
					
						//query to get the ID of the user's project
						$sqlGetChildId = "SELECT \"Project_ID\" FROM \"PROJECT\" WHERE \"Project_Caps\" = $1";
						
						//prepare and execute the query so we can get the ID
						$childID = pg_query_params($conn, $sqlGetChildId, array($name)) or die ("Could not prepare the query");
						$childIDResult = pg_fetch_row($childID);
						
						//now, we need to get the IDs of the parent projects to update the ADAPTATIONS table
					
						for($i = 0; $i < $parentLength; $i++) {
							//this block queries the database to get the Project_IDs of any associated parent projects and stores them in parent project
							$parentProject = pg_query_params($conn, $sqlGetId, array($parentArray[$i])) or die ("Could not prepare query successfully 3".pg_last_error());
							$parentProjectID = pg_fetch_row($parentProject);
							
							//this will add the child and parent IDs into the ADAPTATIONS table
							//this query will add backlinks to child projects and parent projects
							//this query will have a variable name to prevent errors from preparing the same query
							$adaptInsert = pg_prepare($conn, "adaptQuery".$i, $sql) or die("Could not prepare query successfully 4".pg_last_error());
							$adaptInsert = pg_execute($conn, "adaptQuery".$i, array($parentProjectID[0], $childIDResult[0])) or die ("Could not execute query successfully 4".pg_last_error());
						}
					
						//now, we need to update the user's OWNS_A table to represent the addition of this project
						$ownerSQL = 'INSERT INTO "OWNS_A" ("Act_ID", "Project_ID") VALUES ($1, $2)';
						$userQ = pg_prepare($conn, "insertOwnerQuery", $ownerSQL) or die(pg_last_error());
						$userQ = pg_execute($conn, "insertOwnerQuery", array($user, $childIDResult[0])) or die(pg_last_error());
					}
				} //end of parents if
				else {
					//convert the current project's name to uppercase
					$name1 = strtoupper($name);
					
					//this will insert the current project into the PROJECT table
					$sql = 'INSERT INTO "PROJECT" ("Project_Name", "Project_Description", "Link", "Funding", "Project_Caps")
							VALUES ($1, $2, $3, $4, $5)';
							
					//prepares and executes the query that will insert the project		
					$result = pg_prepare($conn, "baseInsertionQuery", $sql) or die("Could not prepare statement successfully");
					$result = pg_execute($conn, "baseInsertionQuery", array($name, $description, $link, $funding, $name1)) or die("Could not execute database query successfully!");
					
					//WE NEED TO PREPARE THIS
					//this will insert info into the OWNS_A table that will reflect the user's ownership of the current project
					$ownerSQL = 'INSERT INTO "OWNS_A" ("Act_ID", "Project_ID") VALUES ($1, $2)';
					$userQ = pg_prepare($conn, "insertOwnerQuery", $ownerSQL)  or die(pg_last_error());
					$userQ = pg_execute($conn, "insertOwnerQuery", array($user, $childIDResult[0]))  or die(pg_last_error());
				}
				
			} //end of connection if 
			else {
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
		<p><label for="user">User ID:</label>
		<input type="text" name="user" id="user" <?php if(isset($user) && !empty($missing)) { echo 'value="'.htmlentities($user).'"';} else { echo 'value=""'; } ?> /><?php if(!isset($user) && !empty($missing)) { echo '<span class="failure">Required</span>';}?></p>
	</div>
	<div>
		<p><label for="link">Link</label>
		<input type="text" name="link" id="link" <?php if(isset($link) && !empty($missing)) { echo 'value="'.htmlentities($link).'"';} else { echo 'value=""'; } ?> /><?php if(!isset($link) && !empty($missing)) { echo '<span class="failure">Required</span>';}?></p>
	</div>
	<div>
		<p><label for="funding">Funding</label>
		<input type="text" name="funding" id="funding" <?php if(isset($funding) && !empty($missing)) { echo 'value="'.htmlentities($funding).'"';} else { echo 'value=""'; } ?> /><?php if(!isset($funding) && !empty($missing)) { echo '<span class="failure">Required</span>';}?></p>
	</div>
	<div>
		<p><label for="parents">Cited Projects</label>
		<input type="text" name="parents" id="parents" <?php if(isset($parents) && !empty($missing)) { echo 'value="'.htmlentities($parents2).'"';} else { echo 'value=""'; } ?> /><?php if(!isset($parents) && !empty($missing)) { echo '<span class="failure">Required</span>';}?></p><?php if(isset($adaptValid) && !$adaptValid) { echo "<p class='failure'>One or more of these projects listed is invalid</p>"; } else { ; }?>
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