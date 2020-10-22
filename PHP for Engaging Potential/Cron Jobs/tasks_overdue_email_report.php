#!/usr/bin/env php
<?php
/**
 * Overdue tasks check, to run as a weekly cron job
 *
 * Checks the database for all staff tasks that are overdue andsends an email report to managers.
 *
 * Portfolio notes. Though I wrote this file and made it executable. I did not set up the cron job for it, I'm sure I would be able to do it but only 
 * the senior developers have root access on the company servers.
 *
 * @author     Daniel Watkins <dan@kitson-consulting.co.uk>
 * @copyright  2020 Kitson Consulting Limited
 * @date       12/02/2020
 * @licence    All rights reserved
 * @package    engagingpotential
 * @see        https://engagingpotential.com/office/
 */

require_once( 'includes/connect.php' );
require_once( 'includes/functions.php' );

define( 'CMS_MAILFROM', 'office@engagingpotential.com' );

//Get all of the current staff and all overdue tasks associated with them
$sql = "SELECT
	`staff`.`id`,
	`staff`.`displayname`,
	`staff`.`email`,
	`staff`.`userlevel`,
	`staff`.`finishdate`,
	`tasks`.`id`,
	`tasks`.`datedue`,
	`tasks`.`title`,
	`tasks`.`recurrence_type`,
	`staff_tasks`.`staff_id`,
	`staff_tasks`.`task_id`,
	`staff_tasks`.`completed`
FROM `staff_tasks`
JOIN `staff` ON `staff`.`id` = `staff_tasks`.`staff_id`
JOIN `tasks` ON `tasks`.`id` = `staff_tasks`.`task_id`
WHERE ( `staff`.`finishdate` = '0000-00-00' OR `staff`.`finishdate` > NOW() )
AND `tasks`.`datedue` < NOW()
AND `staff_tasks`.`completed` = '0000-00-00 00:00:00'
AND DATEDIFF( NOW(), `tasks`.`datedue`) < 31
ORDER BY `staff_tasks`.`staff_id` ASC
";


$statement = $db->prepare($sql);
$statement->execute();
$rows = $statement->fetchAll(PDO::FETCH_ASSOC);
if( count( $rows ) )
{

	$overdue_staff_tasks = array(
		array(),
		array()
	);
	$naughty_staff = $rows[0]['staff_id'];
	$staff_count = 1;

	foreach ( $rows as $row )
	{
		if( $naughty_staff == $row['staff_id'] )
		{
			$overdue_staff_tasks[$naughty_staff][] = $row;
		}
		else
		{
			$naughty_staff = $row['staff_id'];
			$overdue_staff_tasks[$naughty_staff][] = $row;
			++$staff_count;
		}

	}
}

// Get email addresses of managers
$query = "SELECT `email`, `id` FROM `staff` WHERE `userlevel` IN ( SELECT `id` FROM `accesslevels` WHERE `name` = 'Manager' OR `name` = 'Senior Manager' ) AND `finishdate` = '0000-00-00 00:00:00' OR `finishdate` > NOW()";
$statement = $db->prepare( $query );
$statement->execute();
$results = $statement->fetchAll( PDO::FETCH_ASSOC );
$to = '';
foreach( $results as $result )
{
	$to .= $result['email'] . ', ';
}
$to_managers = substr( $to, 0, -2 ); // Strip trailing ', '

// Compose and send email to managers with list of overdue tasks with staff name, task tile and due date.
$subject = "Weekly overdue tasks report";

if( $staff_count == 1 )
{
	$message = "Hello Managers,<br />\r\n<br />\r\nThere is a staff member who has at least one overdue task.<br />\r\n<br />\r\n";
}
else
{
	$message = "Hello Managers,<br />\r\n<br />\r\nThere are " . $staff_count . " staff with overdue tasks.<br />\r\n<br />\r\n";
}

foreach ( $overdue_staff_tasks as $overdue_tasks )
{
	$number_of_tasks = count( $overdue_tasks );
	for( $i = 0; $i < $number_of_tasks; ++$i )
	{
		$message .= html( $overdue_tasks[$i]['displayname'] ) . "'s task " . html( $overdue_tasks[$i]['title'] ) . " should have been completed by " . date( 'd/m/Y', strtotime( $overdue_tasks[$i]['datedue'] ) ) . "<br />\r\n";
	}
}

$message .= "<br />\r\n" . 'You can <a href="https://engagingpotential.com/office/tasks.php">view all tasks here</a>.';

$mail = new Email( $to_managers, $subject, $message );
$mail->send();

///:~
