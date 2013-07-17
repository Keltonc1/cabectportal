<script language="javascript">
  
  var time = 9;
  var interval;
  
  function timeDecrement() {
    var p = document.getElementById('count');
    if(time == 0) {
      clearInterval(interval);
    }
    if(time > 1) {
      p.innerHTML = time + " seconds.";
    }
    else {
      p.innerHTML = time + " second.";
    }
    time--;
  }
  
  interval = setInterval('timeDecrement()', 1000);
</script>

<?php
	session_start();
	$location = $_GET['location'];
	echo "<html>
	<head>
		<style>
			.danger, #count {
				color:red;
			}
		</style>
	</head>
	<body>
		<h1 class=\"danger\">Database Issue</h1>
		<p>There is a problem connecting/using to the database. Please check to make sure you are connected to the internet. Additionally,
			our database may be down for maintenance. You will be redirected to the last page you visited in </p><p id='count'>10 seconds.</p>
	</body>
		</html>";
	header("refresh:10; url=$location");
?>