#!/usr/bin/env php
<?php
/**
 * Overdue tasks check, to run as a daily cron job
 *
 * Checks the database for all staff tasks that are overdue, and emails those staff to remind them.
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

	foreach ( $rows as $row )
	{
		$naughty_staff = $row['staff_id'];
		$overdue_staff_tasks[$naughty_staff][] = $row;
	}

	//Compose and send an email to all staff with overdue tasks
	foreach ( $overdue_staff_tasks as $overdue_tasks )
	{
		$overdue_tasks_count = count( $overdue_tasks );
		for( $i = 0; $i < $overdue_tasks_count; ++$i )
		{
			if( $i == 0 )
			{
				$overdue_tasks_count = count( $overdue_tasks );
				if( $overdue_tasks_count == 1 )
				{
					$subject = "Whoops, you have an overdue task.";
				}
				else
				{
					$subject = "Whoops, you have " . $overdue_tasks_count . " tasks overdue.";
				}

				$message = "Hello " . $overdue_tasks[$i]['displayname'] . ",<br />\r\n<br />\r\nThe following tasks are currently overdue:<br />\r\n<br />\r\n";
				$message .= $overdue_tasks[$i]['title'] . " was due on: " . date( 'd/m/Y', strtotime( $overdue_tasks[$i]['datedue'] ) ) . "<br />\r\n";
			}
			else
			{
				$message .= $overdue_tasks[$i]['title'] . " was due on: " . date( 'd/m/Y', strtotime( $overdue_tasks[$i]['datedue'] ) ) . "<br />\r\n";
			}
			$to = $overdue_tasks[$i]['email'];
		}
		$message .= "<br />\r\n" . 'You can view your tasks <a href="https://engagingpotential.com/office/tasks.php">here</a>.';

		$mail = new Email( $to, $subject, $message );
		$mail->send();
	}

}

///:~
