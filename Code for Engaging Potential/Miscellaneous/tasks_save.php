<?php
/**
 * Add/edit/delete handler for tasks
 *
 * Portfolio notes: I wrote the code for email notifications when a task is set
 *
 * @author     Pete Donnell <pete@kitson-consulting.co.uk>
 * @author     Mark Donnell <mark@kitson-consulting.co.uk>
 * @author     Dan Watkins <dan@kitson-consulting.co.uk>
 * @copyright  2015-2018 Kitson Consulting Limited
 * @date       27/01/2019
 * @licence    All rights reserved
 * @package    engagingpotential
 * @see        https://engagingpotential.com/office/
 */

require_once( 'includes/connect.php' );
require_once( 'includes/functions.php' );
require_once( 'includes/admin.php' );

if( !isStaff() ) die( "You don't have permission to access this section." );

$mode = $_REQUEST['mode'];
if( isset( $_REQUEST['id'] ) ) $id = (int) $_REQUEST['id'];
else $id = 0;
$complete_email_was_sent = false;
$assigned_email_was_sent = false;
$recurring_task = false;
$completed_by_all = false;
$ref = $_REQUEST['ref'];

switch( $mode )
{
	case 'delete':
		if( $id === 0 ) $ret = 'error.php?error=No task specified';
		else
		{
			//TODO add permissions check for senior manager or $admin_id == $staffsetid when there is an associated communication

			$db->delete( $id, 'tasks' );
			$ret = "tasks.php?s=1&msg_success=Task Deleted!";
		}
		break;

	case 'edit':
		$vars = array(
			'datedue' => $_POST['datedue'],
			'description' => $_POST['description'],
			'link' => $_POST['link'],
			'title' => $_POST['title'],
			'recurrence_type' => $_POST['recurrence_type'],
			'recurrence_detail_1' => $_POST['recurrence_detail_1'],
			'recurrence_detail_2' => $_POST['recurrence_detail_2'],
		);
		$types = array(
			'datedue' => PDO::PARAM_STR,
			'description' => PDO::PARAM_STR,
			'link' => PDO::PARAM_STR,
			'title' => PDO::PARAM_STR,
			'recurrence_type' => PDO::PARAM_STR,
			'recurrence_detail_1' => PDO::PARAM_STR,
			'recurrence_detail_2' => PDO::PARAM_STR,
			'completed' => PDO::PARAM_INT
		);

		if ($mode == 'edit')
		{
			$vars['completed'] = (int) $_POST['completed'];
		}
		else
		{
			$vars['staffsetid'] = (int) $_POST['staffsetid'];
			$types['staffsetid'] = PDO::PARAM_INT;
			$vars['datecreated'] = date( 'Y-m-d H:i:s' );
			$types['datecreated'] = PDO::PARAM_STR;
			$vars['completed'] = 0;
		}

		// TASK COMPLETION NOTIFICATIONS
		if( $id )
		{
			if( (int)$_POST['completed'] === 1 )
			{
				$query = "UPDATE `staff_tasks` SET `completed` = :completed WHERE `staff_id` = $admin_id AND `task_id` = $id";
				$statement = $db->prepare( $query );
				$statement->bindValue( ':completed', mysqldatetime( date( 'Y-m-d H:i:s' ) ), PDO::PARAM_STR );
				try
				{
					$statement->execute();
				}
				catch( PDOException $e )
				{
					die( '<pre>' . print_r( $e->error_info, true ) );
				}

				// Save datecompleted as today
				$vars['datecompleted'] = mysqldatetime( date( 'Y-m-d H:i:s' ) );
				$types['datecompleted'] = PDO::PARAM_STR;

				// Store who completed the task
				$vars['staffid'] = $admin_id;
				$types['staffid'] = PDO::PARAM_INT;

				// Check if this has been marked as completed by all staff members
				$statement = $db->prepare( "SELECT `completed` FROM `staff_tasks` WHERE `task_id` = $id AND `completed` = '0000-00-00 00:00:00'" );
				$statement->execute();
				$results = $statement->fetchAll( PDO::FETCH_ASSOC );

				if( count( $results ) === 0 )
				{
					$completed_by_all = true;
					// Increase date for recurring tasks
					$statement = $db->prepare( "SELECT `datedue`, `recurrence_type`, `recurrence_detail_1`, `recurrence_detail_2` FROM `tasks` WHERE `id` = $id" );
					$statement->execute();
					$results=$statement->fetchAll(PDO::FETCH_ASSOC);
					switch($results[0]["recurrence_type"])
					{
						case "daily":
							$recurring_task = true;
							$new_date=date("Y-m-d 00:00:00",strtotime("tomorrow"));
							if ($results[0]["recurrence_detail_1"]==="weekdays" and (date("l")==="Friday" or date("l")==="Saturday"))
							{
								$new_date=date("Y-m-d 00:00:00",strtotime("next Monday"));
							}
							break;
						case "weekly":
							$recurring_task = true;
							$new_date=date("Y-m-d 00:00:00",strtotime("next ".$results[0]["recurrence_detail_1"]));
							break;
						case "monthly":
							$recurring_task = true;
							if((int)date("d",strtotime($results[0]["datedue"])) === (int)$results[0]["recurrence_detail_1"])
							{
								$new_date=date("Y-m-d 00:00:00", strtotime($results[0]["datedue"] . " +1 month"));
							}
							else
							{
								$new_date=date("Y-m-").str_pad($results[0]["recurrence_detail_1"], 2, "0", STR_PAD_LEFT)." 00:00:00";
								if((int)date("d")>(int)$results[0]["recurrence_detail_1"])
								{
									$new_date=date("Y-m-d 00:00:00",strtotime($new_date." +1 month"));
								}
							}
							break;
						case "yearly":
							$recurring_task = true;
							//if due date's month and day match detail 1 and detail 2 then task is next due one year from current due date
							if((int)date("m",strtotime($results[0]["datedue"])) === (int)$results[0]["recurrence_detail_1"] and (int)date("d",strtotime($results[0]["datedue"])) === (int)$results[0]["recurrence_detail_2"])
							{
								$new_date=date("Y-m-d 00:00:00", strtotime($results[0]["datedue"] . " +1 year"));
							}
							//if due date's month and day don't match detail 1 and detail 2 then:
							else
							{
								//if detail 1 (month) and detail 2 (day) have not passed this year then task is next due this year
								$tempdate = date_parse($results[0]["recurrence_detail_1"]);
								$new_date=date("Y-").str_pad($tempdate["month"], 2, "0", STR_PAD_LEFT)."-".str_pad($results[0]["recurrence_detail_2"], 2, "0", STR_PAD_LEFT)." 00:00:00";

								//if detail 1 (month) and detail 2 (day) have already passed this year then task is next due next year
								if((int)date("m")>(int)$tempdate["month"] or ((int)date("m")===(int)$tempdate["month"] and (int)date("d")>(int)$results[0]["recurrence_detail_2"]))
								{
									$new_date=date("Y-m-d 00:00:00",strtotime($new_date." +1 year"));
								}
							}
							break;
						default:
							//do nothing
							break;
					}
				}
			}
			else
			{
				$query = "UPDATE `staff_tasks` SET `completed` = :completed WHERE `staff_id` = $admin_id AND `task_id` = $id";
				$statement = $db->prepare( $query );
				$statement->bindValue( ':completed', '0000-00-00 00:00:00', PDO::PARAM_STR );
				try
				{
					$statement->execute();
				}
				catch( PDOException $e )
				{
					die( '<pre>' . print_r( $e->error_info, true ) );
				}
			}
		}
		// Check if assigned staff have changed.
		if( isset( $_POST['staffid'] ) and count( $_POST['staffid'] ) )
		{
			// Was the assigned staff member changed or a new task created? (as opposed to other changes)
			$changed = true;
			if( $mode === 'edit' )
			{
				$statement = $db->prepare( "SELECT `staff_id` FROM `staff_tasks` WHERE `task_id` = $id ORDER BY `staff_id`" );
				$statement->execute();
				$old_staff_ids = $statement->fetchAll( PDO::FETCH_NUM );
				if( $old_staff_ids == $_POST['staffid'] ) $changed = false;
			}
		}

		// if the task is recurring set the completion status back to not completed
		if( $recurring_task )
		{
			if( $completed_by_all )
			{
				$old_task = $db->select_all( $id, 'tasks' );
				$new_variables = $old_task[0];
				unset( $new_variables['id'] );
				$new_variables['datedue'] = $new_date;
				$new_variables['datecreated'] = date( 'Y-m-d H:i:s' );
				$new_types = array();
				foreach( $new_variables as $key => $value )
				{
					if( ctype_digit( $value ) ) $new_types[$key] = PDO::PARAM_INT;
					else $new_types[$key] = PDO::PARAM_STR;
				}
				$new_id = $db->insert_or_update_many( 0, 'tasks', $new_variables, $new_types );
				if( $new_id )
				{
					$old_staff = $db->query( "SELECT `staff_id` FROM `staff_tasks` WHERE `task_id` = $id" );
					foreach( $old_staff as $os ) $db->insert_or_update_many( 0, 'staff_tasks', array( 'staff_id' => $os['staff_id'], 'task_id' => $new_id ), array( 'staff_id' => PDO::PARAM_INT, 'task_id' => PDO::PARAM_INT ) );
				}
			}
		}
		$id = $db->insert_or_update_many( $id, 'tasks', $vars, $types );

		if( $id )
		{
			if( $changed )
			{
				foreach( $_POST['staffid'] as $assigned_staff_id )
				{
					$query = "SELECT `id` FROM `staff_tasks` WHERE `staff_id` = " . (int)$assigned_staff_id . " AND `task_id` = $id";
					$statement = $db->prepare( $query );
					$statement->execute();
					$results = $statement->fetchAll( PDO::FETCH_ASSOC );
					if( count( $results ) === 0 ) $db->insert_or_update_many( 0, 'staff_tasks', array( 'staff_id' => (int) $assigned_staff_id, 'task_id' => $id ), array( 'staff_id' => PDO::PARAM_INT, 'task_id' => PDO::PARAM_INT ) );
				}
				$query = "SELECT `staff_id` FROM `staff_tasks` WHERE `task_id` = $id";
				$statement = $db->prepare( $query );
				$statement->execute();
				$results = $statement->fetchAll( PDO::FETCH_ASSOC );
				foreach( $results as $result)
				{
					if( !in_array( $result['staff_id'], $_POST['staffid'] ) )
					{
						$query = "DELETE FROM `staff_tasks` WHERE `task_id` = $id AND `staff_id` = " . $result['staff_id'];
						$statement = $db->prepare( $query );
						$statement->execute();
					}
				}
			}
			if( $complete_email_was_sent ) $ret = 'tasks.php?msg_success=Task Completed! Email notification was sent.';
			if( $assigned_email_was_sent ) $ret = 'tasks.php?msg_success=Task Assigned. Email notification was sent.';
			else $ret = 'tasks.php?msg_success=Task Saved!';
		}
		else $ret = 'error.php?error=Error editing task';
		break;

	case 'add':
		$vars = array(
			'datedue' => $_POST['datedue'],
			'description' => $_POST['description'],
			'link' => $_POST['link'],
			'title' => $_POST['title'],
			'recurrence_type' => $_POST['recurrence_type'],
			'recurrence_detail_1' => $_POST['recurrence_detail_1'],
			'recurrence_detail_2' => $_POST['recurrence_detail_2'],
		);
		$types = array(
			'datedue' => PDO::PARAM_STR,
			'description' => PDO::PARAM_STR,
			'link' => PDO::PARAM_STR,
			'title' => PDO::PARAM_STR,
			'recurrence_type' => PDO::PARAM_STR,
			'recurrence_detail_1' => PDO::PARAM_STR,
			'recurrence_detail_2' => PDO::PARAM_STR,
			'completed' => PDO::PARAM_INT,
		);

		$vars['staffsetid'] = (int) $_POST['staffsetid'];
		$types['staffsetid'] = PDO::PARAM_INT;
		$vars['datecreated'] = date( 'Y-m-d H:i:s' );
		$types['datecreated'] = PDO::PARAM_STR;
		$vars['completed'] = 0;

		// if the task is recurring set the completion status back to not completed
		if( $recurring_task )
		{
			$vars['completed'] = 0;
			if( isset( $new_date ) )
			{
				$vars["datedue"] = $new_date;
				$types["datedue"] = PDO::PARAM_STR;
			}
		}
		$id = $db->insert_or_update_many( 0, 'tasks', $vars, $types );

		if( $id )
		{
			foreach( $_POST['staffid'] as $assigned_staff_id )
			{
				$db->insert_or_update_many( 0, 'staff_tasks', array( 'staff_id' => (int) $assigned_staff_id, 'task_id' => $id ), array( 'staff_id' => PDO::PARAM_INT, 'task_id' => PDO::PARAM_INT ) );
			}
			if( $assigned_email_was_sent ) $ret = 'tasks.php?msg_success=' . urlencode( 'Task Assigned. Email notification was sent.' );
			else $ret = 'tasks.php?msg_success=' . urlencode( 'Task Saved!' );
		}
		else $ret = 'error.php?' . urlencode( 'error=Error adding task' );

		// Email staff and managers to inform them a task has been assigned
		if( count( $_POST['staffid'] ) )
		{
			// Get IDs of staff assigned to task
			$ids = '';
			foreach( $_POST['staffid'] as $tempid ) $ids .= (int) $tempid . ', ';
			$ids = substr( $ids, 0, -2 ); // Strip trailing ', '

			// Get names of staff assigned to task
			$query = "SELECT `displayname`, `email` FROM `staff` WHERE `id` IN ( $ids )";
			$statement = $db->prepare( $query );
			$statement->execute();
			$results = $statement->fetchAll( PDO::FETCH_ASSOC );
			$staff_names = '';
			$staff_emails = '';
			foreach( $results as $result ) 
			{
				$staff_names .= $result['displayname'] . ', ';
				$staff_emails .= $result['email'] . ', ';  
			}
			$staff_names = substr( $staff_names, 0, -2 ); // Strip trailing ', '
			$to = substr( $staff_emails, 0, -2 ); // Strip trailing ', '
		    
		    // Staff email
			$subject = '(EP Office) Task "' . $_POST['title'] . '" has been assigned to you';

			$message  = "Hi,<br/>\r\n<br/>\r\n";
			$message .= "The Task '" . html( $_POST['title'] ) . "' has been assigned to you by " . html( $admin_displayname ) . ".<br/>\r\n<br/>\r\n";
			$message .= 'Since you have access to the <a href="https://engagingpotential.com/office/">EP Office system</a>, you can log in and find it under ' . "'Tasks' in the top menu.\r\n"; 

			$mail = new Email( $to, $subject, $message ); 

			if( $mail->send() ) $ret = 'tasks.php?msg_success=' . urlencode( 'Task Assigned. Email notification was sent to those assigned.' );
			else $ret = 'tasks.php?msg_success=' . urlencode( 'Task Saved!' );

			// Get email addresses of managers
			$query = "SELECT `email`, `id` FROM `staff` WHERE `id` NOT IN ( $admin_id, $ids ) AND `userlevel` IN ( SELECT `id` FROM `accesslevels` WHERE `name` = 'Manager' OR `name` = 'Senior Manager' )";
			$statement = $db->prepare( $query );
			$statement->execute();
			$results = $statement->fetchAll( PDO::FETCH_ASSOC );
			$to = '';
			$is_manager = 0;
			foreach( $results as $result )
			{
				$to .= $result['email'] . ', ';
				if( $result['id'] ==  $admin_id ) $is_manager = 1;
			} 
			$to = substr( $to, 0, -2 ); // Strip trailing ', '
			
			// Manager email
			$subject = '(EP Office) Task "' . $_POST['title'] . '" assigned';

			$message  = "Hi,<br/>\r\n<br/>\r\n";
			$message .= "The Task '" . html( $_POST['title'] ) . "' has been assigned to " . html( $staff_names ) . " by " . html( $admin_displayname ) . ".<br/>\r\n<br/>\r\n";
			$message .= 'Since you have access to the <a href="https://engagingpotential.com/office/">EP Office system</a>, you can log in and find it under ' . "'Tasks' in the top menu.\r\n"; 

			$mail = new Email( $to, $subject, $message ); 
  
			if( $mail->send() and ( $is_manager == 0 ) )
			{
				$ret .=  urlencode( ' Email notification was also sent to the managers.' );
			}
			elseif( $mail->send() and ( $is_manager == 1 ) )
			{
				$ret .=  urlencode( '' );
			}
			else $ret .= urlencode( ' Task Saved!' );
		}
		break;
}
if( $ref ) header( 'Location: ' . $ref );
elseif( $ret ) header( 'Location: ' . $ret );
else header( 'Location: tasks.php' );
