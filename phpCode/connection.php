<?php function dbConnect() {   
    // Database connection parameters.

    $host = "127.0.0.1";
    $user = "cabect";
    $pass = "cabect2013#1";
    $db = "cabectdb";

    // Create connection.
    $link = pg_connect("host=$host dbname=$db user=$user password=$pass");
    
    if (!$link) {
        die("Could not connect: ".pg_last_error());
        echo "<br />";
    }
    //returns the connection as a variable that can be used
    return $link;
 }   
?>
