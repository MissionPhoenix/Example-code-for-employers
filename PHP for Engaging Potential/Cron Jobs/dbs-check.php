#!/usr/bin/env php
<?php
/**
 * Expired DBS/CRB check, to run as a cron job
 *
 * Checks the database for all staff and service provider DBSes that expire in the next 1 months or less.
 * Alerts the managers at Engaging of the soon to expire DBS and other required certifications.
 * This cron job also sends warnings for car insurance expiry, driving license checks, mot expiry and road tax expiry.
 *
 * Portfolio Notes: I extended this file to include warnings for Driving License checks, Road tax and MOT.
 *
 *NOTE dbs_valid_to should be called dbs_valid_from, public_insurance_valid_to should be called public_insurance_valid_from, business *insurance_valid_to should be called business_insurance_valid_from and SLA_valid_to should be called SLA_valid_from.
 *
 * @author     Daniel Tan <dan@kitson-consulting.co.uk>
 * @author     Mark Donnell <mark@kitson-consulting.co.uk>
 * @author     Pete Donnell <pete@kitson-consulting.co.uk>
 * @author     Daniel Watkins <dan@kitson-consulting.co.uk>
 * @copyright  2020 Kitson Consulting Limited
 * @date       30/03/2020
 * @licence    All rights reserved
 * @package    engagingpotential
 * @see        https://engagingpotential.com/office/
 */

require_once( 'includes/connect.php' );
require_once( 'includes/functions.php' );

define( 'CMS_MAILFROM', 'office@engagingpotential.com' );

// Get the id of the senior manager access level
$query = "SELECT `id` FROM `accesslevels` WHERE `name` = 'Manager' OR `name` = 'Senior Manager'";
$statement = $db->prepare( $query );
$statement->execute();
$results = $statement->fetchAll( PDO::FETCH_ASSOC );
$senior_manager_userlevel = (int) $results[0]['id'];

//Get all of the staff
$sql = "SELECT
	`staff`.`email`,
	`staff`.`userlevel`,
	`staff`.`finishdate`
FROM staff";

$emailArray = array();
$statement = $db->prepare( $sql );
$statement->execute();
$rows = $statement->fetchAll(PDO::FETCH_ASSOC);

//get all managers Emails from this list
foreach ($rows as $row)
{
	if( $row['userlevel'] == $senior_manager_userlevel and ( $row['finishdate'] === '0000-00-00 00:00:00' or strtotime( $row['finishdate'] ) > time() ) )
	{
		$emailArray[] = $row['email'];
	}
}

//init some arrays for use here
$trainingExpiryDate = array();
$trainingExpiryStaff = array();
$courseName = array();

$sql = "SELECT
	`displayname`,
	`course_name`,
	MAX(`expiry_date`) AS `expiry_date`
	FROM (
	SELECT
	`staff`.`id` AS `staff_id`,
	`training`.`id` AS `training_id`,
	`staff`.`displayname`,
	( CASE
	WHEN `date_assigned` IS NULL THEN '9999-12-31'
	ELSE DATE_ADD(`staff_training_join`.`date_assigned`, INTERVAL `training`.`valid_days` DAY)
	END )
	AS `expiry_date`,
	`training`.`course_name`
FROM `staff`, `staff_training_join`, `training`

WHERE `staff`.`id` = `staff_training_join`.`staff_id`
AND `staff_training_join`.`course_id` = `training`.`id`
AND (`staff`.`finishdate` = '0000-00-00' OR (`staff`.`finishdate` > NOW() AND DATE_ADD(`staff_training_join`.`date_assigned`, INTERVAL `training`.`valid_days` DAY) < `staff`.`finishdate`))
) a
GROUP BY `staff_id`, `training_id`
HAVING DATEDIFF(MAX(`expiry_date`), NOW() ) < 31
UNION
SELECT
	`staff`.`displayname`,
	`training`.`course_name`,
	'0000-00-00' AS `expiry_date`
FROM `staff`, `staff_training_join`, `training`

WHERE `staff`.`id` = `staff_training_join`.`staff_id`
AND `staff_training_join`.`course_id` = `training`.`id`
AND ( `staff`.`finishdate` = '0000-00-00' OR `staff`.`finishdate` > NOW() )
AND `date_assigned` IS NULL

