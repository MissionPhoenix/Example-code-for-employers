<?php
/**
 * Show/edit record of communication with student
 *
 * Original file was used for editing/adding one communication at a time. I updated this file to allow for a communication recorded to be added to multiple students
 * in one go
 * @author     Pete Donnell <pete@kitson-consulting.co.uk>
 * @author     Mark Donnell <mark@kitson-consulting.co.uk>
 * @author     Dan Tan <dan@kitson-consulting.co.uk>
 * @author     Ben Linsey-Bloom <ben@kitson-consulting.co.uk>
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

$id = (int) $_GET["id"]; // ID of the communication record

if( !$id )
{
	$mode = 'add';
	if( isset($_GET['multiple']) and $_GET['multiple'] == 'true') {
		//add record to multiple students
		$multiple = true;
		$display_name = "";
		foreach( $_GET as $key => $value ) 
		{
			if($value == 1) {
				$student_ids_array[] = $key;
				foreach( $db->select_all( $key, 'students' ) as $row )
				{
					$display_name .= $row['firstname'] . " " . $row['lastname'] . ", ";
				}
			}
			unset( $row );
		}
		//DIE(var_dump($student_ids));
	} else {
	// Create new communication record
		$student_id = (int) $_GET["student_id"];
		if( !$student_id ) header( 'Location: error.php?error=No student ID specified' );

		foreach( $db->select_all( $student_id, 'students' ) as $row )
		{
			$display_name = $row['firstname'] . " " . $row['lastname'];
		}
		unset( $row );

		$date = $details = $action = $action_date = $follow_up_date = '';
	}
}
else
{
	$mode = 'edit';
	// Modify an existing communication record
	$sql = "
		SELECT student_comms.*, CONCAT( students.firstname, ' ', students.lastname ) AS display_name
		FROM student_comms, students
		WHERE student_comms.student_id = students.id AND student_comms.id = :id";
	$statement = $db->prepare( $sql );
	$statement->bindValue(':id', $id, PDO::PARAM_INT);
	$statement->execute();

	foreach( $statement->fetchAll( PDO::FETCH_ASSOC ) as $row )
	{
		$student_id = $row['student_id'];
		$display_name = $row['display_name'];
		$date = $row['date'];
		$staff_id = $row['staff_id'];
		$notes = $row['notes'];
		$action = $row['action'];
		$action_date = $row['action_date'];
		$follow_up_date = $row['follow_up_date'];
		$communication_type = $row['communication_type'];
		$action_taken = $row['action_taken'];
	}
	unset( $row );

}

//tab index
$tabindex = nullit( $_GET["tabindex"] );
if( !$tabindex ) $tabindex = "0";
?>
<!DOCTYPE html>
<html>
<?php
require_once( "includes/head.php" );
?>
<script type="text/javascript">
//<![CDATA[
$( function() {
	$('input[name=no_further_action]').change( function() {
		if (this.checked) {
			$("#follow_up_date").prop("disabled", true);
			$('#follow_up_date').css("cursor",'not-allowed');
			$('#follow_up_date').val('');
		} else {
			$("#follow_up_date").prop( "disabled", false );
			$('#follow_up_date').css("cursor",'default');
		}
	} );

	$( "#box-tabs" ).tabs( { selected: <?php echo $tabindex; ?> } );
	$( "#dialog-confirm" ).dialog( {
		autoOpen: false,
		resizable: false,
		height: 200,
		modal: true,
		buttons: {
			'Delete item': function() {
				window.location.href = 'student_comm_save.php?mode=delete&student_id=<?php echo $student_id; ?>&id=<?php echo $id; ?>';
			},
			Cancel: function() {
				$( this ).dialog( 'close' );
			}
		}
	} );

	$( ".dialog-confirm-open" ).click( function() {
		$( "#dialog-confirm" ).dialog( "open" );
		return false;
	} );
} );

function checkfields()
{
	$("#message-error1").css("display","none");
	var ret = true;
	var $required = $( "#date, #staff_id, #comm-type" );

	$required.removeClass("error");

	$required.each( function() {
		var $this = $( this );
		if( $this.val() == '')
		{
			console.log( $this );
			$("#message-error1").css("display","block");
			$this.addClass("error");
			ret = false;
		}
	});

	return ret;
}

<?php
$staff_select = '<option></option>';
$query = "SELECT * FROM `staff` WHERE `finishdate` = '0000-00-00' OR `finishdate` > NOW() ORDER BY `displayname`";
$statement = $db->prepare( $query );
$statement->execute();
$results = $statement->fetchAll( PDO::FETCH_ASSOC );
foreach( $results as $result )
{
	$staff_select .= '<option value="' . $result['id'] . '">' . trim( json_encode( $result['displayname'] ), '"' ) . '</option>';
}
$staff_select .= '</select>';
?>
/*  These commented out functions enable the addition and deletion of actions added to a communication as tasks. This was a functionality request that was reversed.

var current_required_action = 1000;
function add_required_action()
{
	var action_html = '<div>';
	action_html += '<p>Action</p><br /><br /><textarea required="required" name="required_action[' + current_required_action + '][details]"></textarea><br /><br />';
	action_html += '<p>By whom&nbsp;&nbsp;</p><select required="required" name="required_action[' + current_required_action + '][staff]"><?php echo $staff_select; ?><br /><br />';
	action_html += '<p>By when&nbsp;&nbsp;&nbsp;</p><input required="required" type="text" class="date" name="required_action[' + current_required_action + '][date]" /><br /><br />';
	action_html += '<p><a href="#" onclick="return remove_required_action( $( this ) );">Remove this action</a></p>';
	action_html += '<br /><br /><br /><br /></div>';
	$( action_html ).insertBefore( '#add_action_link' );
	$( '.date' ).datepicker( { dateFormat: 'yy-mm-dd' } );
	if( document.getElementById("checkov").checked == true )
	{
		document.getElementById("checkov").checked = false;
	}
	++current_required_action;
}

function remove_required_action( target )
{
	target.parent().parent().remove();
	return false;
}*/
//]]>
</script>
 <script src="resources/scripts/tiny_mce/tinymce.js" type="text/javascript"></script>
 <script src="resources/scripts/tiny_mce/jquery.tinymce.js" type="text/javascript"></script>
