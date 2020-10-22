<?php
/**
 * If the page is still open update the staff sessions table
 *
 * Portfolio notes: This code is loaded into a hidden div on page load and at set intervals. Check footer.php to see the JS/jQuery for this.
 *
 * @author 	   Dan Watkins  <dan@kiston-consulting.co.uk>
 * @copyright  2020 Kitson Consulting Limited
 * @date       09/10/2020
 * @licence    All rights reserved
 * @package    engagingpotential
 * @see        https://engagingpotential.com/office/
 */
require_once( "includes/connect.php" );
date_default_timezone_set("Europe/London");
//Update the last_poll column in the Staff_sessions table to indicate the user is still logged in.
if( isset( $_COOKIE['session_id'] ) && ( $_COOKIE['last_activity'] + 1800 ) > time() )
{
	$query = "UPDATE `staff_sessions` SET `last_poll` = NOW() WHERE `session_id` = :session_id ";
	$statement = $db->prepare( $query );
	$statement->bindValue( ':session_id', $_COOKIE['session_id'], PDO::PARAM_INT );
	$statement->execute();
}

require_once( "includes/disconnect.php" );
///:~