ORDER BY `displayname`, `course_name`";

$statement = $db->prepare($sql);
$statement->execute();
$rows = $statement->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row)
{
	$trainingExpiryDate[] = $row['expiry_date'];
	$trainingExpiryStaff[] = $row['displayname'];
	$courseName[] = $row['course_name'];
}

$subject = "DBS, Insurance or SLA soon to expire for staff or providers";
$message = "Hello Managers,<br />\r\n<br />\r\nThe following staff members or providers have important documents which are soon to expire or have already expired.<br />\r\n<br />\r\n";

$message .= "<strong>The following expiry warnings are for staff:</strong><br />\r\n";

$query = "SELECT * FROM `staff`
		WHERE ( `finishdate` = '0000-00-00' OR `finishdate` > NOW() )
		AND ( DATEDIFF( DATE_ADD( `crb`, INTERVAL 3 YEAR ), NOW() ) < 31
		AND ( `crb` < `finishdate` OR `finishdate` = '0000-00-00' ) )
		ORDER BY `staff`.`displayname`";
$statement = $db->prepare( $query );
$statement->execute();
$rows = $statement->fetchAll( PDO::FETCH_ASSOC );
foreach( $rows as $row )
{
	$message .= html( $row['displayname'] ) . "'s DBS expires on " . date( 'd/m/Y', strtotime( $row['crb'] . "+ 3 years" ) ) . "<br />\r\n";
//	$db->query( "UPDATE `staff` SET `crb_warning` = 1 WHERE `id` = " . $row['id'] );
}
if( empty( $rows ) ) $message .= "There are no DBS expiry warnings for staff. <br />\r\n";
$message .= "<br />\r\n";

$query = "SELECT * FROM `staff` WHERE ( `finishdate` = '0000-00-00' OR `finishdate` > NOW() ) AND ( DATEDIFF( `car_insurance_end`, NOW() ) < 31 AND ( `car_insurance_end` < `finishdate` OR `finishdate` = '0000-00-00' ) )";
$statement = $db->prepare( $query );
$statement->execute();
$rows = $statement->fetchAll( PDO::FETCH_ASSOC );
foreach( $rows as $row )
{
	$message .= html( $row['displayname'] ) . "'s car insurance expires on " . date( 'd/m/Y', strtotime( $row['car_insurance_end'] ) ) . "<br />\r\n";
//	$db->query( "UPDATE `staff` SET `car_alert` = 1 WHERE `id` = " . $row['id'] );
}
if( empty( $rows ) ) $message .= "There are no car insurance expiry warnings for staff. <br />\r\n";
$message .= "<br />\r\n";

for( $x = 0; $x < count( $courseName ); ++$x )
{
	if( $trainingExpiryDate[$x] === '0000-00-00' ) $message .= html( $trainingExpiryStaff[$x] ) . ' has not yet completed course ' . html( $courseName[$x] ) . "<br />\r\n";
	else $message .= html( $trainingExpiryStaff[$x] ) . "'s course ". html( $courseName[$x] ) . ' expires on ' . date( 'd/m/Y', strtotime( $trainingExpiryDate[$x] ) ) . "<br />\r\n";
}
if( count( $courseName ) === 0 ) $message .= "There are no training expiry warnings for staff. <br />\r\n";
$message .= "<br />\r\n";


$message2 = "<br />\r\n<strong>The following expiry warnings are for providers:</strong><br />\r\n";

$query = "SELECT * FROM `providers`
WHERE (`finishdate` = '0000-00-00'
	OR (`finishdate` > NOW()
		AND `finishdate` > DATE_ADD(`dbs_valid_to`, INTERVAL 1096 DAY)
	) )
	AND (DATEDIFF( DATE_ADD(`dbs_valid_to`, INTERVAL 1096 DAY), NOW() ) < 31
		OR `dbs_valid_to` = '0000-00-00')
ORDER BY `contactname`
";
$statement = $db->prepare( $query );
$statement->execute();
$rows = $statement->fetchAll( PDO::FETCH_ASSOC );

