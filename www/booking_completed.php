<?php

include_once('include/session.php'); # always include this file first
include_once('include/html_frame.php');

$frame = new HTMLframe();
$frame->doc_start("Booking completed");
$frame->doc_nav('', $_SESSION[ 'user_data' ][ 'usr_firstn' ]." ".$_SESSION[ 'user_data' ][ 'usr_lastn'] );

?>

<div class="container">
	<div class="row">
		<div class="col-lg-offset-2 col-lg-10">
			<h1>Booking completed</h1>
			<h2>Thank you for using ODIN</h2>
			<a href="overview.php" class="btn btn-default" role="button">Back to hosts</a>
		</div>
	</div>
</div>


<?php

$frame->doc_end();

?>









