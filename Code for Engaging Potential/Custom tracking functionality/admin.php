<?php
/**
 * Checks that the user is logged in
 *
 * Portfolio Notes: My code in this file is everything pertinent to session_id cookies and storing session details. 
 * This file is required in most pther files on the site.
 *
 * @author     (original) Graham Woodruff
 * @author     Matt Kingston <matt@kitson-consulting.co.uk>
 * @author     Mark Donnell <mark@kitson-consulting.co.uk>
 * @author     Pete Donnell <pete@kitson-consulting.co.uk>
 * @author     Dan Watkins <dan@kitson-consulting.co.uk>
 * @copyright  2019 - 2020 Kitson Consulting Limited
 * @date       12/10/2020
 * @licence    All rights reserved
 * @package    engagingpotential
 * @see        https://engagingpotential.com/office/
 */

// check cookies
if( empty( $admin_displayname ) ) $admin_displayname = nullit( $_COOKIE["admin_displayname"] );
if( empty( $admin_id ) ) $admin_id = nullit( $_COOKIE["admin_id"] );
if( empty( $admin_email ) ) $admin_email = nullit( $_COOKIE["admin_email"] );
if( empty( $session_id ) ) $session_id = nullit( $_COOKIE["session_id"] );
if( empty( $admin_displayname ) or empty( $admin_id ) or empty( $admin_email ) )
{
	if( isset( $_SERVER['REQUEST_URI'] ) and $_SERVER['REQUEST_URI'] )
	{
		if( mb_strpos( $_SERVER['REQUEST_URI'], '&modal=true' ) !== false )
		{
			$request = base64_encode( str_replace( '&modal=true', '', $_SERVER['REQUEST_URI'] ) );
		}
		elseif( mb_strpos( $_SERVER['REQUEST_URI'], '?modal=true&' ) !== false )
		{
			$request = base64_encode( str_replace( '?modal=true&', '?', $_SERVER['REQUEST_URI'] ) );
		}
		elseif( mb_strpos( $_SERVER['REQUEST_URI'], '?modal=true' ) !== false )
		{
			$request = base64_encode( str_replace( '?modal=true', '', $_SERVER['REQUEST_URI'] ) );
		}
		else $request = base64_encode( $_SERVER['REQUEST_URI'] );
		header( "Location: login.php?lastURL=$request" );
		die();
	}
	else
	{
		header( 'Location: login.php' );
		die();
	}
}

// check valid
$statement = $db->prepare( "
	SELECT `userlevel` FROM `staff`
	WHERE `id` = :admin_id
	AND `displayname` = :admin_displayname
	AND `cmsaccess` = 1
	UNION
	SELECT 0 AS `userlevel` FROM `providers`
	WHERE `id` = :provider_id
	AND `contactname` = :provider_name" );
$statement->bindParam( ':admin_id', $admin_id, PDO::PARAM_INT );
$statement->bindParam( ':admin_displayname', $admin_displayname, PDO::PARAM_STR );
$statement->bindParam( ':provider_id', $admin_id, PDO::PARAM_INT );
$statement->bindParam( ':provider_name', $admin_displayname, PDO::PARAM_STR );
$statement->execute();
$results = $statement->fetchAll( PDO::FETCH_ASSOC );


if( !( count( $results ) === 1 and isset( $results[0]['userlevel'] ) ) )
{
	header( 'Location: login.php' );
	die();
}
else
{
	$session_length = 1800;
	if( time() - (int) $_COOKIE['last_activity'] > $session_length )
	{
		if( isset( $_SERVER['REQUEST_URI'] ) and $_SERVER['REQUEST_URI'] )
		{
			if( mb_strpos( $_SERVER['REQUEST_URI'], '&modal=true' ) !== false )
			{
				$request = base64_encode( str_replace( '&modal=true', '', $_SERVER['REQUEST_URI'] ) );
			}
			elseif( mb_strpos( $_SERVER['REQUEST_URI'], '?modal=true&' ) !== false )
			{
				$request = base64_encode( str_replace( '?modal=true&', '?', $_SERVER['REQUEST_URI'] ) );
			}
			elseif( mb_strpos( $_SERVER['REQUEST_URI'], '?modal=true' ) !== false )
			{
				$request = base64_encode( str_replace( '?modal=true', '', $_SERVER['REQUEST_URI'] ) );
			}
			else $request = base64_encode( $_SERVER['REQUEST_URI'] );
			header( "Location: login.php?lastURL=$request" );
			die();
		}
		else
		{
			header( 'Location: login.php' );
			die();
		}

	}
	else setcookie( "last_activity", time(), 0, '/' );
	$admin_userlevel = (int) $results[0]['userlevel'];
}

//Store session_id, page URI and the datetime the page was loaded in the session details table. No backend files or responses please
if( isset( $_SERVER['REQUEST_URI'] ) and $_SERVER['REQUEST_URI']  and ( strpos($_SERVER['REQUEST_URI'], "object") === false ) and ( strpos($_SERVER['REQUEST_URI'], "save ") === false ) and ( strpos($_SERVER['REQUEST_URI'], "handler") === false ) and ( strpos($_SERVER['REQUEST_URI'], "ajax") === false ) )
{
	//Remove the "/office/"
	$trimmed_uri = substr( $_SERVER['REQUEST_URI'], 8 );
	setcookie('last_url', $trimmed_uri);
	$statement = $db->prepare( "INSERT INTO `staff_session_details` ( `session_id`, `page_visited` ) VALUES ( :session_id, :page_visited )" );
	$statement->bindParam( ':session_id', $session_id, PDO::PARAM_INT );
	$statement->bindParam( ':page_visited', $trimmed_uri, PDO::PARAM_STR );
	$statement->execute();
}

// enable tinymce
$_SESSION['TinyMCELogin'] = true;

// Define some things
$CONTACT_TYPES = array( 'venues', 'providers' );

define( 'CMS_MAILFROM', 'office@engagingpotential.com' );

/**
 * Check that the current user is a staff member
 *
 *@return bool TRUE if staff, FALSE otherwise
 */
function isStaff()
{
	global $admin_userlevel;
	if ( isset( $admin_userlevel ) and $admin_userlevel >= 1 ) return true;
	else return false;
}
/**
 * Check that the current user is an admin
 *
 *@return bool TRUE if admin, FALSE otherwise
 */
function isAdmin()
{
	global $admin_userlevel;
	if ( isset( $admin_userlevel ) and ( $admin_userlevel == 4 or isSeniorManager() ) ) return true;
	else return false;
}
/**
 * Check that the current user is a team leader
 *
 *@return bool TRUE if team leader, FALSE otherwise
 */
function isLeader()
{
	global $admin_userlevel;
	if ( isset( $admin_userlevel ) and $admin_userlevel >= 7 ) return true;
	else return false;
}
/**
 * Check that the current user is a senior manager
 *
 *@return bool TRUE if senior manager, FALSE otherwise
 */
function isSeniorManager()
{
	global $admin_userlevel;
	if ( isset( $admin_userlevel ) and $admin_userlevel >= 10 ) return true;
	else return false;
}
///:~
