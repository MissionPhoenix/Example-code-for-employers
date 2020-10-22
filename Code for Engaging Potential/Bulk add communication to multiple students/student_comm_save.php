<?php
/**
 * Handler to save/update record of communication with student
 *
 * Original file was used for udating/saving one communication at a time. I edited this file to allow for a communication recorded to be saved for multiple students
 * in one go
 *
 * @author     Pete Donnell <pete@kitson-consulting.co.uk>
 * @author     Mark Donnell <mark@kitson-consulting.co.uk>
 * @author     Dan Tan <dan@kitson-consulting.co.uk>
 * @author     Ben Linsey-Bloom <ben@kitson-consulting.co.uk>
 * @author     Dan Watkins <dan@kitson-consulting.co.uk>
 * @copyright  2017-2019 Kitson Consulting Limited
 * @date       20/10/2020
 * @licence    All rights reserved
 * @package    engagingpotential
 * @see        https://engagingpotential.com/office/
 */

require_once( "includes/connect.php" );
require_once( "includes/functions.php" );
require_once( "includes/admin.php" );

if( !isStaff() ) die( "You don't have permission to access this section." );

if( !isset( $_REQUEST['mode'] ) )
{
	header( 'Location: error.php?error=No mode specified' );
	die();
}
$mode = $_REQUEST['mode'];

if( isset( $_REQUEST['id'] ) ) $id = (int) $_REQUEST['id'];
else $id = 0;

if( isset( $_REQUEST['student_id'] ) and (int) $_REQUEST['student_id'] )
{
	$student_id = (int) $_REQUEST['student_id'];
}
else if ( isset($_POST['student_ids']) and (int) $_POST['student_ids'][0] ) 
{
	if( !isAdmin() or ( isLeader() and !isSeniorManager() ) ) die( "You don't have permission to add a communication to multiple students." );
	foreach( $_POST['student_ids'] as $student_id ) 
	{
		$student_ids[] = (int)$student_id;
	}
} 
else
{
	header( 'Location: error.php?error=' . urlencode( 'No student specified' ) );
	die();
}

