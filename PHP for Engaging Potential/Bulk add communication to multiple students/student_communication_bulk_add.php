<?php
/**
 * Page for adding communications to student logs in bulk.
 *
 * Portfolio notes: some of the HTML template was copied from another page but the rest of this file was written by me.
 *
 * @author     Dan Watkins <dan@kitson-consulting.co.uk>
 * @copyright  2020 Kitson Consulting Limited
 * @date       21/10/2020
 * @licence    All rights reserved
 * @package    engagingpotential
 * @see        https://engagingpotential.com/office/
 */

require_once( 'includes/connect.php' );
require_once( 'includes/functions.php' );
require_once( 'includes/admin.php' );

if( !isAdmin() or ( isLeader() and !isSeniorManager() ) ) die( "You don't have permission to access this section." );

//Get all current students
$query = "SELECT * FROM `students` WHERE `archive` = 0 
									AND `finishdate` = '0000-00-00' 
									OR `finishdate` > CURRENT_DATE() 
									ORDER BY `lastname`";

$statement = $db->prepare( $query );
$statement->execute();
$students = $statement->fetchAll( PDO::FETCH_ASSOC );

//Create form contents for multiple student select.
$student_select = "";
foreach( $students as $student ) {
	$student_select .= '<div class="bulk-add-field"><label>';
	$student_select .= '<input type="checkbox" name="' . $student['id'] . '"';
	if( isset($_GET[$student['id']]) and (int) $_GET[$student['id']] ) $student_select .= ' checked ';
	$student_select .= ' value="1"><span>' . html($student['firstname']) . " " . html($student['lastname']);
	$student_select .= '</span></label></div>';
}

$pagetitle = "Student communications Bulk Add";
// messages
$msg_success = nullit( $_REQUEST["msg_success"] );
if( $msg_success === '' )
	$msg_success_display = "display:none;";
else
	$msg_success_display = "display:block;";

$msg_success = "That communication was just added to the students checked below";
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<?php require_once( 'includes/head.php' ); ?>
	<body>
<?php require_once( 'includes/header.php' ); ?>

		<!-- content -->
		<div id="content">
<?php require_once( 'includes/menu_left_students.php' ); ?>
			<!-- content / right -->
			<div id="right">
				<!-- table -->
				<div class="box">
					<!-- box / title -->
					<div class="title">
						<h5><?php echo $pagetitle; ?></h5>
					</div>

					<!-- end box / title -->
					<div>
						<div id="message-success" style="<?php echo $msg_success_display; ?>" class="message message-success">
							<div class="image">

								<img src="resources/images/icons/success.png" alt="Success" height="32" />
							</div>
							<div class="text">
								<h6>Success</h6>
								<span><?php echo $msg_success; ?></span>
							</div>
							<div class="dismiss">
								<a href="#message-success"></a>
							</div>
						</div>
						<button id="all-select">Select All</button>
						<p></p>
						<form id="student-bulk-comms-form" method="get" action="student_comm_edit.php">
						<?php
echo $student_select;
						?>
							<input type="hidden" name="multiple" value="true">
							<br><br>
							<input type="submit" value="Add communication to selected">
						</form>
					</div>
				</div>
				<!-- end table -->
			</div>
			<!-- end content / right -->
		</div>
		<!-- end content -->
<?php require_once( 'includes/footer.php' ); ?>
<script> 
$( document ).ready( function() {
	$("#all-select").on("click", function() {
		if( $(this).html() != "Unselect All") {
			$("[type='checkbox']").each( function() {
				$(this).attr("checked", true);
			});
			$(this).html("Unselect All");
		} else {
			$("[type='checkbox']").each( function() {
				$(this).attr("checked", false);
			});
			$(this).html("Select All");
		}
	});
});
</script>
	</body>
</html>
<?php require_once( 'includes/disconnect.php' );
