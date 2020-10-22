<?php
/**
 * Record tabs visited in session
 *
 * Portfolio notes:This code is loaded into a hidden div whenever a tab is clicked. Check footer.php to see the JS/jQuery for this..
 *
 * @author 	   Dan Watkins  <dan@kiston-consulting.co.uk>
 * @copyright  2020 Kitson Consulting Limited
 * @date       09/10/2020
 * @licence    All rights reserved
 * @package    engagingpotential
 * @see        https://engagingpotential.com/office/
 */
require_once( "includes/connect.php" );
//Store session_id, page URI with tab and the datetime the page was loaded in the session details table
if( isset( $_COOKIE['session_id'] ) and $_COOKIE['session_id'] )
{
	$session_id = $_COOKIE['session_id'];
	// trim the "office/"
	$address_w_last_tab = substr( $_COOKIE['last_tab'], 7 );
	$statement = $db->prepare( "INSERT INTO `staff_session_details` ( `session_id`, `page_visited` ) VALUES ( :session_id, :page_visited )" );
	$statement->bindParam( ':session_id', $session_id, PDO::PARAM_INT );
	$statement->bindParam( ':page_visited', $address_w_last_tab, PDO::PARAM_STR );
	$statement->execute();
}
?>