switch( $mode )
{
	case 'delete':
		$db->delete( $id, 'student_comms' );
		$ret = "students_edit.php?id=$student_id&tabindex=3";
		break;

	case 'edit': // Fall-through due to insert_or_update function
	case 'add':
		if( isset( $_REQUEST['staff_id'] ) and (int) $_REQUEST['staff_id'] )
		{
			$staff_id = (int) $_REQUEST['staff_id'];
		}
		else
		{
			header( 'Location: error.php?error=' . urlencode( 'No staff member specified.' ) );
			die();
		}

		if( isset( $_POST['no_further_action'] ) ) $follow_up_date = null;
		else $follow_up_date = $_POST['follow_up_date'];

		//Add a communication to each student if multiple students were selected
		if( isset( $student_ids ) ) 
		{
			foreach( $student_ids as $student_id ) 
			{
					$values = array(
						'student_id'			=> $student_id,
						'staff_id'				=> $_POST['staff_id'],
						'date'					=> $_POST['date'],
						'notes'					=> $_POST['notes'],
						'action'				=> $_POST['action'],
			//			'action_date'			=> $_POST['action_date'],
						'follow_up_date'		=> $follow_up_date,
						'communication_type'	=> $_POST['communication_type'],
						'action_taken'			=> $_POST['action_taken'],
					);
					$types = array(
						'student_id'			=> PDO::PARAM_INT,
						'staff_id'				=> PDO::PARAM_INT,
						'date'					=> PDO::PARAM_STR,
						'notes'					=> PDO::PARAM_STR,
						'action'				=> PDO::PARAM_STR,
			//			'action_date'			=> PDO::PARAM_STR,
						'follow_up_date'		=> PDO::PARAM_STR,
						'communication_type'	=> PDO::PARAM_INT,
						'action_taken'			=> PDO::PARAM_STR,
					);

				$id = $db->insert_or_update_many( 0, 'student_comms', $values, $types );

				if( $id )
				{
					$ret = "student_communication_bulk_add.php?msg_success=true";
					foreach( $student_ids as $student_id_ret ) {
						$ret .= "&" . $student_id_ret . "=1";
					}
				// Actions being set as tasks from communitcations has been disable by request of EP, they may wish to implement this again at a later date
				/*	if( isset( $_POST['required_action'] ) and count( $_POST['required_action'] ) )
					{
						$student_details = $db->select_all( $student_id, 'students' );
						$student_name = $student_details[0]['firstname'] . ' ' . $student_details[0]['lastname'];

						//NB we do not edit existing tasks here as that is handled by tasks_edit.php
						foreach( $_POST['required_action'] as $required_action )
						{
							//Create new task
							$constraints = array();
							$types = array();
							//staffid is no longer used but set it anyway just in case
							$constraints['staffid'] = $required_action['staff'];
							$types['staffid'] = PDO::PARAM_INT;
							$constraints['staffsetid'] = $admin_id;
							$types['staffsetid'] = PDO::PARAM_INT;
							$constraints['datecreated'] = date( 'Y-m-d H:i:s' );
							$types['datecreated'] = PDO::PARAM_STR;
							$constraints['datedue'] = $required_action['date'];
							$types['datedue'] = PDO::PARAM_STR;
							$constraints['description'] = '<p>' . $required_action['details'] . '</p>';
							$types['description'] = PDO::PARAM_STR;
							$constraints['title'] = 'Follow up ' . $student_name . ' communication dated: ' . date( 'd/m/Y', strtotime( $required_action['date'] ) );
							$types['title'] = PDO::PARAM_STR;

							$task_id = $db->insert_or_update_many( 0, 'tasks', $constraints, $types );

							//Link new task to staff communication and task to staff
							if( $task_id )
							{
								$success = $db->insert_or_update_many( 0, 'staff_tasks', array( 'staff_id' => $required_action['staff'], 'task_id' => $task_id ), array( 'staff_id' => PDO::PARAM_INT, 'task_id' => PDO::PARAM_INT ) );

								if( $success ) $success = $db->insert_or_update_many( 0, 'student_communications_tasks', array( 'communication_id' => $id, 'task_id' => $task_id ), array( 'communication_id' => PDO::PARAM_INT, 'task_id' => PDO::PARAM_INT ) );
							}
							if( !$task_id or !$success ) $ret = "error.php?error=" . urlencode( "Student communication saved, but creating task for required action failed!" );
						}
					} */
				}
				else $ret = "error.php?error=" . urlencode( "Error adding or editing student communication record." );
			} 
		} else {  //Add or edit a single communication for only one student
				$values = array(
					'student_id'			=> $student_id,
					'staff_id'				=> $_POST['staff_id'],
					'date'					=> $_POST['date'],
					'notes'					=> $_POST['notes'],
					'action'				=> $_POST['action'],
		//			'action_date'			=> $_POST['action_date'],
					'follow_up_date'		=> $follow_up_date,
					'communication_type'	=> $_POST['communication_type'],
					'action_taken'			=> $_POST['action_taken'],
				);
				$types = array(
					'student_id'			=> PDO::PARAM_INT,
					'staff_id'				=> PDO::PARAM_INT,
					'date'					=> PDO::PARAM_STR,
					'notes'					=> PDO::PARAM_STR,
					'action'				=> PDO::PARAM_STR,
		//			'action_date'			=> PDO::PARAM_STR,
					'follow_up_date'		=> PDO::PARAM_STR,
					'communication_type'	=> PDO::PARAM_INT,
					'action_taken'			=> PDO::PARAM_STR,
				);

			$id = $db->insert_or_update_many( $id, 'student_comms', $values, $types );

			if( $id )
			{
				if( isset( $_SESSION['student_comm_search_url'] ) )
				{
					$ret = $_SESSION['student_comm_search_url'];
				}
				else
				{
					$ret = "students_edit.php?id=$student_id&tabindex=3";
				}
				// Actions being set as tasks from communitcations has been disable by request of EP, they may wish to implement this again at a later date
				/* if( isset( $_POST['required_action'] ) and count( $_POST['required_action'] ) )
				{
					$student_details = $db->select_all( $student_id, 'students' );
					$student_name = $student_details[0]['firstname'] . ' ' . $student_details[0]['lastname'];

					//NB we do not edit existing tasks here as that is handled by tasks_edit.php
					foreach( $_POST['required_action'] as $required_action )
					{
						//Create new task
						$constraints = array();
						$types = array();
						//staffid is no longer used but set it anyway just in case
						$constraints['staffid'] = $required_action['staff'];
						$types['staffid'] = PDO::PARAM_INT;
						$constraints['staffsetid'] = $admin_id;
						$types['staffsetid'] = PDO::PARAM_INT;
						$constraints['datecreated'] = date( 'Y-m-d H:i:s' );
						$types['datecreated'] = PDO::PARAM_STR;
						$constraints['datedue'] = $required_action['date'];
						$types['datedue'] = PDO::PARAM_STR;
						$constraints['description'] = '<p>' . $required_action['details'] . '</p>';
						$types['description'] = PDO::PARAM_STR;
						$constraints['title'] = 'Follow up ' . $student_name . ' communication dated: ' . date( 'd/m/Y', strtotime( $required_action['date'] ) );
						$types['title'] = PDO::PARAM_STR;

						$task_id = $db->insert_or_update_many( 0, 'tasks', $constraints, $types );

						//Link new task to staff communication and task to staff
						if( $task_id )
						{
							$success = $db->insert_or_update_many( 0, 'staff_tasks', array( 'staff_id' => $required_action['staff'], 'task_id' => $task_id ), array( 'staff_id' => PDO::PARAM_INT, 'task_id' => PDO::PARAM_INT ) );

							if( $success ) $success = $db->insert_or_update_many( 0, 'student_communications_tasks', array( 'communication_id' => $id, 'task_id' => $task_id ), array( 'communication_id' => PDO::PARAM_INT, 'task_id' => PDO::PARAM_INT ) );
						}
						if( !$task_id or !$success ) $ret = "error.php?error=" . urlencode( "Student communication saved, but creating task for required action failed!" );
					}
				}*/
			}
			else $ret = "error.php?error=" . urlencode( "Error adding or editing student communication record." );
		}
		break;
}
header( 'Location: ' . $ret );