<?php require_once("includes/tiny.php"); ?>
	<body>
<?php if( isAdmin() ): ?>
		<div id="dialog-confirm" title="Delete this record">
			<p><span class="ui-icon ui-icon-alert" style="float:left; margin:0 7px 20px 0;"></span>Deleting will be permanent, are you sure?</p>
		</div>
<?php endif; ?>
	<form id="form" action="student_comm_save.php" method="post" onsubmit="return checkfields()">
<?php require_once("includes/header.php"); ?>

		<!-- content -->
		<div id="content">
<?php require_once("includes/menu_left_students.php"); ?>
			<!-- content / right -->
			<div id="right">

				<!-- forms -->

				<div id="box-tabs" class="box">
					<!-- box / title -->
					<div class="title">
						<h5>
							<?php echo ( $mode === 'edit' ? html( $display_name ) . ' - Edit Communication' : html( $display_name ) . ' - Add New Communication' ); ?>
						</h5>
						<ul class="links">
							<li><a href="#box-details">Details</a></li>
						</ul>
					</div>
					<!-- end box / title -->

					<div id="box-details">
						<div class="messages">
							<div id="message-error1"  style="display:none;" class="message message-error">
								<div class="image"><img src="resources/images/icons/error.png" alt="Error" height="32" /></div>
								<div class="text"><h6>Error</h6><span>Please fill in all required fields (Staff, Date,  Communication Type).*</span></div>
								<div class="dismiss"><a href="#message-error"></a></div>
							</div>
						</div>
						<div class="form">
							<div class="fields">
								<div class="field">
									<div class="label">
										<label for="input-large">Staff member:*</label>
									</div>
									<div class="input-large">
										<select id="staff_id" name="staff_id" class="input-large">