foreach( $rows as $row )
{
	if ($row['dbs_valid_to'] === '0000-00-00 00:00:00')
	{
		$message2 .= html( $row['contactname'] ) . " has no DBS date set<br />\r\n";
	}
	else
	{
		$utcSt = date_create( $row['dbs_valid_to'] );
		$utcExp = date_add( $utcSt, date_interval_create_from_date_string( "3 years" ) );
		$time = $utcExp->format( 'd/m/Y' );
		$message2 .= html( $row['contactname'] ) . "'s DBS expires on " . $time . "<br />\r\n";
	}
	//	$db->query( "UPDATE `providers` SET `dbs_email_sent` = 'y' WHERE `id` = " . $row['id'] );
}
if( empty( $rows ) ) $message2 .= "There are no DBS expiry warnings for providers. <br />\r\n";
$message2 .= "<br />\r\n";

$query = "SELECT
		`finishdate`,
		`public_liability_valid_to`,
		`contactname`
 		FROM `providers`
 		WHERE ( `finishdate` = '0000-00-00' OR `finishdate` > NOW() ) AND ( DATEDIFF( NOW(), `public_liability_valid_to` ) > 334 AND ( `public_liability_valid_to` < `finishdate` OR `finishdate` = '0000-00-00' ) )
 		ORDER BY `contactname`";
$statement = $db->prepare( $query );
$statement->execute();
$rows = $statement->fetchAll( PDO::FETCH_ASSOC );
foreach( $rows as $row )
{
	$message2 .= html( $row['contactname'] ) . "'s public liability insurance expires on " . date( 'd/m/Y', strtotime( $row['public_liability_valid_to'] . '+ 1 years' ) ) . "<br />\r\n";
//	$db->query( "UPDATE `providers` SET `public_liability_email_sent` = 'y' WHERE `id` = " . $row['id'] );
}
if( empty( $rows ) ) $message2 .= "There are no public liability insurance expiry warnings for providers. <br />\r\n";
$message2 .= "<br />\r\n";
$query = "SELECT
		`finishdate`,
		`business_insurance_valid_to`,
		`contactname`
 		FROM `providers`
 		WHERE ( `finishdate` = '0000-00-00' OR `finishdate` > NOW() ) AND ( DATEDIFF( NOW(), `business_insurance_valid_to` ) > 334 AND ( `business_insurance_valid_to` < `finishdate` OR `finishdate` = '0000-00-00' ) )
 		ORDER BY `contactname`";
$statement = $db->prepare( $query );
$statement->execute();
$rows = $statement->fetchAll( PDO::FETCH_ASSOC );
foreach( $rows as $row )
{
	$message2 .= html( $row['contactname'] ) . "'s business insurance expires on " . date( 'd/m/Y', strtotime( $row['business_insurance_valid_to'] . '+ 1 years' ) ) . "<br />\r\n";
//	$db->query( "UPDATE `providers` SET `business_insurance_email_sent` = 'y' WHERE `id` = " . $row['id'] );
}
if( empty( $rows ) ) $message2 .= "There are no business insurance expiry warnings for providers. <br />\r\n";
$message2 .= "<br />\r\n";

$query = $query = "SELECT
		`finishdate`,
		`sla_valid_to`,
		`contactname`
 		FROM `providers`
 		WHERE ( `finishdate` = '0000-00-00' OR `finishdate` > NOW() ) AND ( DATEDIFF( NOW(), `sla_valid_to` ) > 334 AND ( `sla_valid_to` < `finishdate` OR `finishdate` = '0000-00-00' ) )
 		ORDER BY `contactname`";
$statement = $db->prepare( $query );
$statement->execute();
$rows = $statement->fetchAll( PDO::FETCH_ASSOC );
foreach( $rows as $row )
{
	$message2 .= html( $row['contactname'] ) . "'s SLA expires on " . date( 'd/m/Y', strtotime( $row['sla_valid_to'] . '+ 1 years' ) ) . "<br />\r\n";
//	$db->query( "UPDATE `providers` SET `sla_email_sent` = 'y' WHERE `id` = " . $row['id'] );
}
if( empty( $rows ) ) $message2 .= "There are no SLA expiry warnings for providers. <br />\r\n";
$message2 .= "<br />\r\n";

$message2 .= "<br />\r\n-- <br />\r\nThis message was automatically generated by the Engaging Potential MIS, please do not reply.";

if( empty( $rows ) ) $message2 = "There are no expiry warnings for providers.";

$message .= "<br />" . $message2;

foreach( $emailArray as $to )
{
	$mail = new Email( $to, $subject, $message );
	$mail->send();
}


