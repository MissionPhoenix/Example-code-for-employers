<?php
/**
 * Stores new/updated course details in the database
 * 
 * My first file for saving to the database. Used other 'save' files from the system as reference.
 *
 * @author     Pete Donnell <pete@kitson-consulting.co.uk>
 * @author     Dan Watkins <dan@kitson-consulting.co.uk>
 * @copyright  2019 - 2020 Kitson Consulting Limited
 * @date       04/02/2020
 * @licence    All rights reserved
 * @package    engagingpotential
 * @see        https://engagingpotential.com/office/
 */

require_once("includes/connect.php");
require_once("includes/functions.php");
require_once("includes/admin.php");

if( !isStaff() ) die( "You don't have permission to access this section." );

if(isset($_POST['name']) and $_POST['name'] ) $name = $_POST['name'];

if(isset($_POST['years']) and $_POST['years'] ) $years = (int) $_POST['years'];
else $years = 0;

if(isset($_POST['months']) and $_POST['months'] ) $months = (int) $_POST['months'];
else $months = 0;

if(isset($_POST['days']) and $_POST['days'] ) $days = (int) $_POST['days'];
else $days = 0;

$days += ($years * 365) + ($months * 30);

if(isset($_POST['compulsory']) and $_POST['compulsory'] === 'on' ) $compulsory = true;
else $compulsory = false;

if(isset($_POST['notes']) and $_POST['notes'] ) $notes = $_POST['notes'];

if(isset($_POST['active']) and $_POST['active'] === 'on') $active = true;
else $active = false;

if($_POST['type'] and $_POST['type'] ) $type = $_POST['type'];
else die('<pre>Something went wrong, no type was selected' . $_POST['type']);

if(isset($_POST['id']) and $_POST['id'] ) $id = (int) $_POST['id'];
else $id = null;

if(isset($_POST['ref']) and $_POST['ref'] ) $ref = $_POST['ref'];
else $ref = 'staff-training-list.php';

if($type == 'add')
{
	//store the years, months and years in the db as days, converting and storing in seconds would be inaccurate from month to month
	$values = array(
		'course_name'     => $name,
		'valid_days'      => $days,
		'compulsory'      => $compulsory,
		'notes'           => $notes,
		'active'          => $active,
	);
	$types = array(
		'course_name'     => PDO::PARAM_STR,
		'valid_days'      => PDO::PARAM_INT,
		'compulsory'      => PDO::PARAM_BOOL,
		'notes'           => PDO::PARAM_STR,
		'active'          => PDO::PARAM_BOOL,
	);

	$db->insert_or_update_many( '', 'training', $values, $types );
	$id = $db->lastInsertId();
	if( !$id )
	{
		die( 'Unable to add new course. Please go back and try again.' );
	}
	else
	{
		$ref = 'add-course.php?message=Course+successfully+added';
	}
}
elseif( $type === 'update' )
{
	//store the years, months and years in the db as days. Converting and storing in seconds would be inaccurate from month to month
	$values = array(
		'course_name'     => $name,
		'valid_days'      => $days,
		'compulsory'      => $compulsory,
		'notes'           => $notes,
		'active'          => $active
	);

	$types = array(
		'course_name'     => PDO::PARAM_STR,
		'valid_days'      => PDO::PARAM_INT,
		'compulsory'      => PDO::PARAM_BOOL,
		'notes'           => PDO::PARAM_STR,
		'active'          => PDO::PARAM_BOOL
	);
	if( !$db->insert_or_update_many( $id, 'training', $values, $types ) )
	{
		die( 'Something went wrong when updating the record. Please go back and try again.' );
	}
}

// Autmatically assign compulsory courses to all active staff.
if( $compulsory and $active and ( $type == 'add' ) )
{
	$statement = $db->prepare( "SELECT `staff`.`id` FROM `staff` LEFT OUTER JOIN `staff_training_join` ON `course_id` = :id AND `staff_id` = `staff`.`id` WHERE ( `finishdate` = '0000-00-00 00:00:00' OR `finishdate` > NOW() ) AND `date_assigned` IS NULL " );
	$statement->bindValue( ':id', $id, PDO::PARAM_INT );
	$statement->execute();
	$staff = $statement->fetchAll( PDO::FETCH_ASSOC );
	foreach( $staff as $row )
	{
		$values = array( 'course_id' => $id, 'staff_id' => $row['id'], 'course_notes' => 'Compulsory course automatically assigned by system' );
		$types = array( 'course_id' => PDO::PARAM_INT, 'staff_id' => PDO::PARAM_INT, 'course_notes' => PDO::PARAM_STR );
		$db->insert_or_update_many( 0, 'staff_training_join', $values, $types );
	}
}

header('Location: ' . $ref);
die();

///:~