<?php
if( isset( $staff_id ) )
{
	$selected = $staff_id;
}
else
{
	$selected = $admin_id;
}
$statement = $db->prepare( "SELECT staff.id, staff.displayname FROM staff ORDER BY displayname ASC" );
$statement->execute();
foreach( $statement->fetchAll( PDO::FETCH_ASSOC ) as $row )
{
	echo '<option value="' . $row['id'] . '"' . ( $selected === $row['id'] ? ' selected="selected"' : '' ) . '>' . html( $row['displayname'] ) . '</option>' . PHP_EOL;
}
?>
										</select>
									</div>
								</div>

								<div class="field">
									<div class="label">
										<label for="input-large">Date:*</label>
									</div>
									<div class="input">
										<input id="date" name="date" class="date" value="<?php echo geteventdate( $date ); ?>" type="text" />
										<p style="margin: 0px 5px;">yyyy-mm-dd</p>
									</div>
								</div>
								<div class="field">
									<div class="label">
										<label for="input-large">Type:*</label>
									</div>
									<div class="input">
										<select id="comm-type" name="communication_type">
											<option value="">Select a Type</option>
<?php
$query= "SELECT * FROM `communication_types` WHERE SUBSTRING( `name`, 1, 1 ) <> '*'  ORDER BY `name`";
foreach( $db->query( $query ) as $communication_types )
{
	echo "<option value='" . $communication_types['id'] . "'";
	if( isset( $communication_type ) and $communication_types['id'] == $communication_type )
	{
		echo ' selected="selected"';
	}
	echo ">" . html( $communication_types['name'] ) . "</option>";
}
$query= "SELECT * FROM `communication_types` WHERE SUBSTRING( `name`, 1, 1 ) = '*' ORDER BY `name`";
foreach( $db->query( $query ) as $communication_types )
{
	echo "<option value='" . $communication_types['id'] . "'";
	if( isset( $communication_type ) and $communication_types['id'] == $communication_type )
	{
		echo ' selected="selected"';
	}
	echo ">" . html( $communication_types['name'] ) . "</option>";
}
?>
										</select>
<?php
if( isSeniorManager() ):
?>
										<p>Click <a href="communication_types.php">here</a> to create or edit communication types.</p>
<?php
endif;
?>
									</div>
								</div>
								<div class="field">
									<div class="label">
										<label for="input-large">Details:</label>
									</div>
									<div class="textarea textarea-editor">
										<textarea id="notes" name="notes" style="width:400px;height:150px;" class="mceEditor_small"><?php echo $notes; ?></textarea>
									</div>
								</div>
<!--  This commented out code enables the viewing of additional actions added to a communication as tasks. This was a functionality request that was reversed.
								<div class="field">
									<div class="label">
										<label for="input-large">Action required:</label>
									</div>
									<div id="action_required" class="input">
<?php /*
$tasks_to_do = false;
$query = "SELECT `tasks`.`id`, `staffid`, `datedue`, `description`, `completed`, `datecompleted`
FROM `tasks`, `student_communications_tasks`
WHERE `tasks`.`id` = `student_communications_tasks`.`task_id`
AND `student_communications_tasks`.`communication_id` = $id";
$statement = $db->prepare( $query );
$statement->execute();
$tasks = $statement->fetchAll( PDO::FETCH_ASSOC );
foreach( $tasks as $task )
{
	if( $task['completed'] == 0 )
	{
		$tasks_to_do = true;
	}
	$staff_select = '';
	$query = "SELECT `displayname` FROM `staff`, `staff_tasks` WHERE `staff`.`id` = `staff_tasks`.`staff_id` AND `staff_tasks`.`task_id` = " . $task['id'] . " ORDER BY `displayname`";
	$statement = $db->prepare( $query );
	$statement->execute();
	$results = $statement->fetchAll( PDO::FETCH_ASSOC );
	for( $i = 0; $i < count( $results ); ++$i )
	{
		if( $i ) $staff_select .= ', ';
		$staff_select .= html( $results[$i]['displayname'] );
	}
	$date_completed = date_create( $task['datecompleted'] );

	echo '<div style="border:1px solid #ccc;">';
	echo '<p>Action</p><br /><br />' . $task['description'] . '<br /><br />';
	echo '<p>By whom&nbsp;&nbsp;' . $staff_select . '</p><br /><br />';
	echo '<p>By when&nbsp;&nbsp;&nbsp;' . substr( html( $task['datedue'] ), 0, 10 ) . '</p><br /><br />';
	if( $task['completed'] == 1 ) echo '<p><b>Task completed on:&nbsp;' . $date_completed->format( 'Y-m-d' ) . '</b>&nbsp;&nbsp;</p><br /><br />';
	echo '<p>To edit/delete this action, <a href="tasks_edit.php?id=' . $task['id'] . '&amp;ref=' . html( $_SERVER['REQUEST_URI'] ) . '">click here</a>.</p>';
	echo '</div><br /><br /><br /><br />';
}*/
?>
										<a href="javascript:add_required_action()" id="add_action_link" style="float:right; padding-right:20em;">Add an action</a>
									</div>
								</div>