// The following section sends a seperate expiry warning email for Driving License checks, Road tax and MOT.

$sql = "SELECT
	`staff`.`id`,
	`staff`.`displayname`,
	`staff`.`userlevel`,
	`staff`.`finishdate`,
	`staff`.`driving_license_checked`,
	`staff`.`road_tax_expiry`,
	`staff`.`mot_expiry`
FROM staff
WHERE (`staff`.`finishdate` = '0000-00-00' OR (`staff`.`finishdate` > NOW() ) )
ORDER BY `staff`.`displayname` ASC
";

$statement = $db->prepare( $sql );
$statement->execute();
$rows = $statement->fetchALL( PDO::FETCH_ASSOC );

$subject = "Road tax, M.O.T. and driving license check expiry warnings";
$message = "<br>\r\n";

foreach( $rows as $row )
{
	$licensecheckeddate = $row['driving_license_checked'];
	if( ( $licensecheckeddate <> '0000-00-00') and ( date( 'Y-m-d' ) >= date( 'Y-m-d', strtotime("+5 months", strtotime( $licensecheckeddate ) ) ) ) and ( date( 'Y-m-d' ) <= date( 'Y-m-d', strtotime("+6 months", strtotime( $licensecheckeddate ) ) ) ) )
	{
		$warnings_exist = true;
		$message .= $row['displayname'] . "'s diving license needs to be checked before " . date( 'Y-m-d', strtotime("+6 months", strtotime( $licensecheckeddate ) ) ) . ".<br>\r\n";
	}
	elseif( ( $licensecheckeddate <> '0000-00-00') and ( date( 'Y-m-d' ) >= date( 'Y-m-d', strtotime("+6 months", strtotime( $licensecheckeddate ) ) ) ) )
	{
		$warnings_exist = true;
		$message .= $row['displayname'] . "'s diving license should have been checked before " . date( 'Y-m-d', strtotime("+6 months", strtotime( $licensecheckeddate ) ) ) . "!<br>\r\n";
	}

	$roadtaxexpiry = $row['road_tax_expiry'];
	if( ( $roadtaxexpiry <> '0000-00-00') and ( date( 'Y-m-d' ) >= date( 'Y-m-d', strtotime("-1 month", strtotime( $roadtaxexpiry ) ) ) ) and ( date( 'Y-m-d' ) <= date( 'Y-m-d', strtotime( $roadtaxexpiry ) ) ) )
	{
		$warnings_exist = true;
		$message .= $row['displayname'] . "'s road tax will expire on " . date( 'Y-m-d', strtotime( $roadtaxexpiry ) ) . ".<br>\r\n";
	}
	elseif( ( $roadtaxexpiry <> '0000-00-00') and ( date( 'Y-m-d' ) >= date( 'Y-m-d', strtotime( $roadtaxexpiry ) ) ) )
	{
		$warnings_exist = true;
		$message .= $row['displayname'] . "'s road tax has expired on " . date( 'Y-m-d', strtotime( $roadtaxexpiry ) ) . "!<br>\r\n";
	}

	$motexpiry = $row['mot_expiry'];
	if( ( $motexpiry <> '0000-00-00') and ( date( 'Y-m-d' ) >= date( 'Y-m-d', strtotime("-1 month", strtotime( $motexpiry ) ) ) ) and ( date( 'Y-m-d' ) <= date( 'Y-m-d', strtotime( $motexpiry ) ) ) )
	{
		$warnings_exist = true;
		$message .= $row['displayname'] . "'s M.O.T. will expire on " . date( 'Y-m-d', strtotime( $motexpiry ) ) . ".<br>\r\n";
	}
	if( ( $motexpiry <> '0000-00-00') and ( date( 'Y-m-d' ) >= date( 'Y-m-d', strtotime( $motexpiry ) ) ) )
	{
		$warnings_exist = true;
		$message .= $row['displayname'] . "'s M.O.T. has expired on " . date( 'Y-m-d', strtotime( $motexpiry ) ) . "!<br>\r\n";
	}
}

if( !isset( $warnings_exist ) )
{
	$message .= "All clear, there are no warnings this time.";
}

foreach( $emailArray as $to )
{
	$mail = new Email( $to, $subject, $message );
	$mail->send();
}

///:~