-->
								<div class="field">
									<div class="label">
										<label for="input-large">Action required:</label>
									</div>
									<div class="textarea textarea-editor">
										<textarea id="action" name="action" style="width:400px;height:150px;" class="mceEditor_small"><?php echo $action; ?></textarea>
									</div>
								</div>
<!-- Removed by request
								<div class="field">
									<div class="label">
										<label for="input-large">Date of action:</label>
									</div>
									<div class="input">
										<input id="action_date" name="action_date" class="date" value="<?php echo geteventdate( $action_date ); ?>" type="text" />
										<p style="margin: 0px 5px;">yyyy-mm-dd</p>
									</div>
								</div>
-->
								<div class="field">
									<div class="label">
										<label for="input-large">Follow up date:</label>
									</div>
									<div class="input">
										<label><input id="checkov" type="checkbox" name="no_further_action"<?php if( ( $mode === 'add' or !$follow_up_date ) and $tasks_to_do == false ) echo ' checked="checked"'; ?>/> &nbsp;No further action required</label>
									</div>
									<div style="padding-right:10em; padding-left:2em;" class="input">
										<input id="follow_up_date" name="follow_up_date"<?php if( $mode === 'add' or !$follow_up_date ) echo ' disabled="disabled" style="cursor:not-allowed;"'; ?> class="date" value="<?php echo geteventdate( $follow_up_date ); ?>" type="text" required="required" />
										<p style="margin: 0px 5px;">yyyy-mm-dd</p>
									</div>
								</div>

								<div class="field">
									<div class="label">
										<label for="input-large">Action Taken:</label>
									</div>
									<div class="textarea textarea-editor">
										<textarea id="action_taken" name="action_taken" style="width:400px;height:150px;" class="mceEditor_small"><?php echo $action_taken; ?></textarea>
									</div>
								</div>
							</div>
						</div>
					</div>
					<!-- end forms -->

<?php
/* If the url does not contain the word commsearch then the user has not come from the commsearch and should not be redirected there from the handler */
$pageurl = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
if( mb_strpos( $pageurl, 'commsearch' ) === false )
{
	unset($_SESSION['student_comm_search_url']);
}
?>
					<div class="form">
						<div class="fields">
							<div class="buttons">
							<?php if ($mode == 'edit') : ?>
								<input  class="dialog-confirm-open" type="button" name="submit"  value="Delete" />
								&nbsp;&nbsp;&nbsp;
							<?php endif; ?>
							<input type="button" name="submit" onclick="window.history.back()" value="Cancel" />
							&nbsp;&nbsp;&nbsp;
							<div class="highlight">
								<input type="submit" name="submit" value="Save changes" />
								<?php
								if( !isset($multiple) ) {
								?>
									<input type="hidden" name="id" value="<?php echo $id; ?>" id="id" />
									<input type="hidden" name="student_id" value="<?php echo $student_id; ?>" id="student_id" />
								<?php
								// if adding communications to multiple students feed an array to student comm save
								} else {
									foreach( $student_ids_array as $student_id ) 
									{
								?>
										<input type="hidden" name="student_ids[]" value="<?php echo $student_id; ?>" />
								<?php
									}
								}
								?>
								<input type="hidden" name="mode" value="<?php echo $mode; ?>" id="mode" />
							</div>
							</div>
						</div>
					</div><!-- form -->
				</div><!-- box -->
			</div><!-- end content / right -->
		</div><!-- end content -->
<?php require_once("includes/footer.php"); ?>
</form>
	</body>
</html>
<?php require_once("includes/disconnect.php");
