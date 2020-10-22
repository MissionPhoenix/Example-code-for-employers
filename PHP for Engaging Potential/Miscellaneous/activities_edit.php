<?php
/**
 * Page for creating/editing sessions
 *
 * NB that Activities have been rebranded Sessions!
 * Often opened in modal pop-up by clicking on a timetable slot
 *
 * Portfolio notes: added the functionality for several new fields when editing session details e.g. learning foci, objectives, risk assesments, 
 * vehicle details and many others. Added events to the modal window tabs as the modal is being generated.
 *
 * @author     Mark Donnell <mark@kitson-consulting.co.uk>
 * @author     Pete Donnell <pete@kitson-consulting.co.uk>
 * @author     Dan Watkins <dan@kitson-consulting.co.uk>
 * @copyright  2018-2019 Kitson Consulting Limited
 * @date       20/10/2019
 * @licence    All rights reserved
 * @package    engagingpotential
 * @see        https://engagingpotential.com/office/
 */
require_once( 'includes/connect.php' );
require_once( 'includes/functions.php' );
require_once( 'includes/admin.php' );

$new_session = false;

if( isset( $_GET['id'] ) )
{
	$id = (int) $_GET['id'];
}
elseif( isSeniorManager() )
{
	// If there is no id set, we're creating a new session. Don't forget to delete it if the user clicks 'Cancel'.
	$id = 0;
	if( isset( $_GET['d'] ) and $_GET['d'] )
	{
		$fromdate = $todate = date( 'Y-m-d', strtotime( urldecode( $_GET['d'] ) ) );
		$fromtime = date( 'H:i', strtotime( urldecode( $_GET['d'] ) ) );
		$totime = date( 'H:i', strtotime( urldecode( $_GET['d'] ) ) + 60*60 );
	}
	else
	{
		$fromdate = $todate = date( 'Y-m-d' );
		$fromtime = '09:30:00';
		$totime = '10:30:00';
	}
	$studentid = (int) $_GET['studentid'];
	if( !$studentid ) // This should never happen!
	{
		header( 'Location: error.php?error=No student selected.' );
		die();
	}
	// Check if there is already a blank session present in this slot
	$query = "SELECT id FROM activities
	          WHERE attended = 0 AND activitytitle = 'Untitled'
	          AND notes = ' ' AND datestart = '$fromdate $fromtime'
	          AND datefinish = '$todate $totime' AND activitytypeid = 0 AND venueid = 0";
	$result = $db->query( $query );
	if( $result !== false )
	{
		foreach( $result as $line )
		{
			$id = $line['id'];
		}
	}
	// If no blank session present, create a new one
	if( !$id )
	{
		$query = "INSERT INTO activities (attended, activitytitle, notes, datestart, datefinish, activitytypeid, venueid)
		          VALUES (0, 'Untitled', ' ', '$fromdate $fromtime', '$todate $totime', 0, 0 )";
		$result = $db->exec( $query );
		$id = $db->lastInsertId();
		$query = "INSERT INTO activitystudents (studentid, activityid) VALUES ($studentid, $id)";
		$db->exec( $query );
		$new_session = true;
	}
}
elseif( isset( $_SESSION['last_url'] ) ) // If possible, redirect non-admins who try to create a new session
{
	header( 'Location: ' . $_SESSION['last_url'] );
	die();
}
else // This should never happen.
{
	header( 'Location: error.php?error=' . urlencode( 'Access denied. You must be an admin to create new sessions.' ) );
	die();
}

$modal = $_GET['modal']; // If in modal dialog, display form only
$_SESSION['modal'] = $modal; // Create modal session variable


if( $modal ) $_SESSION['activityref'] = 'timetable.php';
else $_SESSION['activityref'] = 'activities.php?s=1';

$notes = $debrief = $activitytitle = $activitytypeid = $venueid = $fromdate = $fromtime = $todate = $totime = $datestart = $datefinish = $fontsize = $planning_required = $child_session = '';

if( $id )
{
	// Get session details
	$query = "SELECT *, COALESCE(`time_attended`, '') as place_holder FROM `activities` WHERE `id` = $id";
	$statement = $db->prepare( $query );
	$statement->execute();
	foreach( $statement->fetchAll( PDO::FETCH_ASSOC ) as $row )
	{
		$attended = $row['attended'];
		$activitytitle = html( $row['activitytitle'] );
		$notes = $row['notes'];
		$debrief = $row['debrief'];
		$activitytypeid = $row['activitytypeid'];
		$venueid = $row['venueid'];
		$fromdate = geteventdate( $row['datestart'] );
		$fromtime = geteventtime( $row['datestart'] );
		if( $fromtime === '00:00' ) $fromtime = '';
		$todate = geteventdate( $row['datefinish'] );
		$totime = geteventtime( $row['datefinish'] );
		if( $totime === '23:59' or $totime === '00:00' ) $totime = '';
		$fontsize = $row['fontsize'];
		$attendance_type = $row['attendance_type'];
		$time_attended = $row['place_holder'];
		$planning_required = $row['planning_required'];

		$query = 'SELECT `id` FROM `activities` WHERE `parent_session` = :id';
		$statement = $db->prepare($query);
		$statement->bindParam( ':id', $id, PDO::PARAM_INT );
		$statement->execute();
		$results = $statement->fetchAll(PDO::FETCH_ASSOC);
		if (count($results))
		{
			$child_session = $row['id'];
		}
	}

	// Get session planning details
	$query = "SELECT * FROM `activity_planning` WHERE `activityid` = $id";
	$statement = $db->prepare( $query );
	// It's not necessary to bind parameters because $id has been cast to int
	$statement->execute();
	$results = $statement->fetchAll( PDO::FETCH_ASSOC );
	if( count( $results ) )
	{
		$plan = $results[0];
		switch( $plan['staff_type'] )
		{
			case 'staff':
				$query = "SELECT `displayname` FROM `staff` WHERE `id` = " . $plan['staffid'];
				break;
			case 'provider':
				$query = "SELECT `contactname` AS `displayname` FROM `providers` WHERE `id` = " . $plan['staffid'];
				break;
			default:
				die( 'Unknown staff type in session planning.' );
				break;
		}
		$statement = $db->prepare( $query );
		$statement->execute();
		$results = $statement->fetchAll( PDO::FETCH_ASSOC );
		if( count( $results ) ) $plan['staff'] = $results[0]['displayname'];
	}
	if( $plan )
	{
		$learning_focus_id = $plan['learning_focus_id'];
		$learning_focus_id_2 = $plan['learning_focus_id_2'];
		$learning_focus_id_3 = $plan['learning_focus_id_3'];

		$vehicle_id = $plan['vehicle_id'];
		$activity_risk_assessment = $plan['risk_assessment'];
		$young_person_risk_assessment = $plan['young_person_risk_assessment'];
		$vehicle_risk_assessment = $plan['vehicle_risk_assessment'];
		if( $plan['itinerary_if_required'] === 'y' ) $itinerary_if_required = true;
		else $itinerary_if_required = false;
		$pick_up_staff_id = $plan['pick_up_staff_id'];
		$pick_up_vehicle_id = $plan['pick_up_vehicle_id'];
		$pick_up_young_person_risk_assessment = $plan['pick_up_young_person_risk_assessment'];
		$pick_up_vehicle_risk_assessment = $plan['pick_up_vehicle_risk_assessment'];

		if( $plan['pick_up_itinerary_if_required'] === 'y' ) $pick_up_itinerary_if_required = true;
		else $pick_up_itinerary_if_required = false;
	}
	else
	{
		$vehicle_id = null;
		$activity_risk_assessment = null;
		$young_person_risk_assessment = null;
		$vehicle_risk_assessment = null;
		$itinerary_if_required = null;
		$pick_up_staff_id = null;
		$pick_up_vehicle_id = null;
		$pick_up_young_person_risk_assessment = null;
		$pick_up_vehicle_risk_assessment = null;
		$pick_up_itinerary_if_required = null;
	}
}

$externaldropdown = $staffdropdown = '';

if( isSeniorManager() )
{
	// Staff
	$staff = '';

	$staffdropdown = '<select form="form" id="newstaffid" name="newstaffid" style="width:200px;">' . PHP_EOL;
	foreach( $db->query( "SELECT * FROM staff WHERE finishdate = '0000-00-00 00:00:00' OR finishdate > '$todate $totime' ORDER BY displayname" ) as $row )
	{
		$staffdropdown .= '<option value="' . $row['id'] . '">' . str_replace( ' ', '&nbsp;', html( $row['displayname'] ) ) . '</option>' . PHP_EOL;
	}
	$staffdropdown .= '</select>' . PHP_EOL;

	// External Contacts
	$external = '';

	$externaldropdown = '<select form="form" id="newprovidersid" name="newprovidersid" style="width:200px;">' . PHP_EOL;
	foreach( $db->query( "SELECT * FROM providers WHERE finishdate = '0000-00-00 00:00:00' OR finishdate > '$todate $totime' ORDER BY contactname" ) as $row )
	{
		$externaldropdown .= '<option value="' . $row['id'] . '">' . str_replace( ' ', '&nbsp;', html( $row['contactname'] ) ) . "</option>" . PHP_EOL;
	}
	$externaldropdown .= "</select>" . PHP_EOL;

	$activitytitle = '<input form="form" type="text" name="activitytitle" value="' . $activitytitle . '" />';
}

// Session Types
if( isSeniorManager() )
{
	$activitytype = '<select form="form" id="activitytypeid" name="activitytypeid">' . PHP_EOL;
	$activitytype .= '<option disabled="disabled">Select</option>' . PHP_EOL;
	$query = "SELECT *, ( SELECT COUNT(*) FROM `activities` WHERE `activitytype`.`id` = `activities`.`activitytypeid` ) AS num_activities_of_type FROM activitytype WHERE id > 0 ORDER BY num_activities_of_type DESC, activity ASC";
	foreach( $db->query( $query ) as $row )
	{
		if( $activitytypeid == $row['id'] ) $activitytype .= '<option selected="selected" value="' . $row['id'] . '">' . $row['activity'] . '</option>';
		else $activitytype .= '<option value="' . $row['id'] . '">' . str_replace( ' ', '&nbsp;', html( $row['activity'] ) ) . '</option>';
		$activitytype .= PHP_EOL;
	}
	$activitytype .= '</select>' . PHP_EOL;
}
else
{
	foreach( $db->query( 'SELECT * FROM activitytype WHERE id > 0 ORDER BY activity' ) as $row )
	{
		if( $activitytypeid == $row['id'] ) $activitytype = '<p>' . str_replace( ' ', '&nbsp;', html( $row['activity'] ) ) . '</p>' . PHP_EOL;
	}
}

// Venue
$venues = '<select form="form" id="venueid" name="venueid">' . PHP_EOL;
$venues .= '<option></option>' . PHP_EOL;
// Venues/locations were previously listed in the order of most commonly used venue first
//$query = "SELECT *, ( SELECT COUNT(*) FROM `activities` WHERE `venues`.`id` = `activities`.`venueid` ) AS num_activities_of_type FROM venues WHERE id > 0 ORDER BY CASE WHEN( `venuename` = '_' ) THEN 0 ELSE 1 END, num_activities_of_type DESC, venuename ASC";
$query = "SELECT * FROM `venues` WHERE `id` > 0 ORDER BY `venuename`";
foreach( $db->query( $query ) as $row )
{
	if ($venueid == $row['id'] ) $venues .= '<option selected="selected" value="' . $row['id'] . '">' . $row['venuename'] . '</option>';
	else $venues .= '<option value="' . $row['id'] . '">' . str_replace( ' ', '&nbsp;', html( $row['venuename'] ) ) . '</option>';
	$venues .= PHP_EOL;
}
$venues .= '</select>' . PHP_EOL;

// Curriculum areas for session planning

$query = 'SELECT * FROM `awardsubjects` ORDER BY `subject_area`';
$statement = $db->prepare( $query );
$statement->execute();
$results = $statement->fetchAll( PDO::FETCH_ASSOC );
$curriculum_areas = array( 1 => '', 2 => '', 3 => '' );
foreach( $results as $result )
{
	for( $i = 1; $i <= 3; ++$i )
	{
		$curriculum_areas[$i] .= '<option ';
		if( isset( $plan ) and $plan['curriculum_area_' . (string) $i] === $result['id'] ) $curriculum_areas[$i] .= 'selected="selected" ';
		$curriculum_areas[$i] .= 'value="' . $result['id'] . '">' . html( $result['subject_area'] ) . '</option>' . PHP_EOL;
	}
}
$query = "SELECT *, `awardsubjects`.`id` AS 'awardsubject', `awards`.`id` AS awardid FROM `awards`
JOIN `awardsubjects` ON `awards`.`subjectid` = `awardsubjects`.`id`
WHERE `archive` = 0
ORDER BY `awardsubjects`.`id`";
$statement = $db->prepare( $query );
$statement->execute();
$results = $statement->fetchAll( PDO::FETCH_ASSOC );

$selected_award = "<div id='".$results[0]['awardsubject']."' class='show' style='margin-left:205px; display:none; width:200px'><select form='form' name='awardid[".$results[0]['awardsubject']."]' style='width:200px !important'><option value=''>".$results[0]['subject_area']."</option>";
$currentAwardSubject = $results[0]['awardsubject'];
foreach ($results as $result){
	if ($currentAwardSubject === $result['awardsubject']){//add to current list

		$selected_award .= "<option value='".$result['awardid']."'>".$result['unit_title']."</option>";

	}
	else {//make new list
		$currentAwardSubject = $result['awardsubject'];
		$selected_award .= "</select></div>";

		$selected_award .= "<div id='".$result['awardsubject']."' class='show' style='margin-left:205px; display:none; width:200px'><select form='form' name='awardid[".$result['awardsubject']."]' style='width:200px !important'><option value=''>".$result['subject_area']."</option>";
		$selected_award .= "<option value='".$result['awardid']."'>".$result['unit_title']."</option>";

	}
}
$selected_award .= "</select></div>";

// Session planning statuses
$query = 'SELECT * FROM `activity_planning_statuses` ORDER BY `id` ASC';
$statement = $db->prepare( $query );
$statement->execute();
$results = $statement->fetchAll( PDO::FETCH_ASSOC );
$statuses = '';
foreach( $results as $result )
{
	$statuses .= '<option ';
	if( isset( $plan ) and $result['id'] === $plan['status'] ) $statuses .= 'selected="selected" ';
	$statuses .= 'value="' . $result['id'] . '">' . html( $result['label'] ) . '</option>' . PHP_EOL;
}

/*
 * End dropdown loading
 */


//tab index
if( isset( $_GET['tabindex'] ) and $_GET['tabindex'] ) $tabindex = (string) (int) $_GET['tabindex'];
else $tabindex = '0';
?>
<!DOCTYPE html>
<html>
<?php
if( !$modal ) require_once( "includes/head.php" );
?>
	<body>
<script>
//<![CDATA[

var available_awards = [];

function checkfields()
{
	if( $( 'select[name=venueid]' ).val() )
	{
		if( !$( 'select[name=vehicle_id]' ).val() )
		{
			alert( "You must select a vehicle." );
			return false;
		}
		if( !$( 'input[name=activity_risk_assessment]' ).prop( 'checked' ) )
		{
			alert( "You must acknowledge your understanding of the activity's risk assessment." );
			return false;
		}
		if( !$( 'input[name=young_person_risk_assessment]' ).prop( 'checked' ) )
		{
			alert( "You must acknowledge your understanding of the young person's risk assessment." );
			return false;
		}
		if( !$( 'input[name=vehicle_risk_assessment]' ).prop( 'checked' ) )
		{
			alert( "You must acknowledge your understanding of the vehicle's risk assessment." );
			return false;
		}
		if( $( 'select[name=pick_up_staff_id]' ).val() )
		{
			if( !$( 'select[name=pick_up_vehicle_id]' ).val() )
			{
				alert( "You must select a vehicle for pick up." );
				return false;
			}
			if( !$( 'input[name=pick_up_young_person_risk_assessment]' ).prop( 'checked' ) )
			{
				alert( "You must acknowledge your understanding of the young person's pick up risk assessment." );
				return false;
			}
			if( !$( 'input[name=pick_up_vehicle_risk_assessment]' ).prop( 'checked' ) )
			{
				alert( "You must acknowledge your understanding of the pick up vehicle's risk assessment." );
				return false;
			}
		}
	}
	$( "#message-error1" ).css( "display", "none" );
	$( "#message-error2" ).css( "display", "none" );
	$( "#message-error3" ).css( "display", "none" );
	$( "#message-error4" ).css( "display", "none" );

	return true;
}

function fetch_table( type )
{
	$.ajax(
	{
		url: "activities_table_ajax.php?type=" + type + "&action=fetch&id=<?php echo $id; ?>",
		success: function( data )
		{
			$( '#table_' + type + '_sub' ).html( data );
		}
	} );
}

<?php if( isSeniorManager() ): ?>
function delete_person(type, id)
{
	$.ajax(
	{
		url: "activities_table_ajax.php?type=" + type + "&action=delete&id=<?php echo $id; ?>&linkid=" + id,
		success: function( data )
		{
			$( '#table_' + type + '_sub' ).html( data );
			/*$.get( 'list-staff-cars-ajax.php?activity_id=<?php echo $id; ?>', function( data ) {
				$( 'select[name=vehicle_id]' ).html( data );
			} );*/
		}
	} );
}

function add_person( type )
{
	$.ajax(
	{
		url: "activities_table_ajax.php?type=" + type + "&action=add&id=<?php echo $id; ?>&linkid=" + $( "#new" + type + "id" ).val(),
		success: function( data )
		{
			$( '#table_' + type + '_sub' ).html( data );
			/*$.get( 'list-staff-cars-ajax.php?activity_id=<?php echo $id; ?>', function( data ) {
				$( 'select[name=vehicle_id]' ).html( data );
			} );*/
		}
	} );
}

function add_staff()
{
	$.ajax(
	{
		url: "activities_table_ajax.php?type=staff&action=check&id=<?php echo $id; ?>&linkid=" + $( "#newstaffid" ).val(),
		success: function( data )
		{
			returnvalue = JSON.parse( data );
			name = returnvalue['name'];
			sessions = returnvalue['sessions'];
			number_of_clashes = sessions.length;
			if( number_of_clashes > 0 )
			{
				confirmation_text = name + ' has ';
				if( number_of_clashes > 1 )
				{
					confirmation_text += 'conflicting sessions at ' + sessions[0]['datestart'].slice( -8, -3 ) + ' - ' + sessions[0]['datefinish'].slice( -8, -3 );
					for( i = 1; i < number_of_clashes - 1; ++i )
					{
						confirmation_text += ', ' + sessions[i]['datestart'].slice( -8, -3 ) + ' - ' + sessions[i]['datefinish'].slice( -8, -3 );
					}
					confirmation_text += ' and ' + sessions[number_of_clashes - 1]['datestart'].slice( -8, -3 ) + ' - ' + sessions[number_of_clashes - 1]['datefinish'].slice( -8, -3 );
				}
				else
				{
					confirmation_text += 'a conflicting session at ' + sessions[0]['datestart'].slice( -8, -3 ) + ' - ' + sessions[0]['datefinish'].slice( -8, -3 );
				}
				confirmation_text += '. Click OK to assign him/her anyway.';
				override = confirm( confirmation_text );
				if( override ) add_person( 'staff' );
			}
			else add_person( 'staff' );
		}
	} );
}
<?php endif; ?>

function delete_blank_session( redirect )
{
<?php
$redirect = 'index.php';
if( $new_session )
{
	$href = '"activities_save.php?mode=delete&id=' . (int) $id . '"';
	if( isset( $_SESSION['last_url'] ) )
	{
		$redirect = urlencode( $_SESSION['last_url'] );
	}
}
else $href = 'window.location.href';
?>
	newlocation = <?php echo $href; ?>;
	if( redirect ) newpath = redirect;
	else newpath = '<?php echo $redirect; ?>';
	window.location.href = newlocation + '&referer=' + newpath;
}

$( function ()
{
	$( '#box-tabs' ).tabs( { selected: <?php echo $tabindex; ?> } );
	$( '#box-tabs' ).bind( 'tabsshow', function( event, ui ) {
		if( ui.index == 1 )
		{
			$( '#export_buttons' ).show();
		}
		else
		{
			$( '#export_buttons' ).hide();
		}
	} );

	$( '#dialog-confirm' ).dialog(
	{
		autoOpen: false,
		resizable: false,
		height: 200,
		modal: true,
		buttons:
		{
			'Delete item': function()
			{
<?php
if( isSeniorManager() )
{
	$href = '"activities_save.php?mode=delete&id=' . (int) $id;
	if( $modal )
	{
		$href .= '&referer=" + encodeURIComponent( window.location.href )';
	}
	elseif( isset( $_SESSION['last_url'] ) )
	{
		$href .= '&referer=' . urlencode( $_SESSION['last_url'] ) . '"';
	}
	else
	{
		$href .= '"';
	}
}
else $href = 'window.location.href';
?>
				window.location.href = <?php echo $href; ?>;

			},
			'Cancel': function ()
			{
				$( this ).dialog( 'close' );
			}
		}
	});

	$( '.dialog-confirm-open' ).click( function ()
	{
		$( '#dialog-confirm' ).dialog( 'open' );
		return false;
	} );

	$( 'input[type=radio][name=planning_required]').change( function() {
		if( $( 'input[type=radio][name=planning_required]:checked' ).val() == 'N' ) $( '#session-planning-details' ).hide();
		else $( '#session-planning-details' ).show();
	} );

	$( '#award_autocomplete' ).autocomplete( {
//		source: 'award_by_curriculum_area.php?subjectid1=' + $( 'select[name=curriculum_area_1]' ).val() + '&subjectid2=' + $( 'select[name=curriculum_area_2]' ).val() + '&subjectid3=' + $( 'select[name=curriculum_area_3]' ).val(),
		source: function(request, response) {
			$.getJSON("award_by_curriculum_area.php", { subjectid1: $('select[name=curriculum_area_1]').val(), subjectid2: $('select[name=curriculum_area_2]').val(), subjectid3: $('select[name=curriculum_area_3]').val(), term: request.term }, response );
		},
		minLength: 3,
		select: function( event, ui ) {
			event.preventDefault();
			$( "#awardid" ).val( ui.item.value ); // save selected id to hidden input
			$( "#award_autocomplete" ).val( ui.item.label ); // display the selected text
			if( ui.item.value ) $( '#award_status' ).prop( 'disabled', false ); // enable the status dropdown
			unsaved_changes = true; // stop users from closing the modal dialog before saving without confirmation
		}
	} );
<?php
if( isset( $plan ) and $plan['awardid'] ):
?>
	for( i = 0; i < available_awards.length; ++i )
	{
		if( $( '#awardid' ).val() == available_awards[i].value ) $( '#award_autocomplete' ).val( available_awards[i].label );
	}
<?php
endif;
?>
	fetch_table( 'students' );
	fetch_table( 'staff' );
	fetch_table( 'providers' );

	$( 'input[name=planning_required]' ).change( function() {
		unsaved_changes = true;
	} );
	$( 'input[name=ref]' ).keyup( function() {
		unsaved_changes = true;
	} );
	$( 'select[name=curriculum_area_1]' ).change( function() {
		unsaved_changes = true;
	} );
	$( 'select[name=curriculum_area_2]' ).change( function() {
		unsaved_changes = true;
	} );
	$( 'select[name=curriculum_area_3]' ).change( function() {
		unsaved_changes = true;
	} );
	$( 'select[name=learning_focus_id]' ).change( function() {
		unsaved_changes = true;
	} );
	$( 'select[name=learning_focus_id_2]' ).change( function() {
		unsaved_changes = true;
	} );
	$( 'select[name=learning_focus_id_3]' ).change( function() {
		unsaved_changes = true;
	} );
	$( 'textarea[name=objectives]' ).keyup( function() {
		unsaved_changes = true;
	} );
	$( 'textarea[name=actions_method]' ).keyup( function() {
		unsaved_changes = true;
	} );
	$( 'textarea[name=resources]' ).keyup( function() {
		unsaved_changes = true;
	} );
	$( 'textarea[name=success_criteria]' ).keyup( function() {
		unsaved_changes = true;
	} );
	$( 'textarea[name=evaluation]' ).keyup( function() {
		unsaved_changes = true;
	} );
	$( 'textarea[name=evidence]' ).keyup( function() {
		unsaved_changes = true;
	} );
	$( 'select[name=status]' ).change( function() {
		unsaved_changes = true;
	} );
	$( 'select[name=attendance_type]' ).change( function() {
		unsaved_changes = true;
	} );
	$( 'textarea[name=notes]' ).keyup( function() {
		unsaved_changes = true;
	} );
	$('input[name="outcome"]').on('change', function() {
		$('input[name="outcome"]').not(this).prop('checked', false);
	});

	$( 'select[name=venueid]' ).on( 'change', function() {
		if( $(this).val() )
		{
			$( '#offsite_session_info' ).show();
			$( 'select[name=vehicle_id]' ).prop( 'required', true );
			$( 'input[name=activity_risk_assessment]' ).prop( 'required', true );
			$( 'input[name=young_person_risk_assessment]' ).prop( 'required', true );
			$( 'input[name=vehicle_risk_assessment]' ).prop( 'required', true );
		}
		else
		{
			$( '#offsite_session_info' ).hide();
			$( 'select[name=vehicle_id]' ).prop( 'required', false );
			$( 'input[name=activity_risk_assessment]' ).prop( 'required', false );
			$( 'input[name=young_person_risk_assessment]' ).prop( 'required', false );
			$( 'input[name=vehicle_risk_assessment]' ).prop( 'required', false );
		}
	});

	$( 'select[name=pick_up_staff_id]' ).on( 'change', function() {
		if( $(this).val() )
		{
			/*$.get( 'list-staff-cars-ajax.php?staff_id[]=' + $(this).val(), function( data ) {
				$( 'select[name=pick_up_vehicle_id]' ).html( data );
			} );*/
			$( '#different_pick_up_info' ).show();
			$( 'select[name=pick_up_vehicle_id]' ).prop( 'required', true );
			$( 'input[name=pick_up_young_person_risk_assessment]' ).prop( 'required', true );
			$( 'input[name=pick_up_vehicle_risk_assessment]' ).prop( 'required', true );
		}
		else
		{
			$( '#different_pick_up_info' ).hide();
			$( 'select[name=pick_up_vehicle_id]' ).prop( 'required', false );
			$( 'input[name=pick_up_young_person_risk_assessment]' ).prop( 'required', false );
			$( 'input[name=pick_up_vehicle_risk_assessment]' ).prop( 'required', false );
		}
	});

	//Run the divshow function when the ajax page is done loading.
	$(document).ajaxComplete(function () {
		showdiv();
	});
} );

window.onload = showdiv;

function showdiv() {
	//Hide all dive
	var elements = document.getElementsByClassName("show");
	for (var i = 0, len = elements.length; i < len; i++) {
		value = elements[i].value;
		elements[i].style.display = "none";
	}
	//get selected div id and then show the dropdown
	var elements = document.getElementsByClassName("curriculum_area");
	for (var i = 0, len = elements.length; i < len; i++) {
		if(document.getElementById(elements[i].value) != null){
			document.getElementById(elements[i].value).style.display = "block";
		}
	}
}
//]]>
</script>
<?php if( isSeniorManager() ): ?>
		<div id="dialog-confirm" title="Delete this record">
			<p><span class="ui-icon ui-icon-alert" style="float:left; margin:0 7px 20px 0;"></span>Deleting will be permanent, are you sure?</p>
		</div>
<?php
endif;
if( isStaff() ):
?>
	<div id="export_buttons" style='<?php if( $tabindex !== '1' ) echo "display:none; ";?>z-index:3; position:absolute; <?php if($modal) echo 'top:8em; left:70%;'; else echo 'top:21em; left:70%;'; ?>'>
		<form id="pdf_form" style='display: inline-block;' target="_blank" class="hidefromprint" method='post' action="pdf.php">
			<input type='hidden' name='html' id='pdf-content' />
			<input type='hidden' name='layout' value='landscape' />
			<input type="hidden" name="filename" value="<?php echo html( $student['name'] /*BUG This is causing an error, $students does not appear to be an array... */); ?> Session Planning Report <?php echo date( 'd-m-Y' ); ?>" />
			<a href='#' id='pdf-export' title='Export to PDF (warning: this may take a long time)'>
				<img style='vertical-align: middle;' alt="PDF icon" src="resources/images/icons/pdf.png" />
				Export to PDF
			</a>
		</form>
	</div>
<?php
endif;
if( !$modal ):
require_once( 'includes/header.php' );
?>

			<!-- content -->
			<div id="content">
<?php
require_once( 'includes/menu_left_activities.php' );
?>
				<!-- content / right -->
	<div id="right">
<?php
else:
?>
<div id="content" class="modal">
<?php
endif; // modal
?>
	<form id="form" action="activities_save.php<?php if( isset( $_SERVER['last_url']) ) echo '?referer=' . urlencode( $_SESSION['last_url'] ); ?>" method="post" onsubmit="return checkfields()">
</form>

	<!-- forms -->

	<div id="box-tabs" class="box">
		<div class="title">
			<h5>Edit Session</h5>
			<ul class="links">
<?php if( isStaff() ): ?>
				<li><a href="#box-details">Session Details</a></li>
<?php endif; ?>
				<li><a href="#box-notes">Planning</a></li>
<?php if( isStaff() ): ?>
				<li><a href="#box-POS">POS</a></li>
				<li><a href="#box-attendance">Attendance</a></li>
<?php if (!$new_session && !$child_session): ?>
				<li><a href="#box-reschedule">Reschedule</a></li>
<?php endif; ?>
<?php endif; ?>
			</ul>
		</div>
<?php if( isStaff() ): ?>
		<div id="box-details">
			<div class="messages">
				<div id="message-error1"  style="display:none;" class="message message-error">
					<div class="image"><img src="resources/images/icons/error.png" alt="Error" height="32" /></div>
					<div class="text"><h6>Error</h6><span>Please fill in all required fields.*</span></div>
					<div class="dismiss"><a href="#message-error"></a></div>
				</div>
			</div>

			<h6>Session Details</h6>
			<div class="form">
				<div class="fields">

<?php
//add the link to the parent session if it exists.
		$rescheduled = '';
		$query = "SELECT `parent_session` FROM `activities` WHERE `id` = " . $id;
		$statement = $db->prepare( $query );
		$statement->execute();
		$results = $statement->fetchAll( PDO::FETCH_ASSOC);
		if ($results[0]['parent_session'] !== NULL)
		{
			$rescheduled .= '<div class="label"><a href="activities_edit.php?id=';
			$rescheduled .= $results[0]['parent_session'];
			$rescheduled .= '"><strong>Click here for the previous missed session</strong></a></div>';
		}
	$query = 'SELECT `id` FROM `activities` where `parent_session` = :id';
	$statement = $db->prepare( $query );
	$statement->bindParam( ':id', $id, PDO::PARAM_INT );
	$statement->execute();
	$results = $statement->fetchAll( PDO::FETCH_ASSOC );
	if (count($results))
	{
		$rescheduled .= '<div class="label"><a href="activities_edit.php?id=';
		$rescheduled .= $results[0]['id'];
		$rescheduled .= '"><strong>Click here for the rescheduled version of this session</strong></a></div>';
	}
	echo($rescheduled);

?>

					<div class="field">
						<div class="label">
							<label>Student:</label>
						</div>
						<div class="input">
<?php
$student_names = "";
if( isset( $studentid ) and (int) $studentid ) $query = 'SELECT `firstname`, `lastname` FROM `students` WHERE `id` = ' . (int) $studentid;
else $query = 'SELECT `students`.`id`, `students`.`firstname` AS `firstname`, `students`.`lastname` AS `lastname` FROM `students`, `activitystudents` WHERE `activitystudents`.`studentid` = `students`.`id` AND `activitystudents`.`activityid` = ' . (int) $id;
$results = array();
foreach( $db->query( $query ) as $result ) $results[] = $result;
for( $i = 0; $i < count( $results ); ++$i )
{
	$student_names .= $results[$i]['firstname'] . ' ' . $results[$i]['lastname'];
	echo '<p>' . html( $results[$i]['firstname'] . ' ' . $results[$i]['lastname'] );
	if( $i < count( $results ) - 1 ) echo ',&nbsp;';
	echo '</p>' . PHP_EOL;
	if( !$studentid ) $studentid = (int) $results[$i]['id'];
}
?>
						</div>
					</div>
					<div class="field">
						<div class="label">
							<label>Session Type:</label>
						</div>
						<div class="input">
<?php
echo $activitytype;
if( isSeniorManager() ):
?>
							<p style="float:right;">&nbsp;&nbsp;&nbsp;Click <a href="<?php if( $new_session ) echo "javascript:delete_blank_session('activitytypes.php')"; else echo 'activitytypes.php'; ?>">here</a> to edit or create Session Types</p>
<?php
endif;
?>
						</div>
					</div>

<?php if( isSeniorManager() ): // Only admins can reschedule
?>
				<div class="field">
					<div class="label">
						<label>Date From:</label>
					</div>
					<div class="input">
						<input form="form" type="text" id="fromdate" name="fromdate" class="date"  value="<?php echo $fromdate; ?>" /><p style="margin: 0px 5px;">yyyy-mm-dd</p>
					</div>
				</div>

				<div class="field">
					<div class="label">
						<label>Time From:</label>
					</div>
					<div class="input">
						<input form="form" type="text" id="fromtime" name="fromtime" class="timepicker"  value="<?php echo $fromtime; ?>" /><p style="margin: 0px 5px;">hh:mm, leave blank for all day</p>
					</div>
				</div>
				<div class="field">
					<div class="label">
						<label>Date To:</label>
					</div>
					<div class="input">
						<input form="form"  type="text" id="todate" name="todate" class="date" value="<?php echo $todate; ?>" /><p style="margin: 0px 5px;">yyyy-mm-dd</p>
					</div>
				</div>

				<div class="field">
					<div class="label">
						<label>Time To:</label>
					</div>
					<div class="input">
						<input form="form" type="text" id="totime" name="totime" class="timepicker"  value="<?php echo $totime; ?>" /><p style="margin: 0px 5px;">hh:mm, leave blank for all day</p>
					</div>
				</div>

				<div class="field">
					<div class="label">
						<label>Font Size:</label>
					</div>
					<div class="input">
						<?php echo generate_fontsize_select( $fontsize ); ?>
					</div>
				</div>

<?php
endif; // end if admin
?>
				</div>
			</div>
<?php if( isStaff() ): ?>
			<div id="box-staff">
				<div class="messages">
					<div id="message-error5"  style="display:none;" class="message message-error">
						<div class="image"><img src="resources/images/icons/error.png" alt="Error" height="32" /></div>
						<div class="text"><h6>Error</h6><span>Please fill in all required fields.*</span></div>
						<div class="dismiss"><a href="#message-error"></a></div>
					</div>
				</div>
				<h6 style="margin-left:20px;">Engaging Potential staff assigned to this session</h6>
				<div class="table">
					<div id="table_staff_sub">
						<table id="icons">
						</table>
					</div>
					<p style="line-height: 5px;">&nbsp;</p>
					<div style="float:left;width:500px;"><?php echo $staffdropdown; ?></div>
<?php if( isSeniorManager() ): ?>
					<div style="padding:8px;" class="add"><a href="javascript:add_staff()">Add this staff member</a></div>
<?php endif; ?>
					<p>&nbsp;</p>
				</div>
			</div>

			<div id="assign-exterior">
				<div id="box-exterior">
					<div class="messages">
						<div id="message-error7" style="display:none;" class="message message-error">
							<div class="image"><img src="resources/images/icons/error.png" alt="Error" height="32" /></div>
							<div class="text"><h6>Error</h6><span>Please fill in all required fields.*</span></div>
							<div class="dismiss"><a href="#message-error"></a></div>
						</div>
					</div>
					<h6 style="margin-left:20px;">Service providers assigned to this session</h6>
					<div class="table">
						<div id="table_providers_sub">
							<table id="icons">
							</table>
						</div>
						<p style="line-height: 5px;">&nbsp;</p>
						<div style="float:left;width:192px;"><?php echo $externaldropdown; ?></div>
<?php if( isSeniorManager() ): ?>
						<div style="padding:8px;" class="add"><a href="javascript:add_person( 'providers' )">Add this Service Provider</a></div>
<?php endif; ?>
						<p>&nbsp;</p>
					</div>
				</div>
			</div>
<?php endif; ?>
		</div><!-- end box-details -->
<?php endif; ?>
		<div id="box-notes">
			<div class="messages">
				<div id="message-error2"  style="display:none;" class="message message-error">
					<div class="image"><img src="resources/images/icons/error.png" alt="Error" height="32" /></div>
					<div class="text"><h6>Error</h6><span>Please fill in all required fields.*</span></div>
					<div class="dismiss"><a href="#message-error"></a></div>
				</div>
			</div>

			<h6>Session Planning</h6>
			<div class="form">
<?php  if( $planning_required == 'N' and !isStaff() )
{ 
	echo 'Planning is not available for this session';
}
?>
				<p style="margin-left: 6px;"> Session Time:
<?php
if( isset( $fromdate ) ) echo date("d/m/Y", strtotime($fromdate));
echo " ";
if (isset( $fromtime ) ) echo $fromtime;
echo "-";
if ( isset( $totime ) ) echo $totime;
?>
				</p>
<?php
if( isset( $plan ) and isSeniorManager() ) echo '<p style="margin-left: 6px;">Planning was last updated by ' . html( $plan['staff'] ) . ' at ' . date( 'H:i', strtotime( $plan['modification_time'] ) ) . ' on ' . date( 'l F j, Y', strtotime( $plan['modification_time'] ) ) . '.</p>';

if( isStaff() ):
if( isset( $studentid ) and (int) $studentid ):
?>			<p style="margin-left: 6px;">To view previous session plans for this student, <a title='Opens in a new tab' target='_blank' href='planning-report.php?studentid=<?php echo $studentid; ?>'>click here</a>.</p>
<?php
endif; 
endif;
?>
				<div class="fields">
					<div class="fields">
<?php if( isStaff() ): ?>
						<div class="field">
							<div class="label">
								<label>Planning required:</label>
							</div>
							<div class="input">
								<label style="float: left;"><input form="form" type="radio" id="planning_required_y" name="planning_required"<?php if( $planning_required !== 'N' ) echo ' checked="checked"'; ?> value="Y" /> Y</label>
								<label><input form="form" style="margin-left: 0.5em;" type="radio" id="planning_required_n" name="planning_required"<?php if( $planning_required === 'N' ) echo ' checked="checked"'; ?> value="N" /> N</label>
							</div>
						</div>
<?php endif; ?>
						<div id="session-planning-details"<?php if( $planning_required === 'N' ) echo ' style="display: none;"'; ?>>
<?php if( isStaff() ): ?>
							<div class="field">
								<div class="label">
									<label>Ref:</label>
								</div>
								<div class="input">
									<input form="form" type='text' name='ref'<?php if( isset( $plan ) ) echo ' value="' . html( $plan['ref'] ) . '"'; ?> />
								</div>
							</div>
							<div class="field">
								<div class="label">
									<label>Curriculum areas:</label>
								</div>
								<div class="input">
									<select form="form" class='curriculum_area' name='curriculum_area_1' style='width: 200px;' onchange='showdiv()'>
										<option value=''>None</option>
										<?php echo $curriculum_areas[1]; ?>
									</select><br />
									<select form="form" class='curriculum_area' name='curriculum_area_2' style='width: 200px;' onchange='showdiv()'>
										<option value=''>None</option>
										<?php echo $curriculum_areas[2]; ?>
									</select><br />
									<select form="form" class='curriculum_area' name='curriculum_area_3' style='width: 200px;' onchange='showdiv()'>
										<option value=''>None</option>
										<?php echo $curriculum_areas[3]; ?>
									</select>
								</div>
							</div>
<?php endif; ?> 
							<div class="field">
								<div class="label">
									<label>Learning Focus:</label>
								</div>
<?php 
//Learning Focus 
$learning_focus = '<select form="form" id="learning_focus_id" name="learning_focus_id">' . PHP_EOL;
$learning_focus .= '<option value="">None</option>' . PHP_EOL;
$query = "SELECT *, ( SELECT COUNT(*) FROM `activity_planning` WHERE `activity_learning_focus`.`id` = `activity_planning`.`learning_focus_id` ) AS `num_activities_with_focus` FROM `activity_learning_focus` WHERE `id` > 0 ORDER BY `name` ASC, `num_activities_with_focus` DESC";
foreach( $db->query( $query ) as $row )
{
	if( $learning_focus_id == $row['id'] ) $learning_focus .= '<option selected="selected" value="' . $row['id'] . '">' . $row['name'] . '</option>';
	else $learning_focus .= '<option value="' . $row['id'] . '">' . str_replace( ' ', '&nbsp;', html( $row['name'] ) ) . '</option>';
	$learning_focus .= PHP_EOL;
}
$learning_focus .= '</select><br />' . PHP_EOL;

$learning_focus_2 = '<select form="form" id="learning_focus_id_2" name="learning_focus_id_2">' . PHP_EOL;
$learning_focus_2 .= '<option>None</option>' . PHP_EOL;
$query = "SELECT *, ( SELECT COUNT(*) FROM `activity_planning` WHERE `activity_learning_focus`.`id` = `activity_planning`.`learning_focus_id_2` ) AS `num_activities_with_focus` FROM `activity_learning_focus` WHERE `id` > 0 ORDER BY `name` ASC, `num_activities_with_focus` DESC";
foreach( $db->query( $query ) as $row )
{
	if( $learning_focus_id_2 == $row['id'] ) $learning_focus_2 .= '<option selected="selected" value="' . $row['id'] . '">' . $row['name'] . '</option>';
	else $learning_focus_2 .= '<option value="' . $row['id'] . '">' . str_replace( ' ', '&nbsp;', html( $row['name'] ) ) . '</option>';
	$learning_focus_2 .= PHP_EOL;
}
$learning_focus_2 .= '</select><br />' . PHP_EOL;

$learning_focus_3 = '<select form="form" id="learning_focus_id_3" name="learning_focus_id_3">' . PHP_EOL;
$learning_focus_3 .= '<option>None</option>' . PHP_EOL;
$query = "SELECT *, ( SELECT COUNT(*) FROM `activity_planning` WHERE `activity_learning_focus`.`id` = `activity_planning`.`learning_focus_id_3` ) AS `num_activities_with_focus` FROM `activity_learning_focus` WHERE `id` > 0 ORDER BY `name` ASC, `num_activities_with_focus` DESC";
foreach( $db->query( $query ) as $row )
{
	if( $learning_focus_id_3 == $row['id'] ) $learning_focus_3 .= '<option selected="selected" value="' . $row['id'] . '">' . $row['name'] . '</option>';
	else $learning_focus_3 .= '<option value="' . $row['id'] . '">' . str_replace( ' ', '&nbsp;', html( $row['name'] ) ) . '</option>';
	$learning_focus_3 .= PHP_EOL;
}
$learning_focus_3 .= '</select>' . PHP_EOL;
?>
 							<div class="input">
<?php
echo $learning_focus;
echo $learning_focus_2;
echo $learning_focus_3;
if( isSeniorManager() ):
?>
							<p style="float:right;">&nbsp;&nbsp;&nbsp;Click <a href="<?php if( $new_session ) echo "javascript:delete_blank_session('learning-focus.php')"; else echo 'learning-focus.php'; ?>">here</a> to edit or create a new Learning Focus</p>
<?php
endif;
?>
							</div>
<!--							<div class="textarea textarea-editor">
									<textarea form="form" id="learning_focus" name="learning_focus" style="height:55px;width:450px;"><?php if( isset( $plan ) ) echo html( $plan['learning_focus'] ); ?></textarea>
								</div> -->
							</div>
							<div class="field">
								<div class="label">
									<label>Objectives:</label>
									<p>
										What I hope to achieve with this YP in this session?
									</p>
								</div>
								<div class="textarea textarea-editor">
									<textarea form="form" id="objectives" name="objectives" style="height:55px;width:450px;"><?php if( isset( $plan ) ) echo html( $plan['objectives'] ); ?></textarea>
								</div>
							</div>
							<div class="field">
								<div class="label">
									<label>Actions/Method:</label>
									<p>
										How will the session be run?
									</p>
								</div>
								<div class="textarea textarea-editor">
									<textarea form="form" id="actions_method" name="actions_method" style="height:55px;width:450px;"><?php if( isset( $plan ) ) echo html( $plan['actions_method'] ); ?></textarea>
								</div>
							</div>
							<div class="field">
								<div class="label">
									<label>Resources:</label>
									<p>
										What will I need/use for a successful session?
									</p>
								</div>
								<div class="textarea textarea-editor">
									<textarea form="form" id="resources" name="resources" style="height:55px;width:450px;"><?php if( isset( $plan ) ) echo html( $plan['resources'] ); ?></textarea>
								</div>
							</div>
							<div class="field">
								<div class="label">
									<label>Success Criteria:</label>
									<p>
										How will I measure that my objective has been achieved?
									</p>
								</div>
								<div class="textarea textarea-editor">
									<textarea form="form" id="success_criteria" name="success_criteria" style="height:55px;width:450px;"><?php if( isset( $plan ) ) echo html( $plan['success_criteria'] ); ?></textarea>
								</div>
							</div>
							<div class="field">
								<div class="label">
									<label>Evaluation:</label>
									<p>
										What happened? What worked well and why or not.
									</p>
								</div>
								<div class="textarea textarea-editor">
									<textarea form="form" id="evaluation" name="evaluation" style="height:55px;width:450px;"><?php if( isset( $plan ) ) echo html( $plan['evaluation'] ); ?></textarea>
								</div>
							</div>
							<div class="field">
								<div class="label">
									<label>Evidence:</label>
									<p>
										Where can we find it? Folders portfolio etc, Hard Evidence, Soft Evidence
									</p>
								</div>
								<div class="textarea textarea-editor">
									<textarea form="form" id="evidence" name="evidence" style="height:55px;width:450px;"><?php if( isset( $plan ) ) echo html( $plan['evidence'] ); ?></textarea>
								</div>
							</div>

							<div class="field">
								<div class="label">
									<label>Outcome</label>
								</div>
								<div class="input">
									<label>
										<input form="form" type="checkbox" name="outcome" value="<?php echo COLOUR_GREEN; ?>"<?php if( $plan['outcome'] === COLOUR_GREEN ) echo " checked='checked'"; ?> />&nbsp;
										<span style="background-color: <?php echo COLOUR_GREEN;?>; height: 16px; width: 16px; display: inline-block; cursor: pointer;"></span>
									</label>
									<br />
									<label>
										<input form="form" type="checkbox" name="outcome" value="<?php echo COLOUR_AMBER; ?>"<?php if( $plan['outcome'] === COLOUR_AMBER ) echo " checked='checked'"; ?> />&nbsp;
										<span style="background-color: <?php echo COLOUR_AMBER;?>; height: 16px; width: 16px; display: inline-block; cursor: pointer;"></span>
									</label>
									<br />
									<label>
										<input form="form" type="checkbox" name="outcome" value="<?php echo COLOUR_RED; ?>"<?php if( $plan['outcome'] === COLOUR_RED ) echo " checked='checked'"; ?> />&nbsp;
										<span style="background-color: <?php echo COLOUR_RED;?>; height: 16px; width: 16px; display: inline-block; cursor: pointer;"></span>
									</label>
								</div>
							</div>
<?php if( isStaff() ): ?>
							<div class="field">
								<div class="label">
									<label>Award Auto Select:</label>
								</div>
								<div class="input">
-                                <input form="form" type='text' id='award_autocomplete' name='award_autocomplete' style="width: 450px;" placeholder='None' 
<?php 
/* Displays the chosen auto select award */
if( isset( $plan['awardid'] ) and $plan['awardid'] )
{
	$subquery = 'SELECT * FROM `awards` WHERE `archive` = 0 AND `id` = :awardid';
   $statement = $db->prepare( $subquery );
   $statement->bindParam( ':awardid', $plan['awardid'], PDO::PARAM_STR );
   $statement->execute();
   $subresult = $statement->fetch();
   $award_label = $subresult['unit_title'];
   if( $subresult['unit_number'] ) $award_label .= ' ' . $subresult['unit_number'];
   $award_label .= ' (' . $subresult['award_title'];
   if( $subresult['level'] and $subresult['level'] !== 'N/A' ) $award_label .= ' ' . $subresult['level'];
   $award_label .= ')';
   if( $subresult['awarding_body'] ) $award_label .= ' ' . $subresult['awarding_body'];
   $award_label = trim( $award_label );
   if( $award_label[0] === '(' )
		{
		$award_label = substr( $award_label, 1, mb_strlen( $award_label ) - 2 );
		}
$award_label = str_replace( "\t", '', $award_label ); 
echo 'value="' . $award_label . '"';
}
?>
/>
									<input form="form" type='hidden' id='awardid' name='auto_awardid' <?php if( isset( $plan ) ) echo ' value="' . html( $plan['awardid'] ) . '"'; ?> />
								</div>
							</div>

							<div class="field">
								<div class="label">
									<label>Award Category:</label>
									<p>Please only select 1 Award</p>
								</div>
								<div>
								    <?php echo $selected_award; ?>
								</div>
							</div>

							<div class="field">
								<div class="label">
									<label>Award Status:</label>
								</div>
								<div class="input">
									<select form="form" name='status' id='award_status' style='width: 120px;'<?php if( !isset( $plan ) or !$plan['awardid'] ) echo ' disabled="disabled"'; ?>>
										<?php echo $statuses; ?>
									</select>
								</div>
							</div>

							<div class="field">
								<div class="label">
									<label>Location:</label>
								</div>
								<div class="input">
									<?php echo $venues; ?>
<?php if( isSeniorManager() ): ?>
									<p style="float:right;">&nbsp;&nbsp;&nbsp;Click <a href="contacts.php?type=venues">here</a> to edit or create Locations</p>
<?php endif; ?>
								</div>
							</div>

							<div id="offsite_session_info"<?php if( !$venueid ) echo ' style="display:none;"'; ?>>
								<div class="field">
									<div class="label">
										<label>Vehicle*:</label>
									</div>
									<div class="input">
										<select form="form" name="vehicle_id"<?php if( $venueid ) echo ' required="required"'; ?> style="width:15em;">
											<option></option>
<?php
$query = "SELECT `id`, CONCAT( `registration`, ' (Company car)' ) AS `registration` FROM `vehicles`
WHERE `id` NOT IN ( SELECT `vehicle_id` FROM `staff_vehicles` UNION SELECT `vehicle_id` FROM `providers_vehicles` )
UNION SELECT `vehicles`.`id`, CONCAT( `vehicles`.`registration`, ' (', `staff`.`displayname`, ')' ) AS `registration`
FROM `vehicles`, `staff_vehicles`, `staff`
WHERE `vehicles`.`id` = `staff_vehicles`.`vehicle_id`
AND `staff_vehicles`.`staff_id` = `staff`.`id`
AND ( `staff`.`finishdate` = '0000-00-00' OR `staff`.`finishdate` > NOW() )
UNION SELECT `vehicles`.`id`, CONCAT( `vehicles`.`registration`, ' (', `providers`.`contactname`, ')' ) AS `registration`
FROM `vehicles`, `providers_vehicles`, `providers`
WHERE `vehicles`.`id` = `providers_vehicles`.`vehicle_id`
AND `providers_vehicles`.`provider_id` = `providers`.`id`
AND ( `providers`.`finishdate` = '0000-00-00' OR `providers`.`finishdate` > NOW() )
";
$statement = $db->prepare( $query );
$statement->execute();
$vehicles = $statement->fetchAll( PDO::FETCH_ASSOC );
foreach( $vehicles as $vehicle )
{
	echo '<option';
	if( $vehicle_id == $vehicle['id'] ) echo ' selected="selected"';
	echo ' value="' . $vehicle['id'] . '">' . html( $vehicle['registration'] ) . "</option>\n";
}
unset( $vehicle );
?>
										</select>
									</div>
								</div>

								<div class="field">
									<div class="label">
										<label>Activity RA*:</label>
									</div>
									<div class="input">
										<label><input form="form" type="checkbox" name="activity_risk_assessment" id="activity_risk_assessment" <?php if( $venueid ) echo ' required="required"'; if( $activity_risk_assessment ) echo ' checked="checked"'; ?> />&nbsp;I have read and understood</label>
									</div>
								</div>

								<div class="field">
									<div class="label">
										<label>Young Person RA*:</label>
									</div>
									<div class="input">
										<label><input form="form" type="checkbox" name="young_person_risk_assessment" id="young_person_risk_assessment" <?php if( $venueid ) echo ' required="required"'; if( $young_person_risk_assessment ) echo ' checked="checked"'; ?> />&nbsp;I have read and understood</label>
									</div>
								</div>

								<div class="field">
									<div class="label">
										<label>Vehicle RA*:</label>
									</div>
									<div class="input">
										<label><input form="form" type="checkbox" name="vehicle_risk_assessment" id="vehicle_risk_assessment" <?php if( $venueid ) echo ' required="required"'; if( $vehicle_risk_assessment ) echo ' checked="checked"'; ?> />&nbsp;I have read and understood</label>
									</div>
								</div>

								<div class="field">
									<div class="label">
										<label>Itinerary (if required):</label>
									</div>
									<div class="input">
										<label><input form="form" type="checkbox" name="itinerary_if_required" id="itinerary_if_required"<?php if( $itinerary_if_required ) echo ' checked="checked"'; ?> /></label>
									</div>
								</div>

								<div class="field">
									<div class="label">
										<label>Pick Up (if different):</label>
									</div>
									<div class="input">
										<select form="form" name="pick_up_staff_id" style="width:15em;">
											<option></option>
<?php
// Change requested by Paul 16/05/2019 to make pickup vehicle possible to be the staff member currently logged in.
// $query = "SELECT `id`, `displayname` FROM `staff` WHERE `id` <> $admin_id AND ( `finishdate` = '0000-00-00' OR `finishdate` > NOW() ) ORDER BY `displayname`";
$query = "SELECT `id`, `displayname` FROM `staff` WHERE ( `finishdate` = '0000-00-00' OR `finishdate` > NOW() ) ORDER BY `displayname`";
$statement = $db->prepare( $query );
$statement->execute();
$pick_up_staff = $statement->fetchAll( PDO::FETCH_ASSOC );
foreach( $pick_up_staff as $pick_up_staff_member )
{
	echo '<option';
	if( $pick_up_staff_id == $pick_up_staff_member['id'] ) echo ' selected="selected"';
	echo ' value="' . $pick_up_staff_member['id'] . '">' . html( $pick_up_staff_member['displayname'] ) . "</option>\n";
}
?>
										</select>
									</div>
								</div>

								<div id="different_pick_up_info"<?php if( !$pick_up_staff_id ) echo ' style="display:none;"'; ?>>
									<div class="field">
										<div class="label">
											<label>Pick Up Vehicle*:</label>
										</div>
										<div class="input">
											<select form="form" name="pick_up_vehicle_id" style="width:15em;"<?php if( $pick_up_staff_id ) echo ' required="required"'; ?>>
												<option></option>
<?php
foreach( $vehicles as $vehicle )
{
	echo '<option';
	if( $pick_up_vehicle_id == $vehicle['id'] ) echo ' selected="selected"';
	echo ' value="' . $vehicle['id'] . '">' . html( $vehicle['registration'] ) . "</option>\n";
}
?>
											</select>
										</div>
									</div>

									<div class="field">
										<div class="label">
											<label>Pick Up Young Person RA*:</label>
										</div>
										<div class="input">
											<label><input form="form" type="checkbox" name="pick_up_young_person_risk_assessment" id="pick_up_young_person_risk_assessment" <?php if( $pick_up_staff_id ) echo ' required="required"'; if( $pick_up_young_person_risk_assessment ) echo ' checked="checked"'; ?> />&nbsp;I have read and understood</label>
										</div>
									</div>

									<div class="field">
										<div class="label">
											<label>Pick Up Vehicle RA*:</label>
										</div>
										<div class="input">
											<label><input form="form" type="checkbox" name="pick_up_vehicle_risk_assessment" id="pick_up_vehicle_risk_assessment"<?php if( $pick_up_staff_id ) echo ' required="required"'; if( $pick_up_vehicle_risk_assessment ) echo ' checked="checked"'; ?> />&nbsp;I have read and understood</label>
										</div>
									</div>

									<div class="field">
										<div class="label">
											<label>Pick Up Itinerary (if required):</label>
										</div>
										<div class="input">
											<label><input form="form" type="checkbox" name="pick_up_itinerary_if_required" id="pick_up_itinerary_if_required"<?php if( $pick_up_itinerary_if_required ) echo ' checked="checked"'; ?> /></label>
										</div>
									</div>
								</div>
							</div>
<?php endif; ?>
						</div><!-- session-planning-details -->
					</div>
				</div>
			</div>
		</div><!-- box-notes -->
<?php if( isStaff() ): ?>
    <div id="box-POS">
      <div class="form">

        <div class="field">
          <div class="label">
            <label>Brief Description of the Activity or Situation:</label>
            <p>
              Please provide a description of the activity.
            </p>
          </div>
          <div class="textarea textarea-editor">
            <textarea form="form" id="brief_description" name="brief_description" style="height:55px;width:450px;"><?php if( isset( $plan ) ) echo html( $plan['brief_description'] ); ?></textarea>
          </div>
        </div>

        <div class="field">
          <div class="label">
            <label>What was so good?:</label>
            <p>
              What made this activity good?
            </p>
          </div>
          <div class="textarea textarea-editor">
            <textarea form="form" id="what_was_good" name="what_was_good" style="height:55px;width:450px;"><?php if( isset( $plan ) ) echo html( $plan['what_was_good'] ); ?></textarea>
          </div>
        </div>

        <div class="field">
          <div class="label">
            <label>Why is this Development:</label>
            <p>
              What elements of the activity helped the young person develop?
            </p>
          </div>
          <div class="textarea textarea-editor">
            <textarea form="form" id="development" name="development" style="height:55px;width:450px;"><?php if( isset( $plan ) ) echo html( $plan['development'] ); ?></textarea>
          </div>
        </div>

      </div>
    </div>
		<div id="box-attendance">
			<div class="form">
				<p style="margin-left: 6px;"> Session Time:
<?php
if( isset( $fromdate ) ) echo date("d/m/Y", strtotime($fromdate));
echo " ";
if (isset( $fromtime ) ) echo $fromtime;
echo "-";
if ( isset( $totime ) ) echo $totime;
?>
				</p>
				<div class="fields">
					<div class="field">
						<div class="label label-radio">
							<label>Attended:</label>
						</div>
<?php
// Only permitted to set as attended after session has started, for obvious reasons.
$disabled = ( strtotime( date( 'Y-m-d H:i:s' ) ) < strtotime( $fromdate . ' ' . $fromtime ) ) ? ' disabled="disabled"' : '';
?>
						<div>
							<select form="form" <?php echo $disabled; ?> name="attendance_type">
								<option disabled="disabled"<?php if( !(int) $attendance_type ) echo ' selected="selected"'; ?>>Select...</option>

<?php
$query = "SELECT * FROM attendance_types";
$attendance_types = "";
foreach( $db->query( $query ) as $result )
{
	echo '<option value="' . $result["id"] . '"';
	if( (int) $attendance_type === (int) $result["id"] ) echo " selected='selected'";
	echo '>' . html( $result["description"] ) . '</option>' . PHP_EOL;
}
?>
							</select>
						</div>
<?php if( $disabled ) echo '<p style="position: relative; left: 10px;">This session cannot be marked as attended as it is in the future.</p>' ?>
					</div>
					<div class="field">
						<div class="label label-radio">
							<label>Time Attended:</label>
						</div>
<?php
// Only permitted to set as attended after session has started, for obvious reasons.
$disabled = ( strtotime( date( 'Y-m-d H:i:s' ) ) < strtotime( $fromdate . ' ' . $fromtime ) ) ? ' disabled="disabled"' : '';
?>
					<div>
							<input form="form" <?php echo $disabled; ?> value="<?php echo $time_attended; ?>" name="time_attended" type="number" min="0" step="5" max="<?php echo get_time_diff(strtotime( $fromdate . ' ' . $fromtime ), strtotime($todate . ' ' . $totime ), "minute" ); ?>" />
					</div>
					<div class="field">
						<div class="label">
							<label>Attendance Notes:</label>
						</div>
						<div class="textarea textarea-editor">
							<textarea form="form" id="notes" name="notes" style="height: 250px; width: 450px;"<?php echo $disabled; ?>><?php echo $notes; ?></textarea>
						</div>
<?php if( $disabled ) echo '<p style="position: relative; left: 10px;">Attendance notes for this session cannot be entered as it is in the future.</p>' ?>
					</div>
				</div>
			</div>
		</div>


	<!-- End of attendance Tab -->
	<!-- end forms -->

</div>
<?php if (!$new_session && !$child_session): ?>
<div id="box-reschedule">
<form id="rescheduleform" action="missed_session.php" method="get" >
	<input type="hidden" name="sessionid" value="<?php echo $id; ?>" form="rescheduleform" />
	<input type="hidden" name="studentid" value="<?php echo $studentid; ?>" form="rescheduleform" />
	&nbsp;&nbsp; <p><strong>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Start Date: &nbsp;&nbsp;
	<input type="text" id="missedfromdate" name="fromdate" class="date" value="<?php echo $fromdate; ?>" form="rescheduleform" /></strong></p>
	&nbsp;&nbsp; <p><strong>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Start Time:&nbsp;&nbsp;&nbsp;
	<input type="text" id="missedfromtime" name="fromtime" class="timepicker"  value="<?php echo $fromtime; ?>" form="rescheduleform" /></strong></p>
	&nbsp;&nbsp; <p><strong>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Finish Date:
	<input type="text" id="missedtodate" name="todate" class="date" value="<?php echo $todate; ?>" form="rescheduleform" /></strong></p>
	&nbsp;&nbsp; <p><strong>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Finish Time:
	<input type="text" id="missedtotime" name="totime" class="timepicker"  value="<?php echo $totime; ?>" form="rescheduleform" /></strong></p>
	<input type='submit' name="action" value="Reschedule" form="rescheduleform" />
</form>
</div>
<?php endif; ?>
<?php endif; ?>



<div class="form" id="main_form_buttons">
	<div class="fields" >
		<div class="buttons">
<?php
if( isSeniorManager() )
{
if( empty( $id ) ):
?>
			<input form="form" type="hidden" name="studentid" value="<?php echo $id; ?>" id="id" />
			<input form="form" type="hidden" name="activityid" value="<?php echo $id; ?>" id="id" />
<?php
endif; // empty( $id )
?>
			<input form="form" class="dialog-confirm-open" type="button" name="submit"  value="Delete" />
<?php
} // isSeniorManager()
if( !$modal )
{
if( $new_session ) // If a new session was created, Cancel needs to delete it.
{
?>&nbsp;&nbsp;&nbsp;


			<input form="form" type="button" name="submit" onclick="delete_blank_session()" value="Cancel" />
<?php
}
else
{
?>&nbsp;&nbsp;&nbsp;
			<input form="form" type="button" name="submit" onclick="history.back();" value="Cancel" />
<?php
}
} // !$modal
if( ( ( $planning_required == 'N' or $planning_required == '' ) and isStaff() ) or $planning_required == 'Y' )
{
?>&nbsp;&nbsp;&nbsp;
			<div class="highlight">
				<input form="form" type="submit" name="submit" value="Save changes" onclick="unsaved_changes = false;" />
				&nbsp;&nbsp;
				<input form="form" type="hidden" name="id" value="<?php echo $id; ?>" id="id" />
<?php
} 
if( isset( $_GET['return_url'] ) and trim( $_GET['return_url'] ) ):
?>
				<input form="form" type="hidden" name="referer" value="<?php echo html( base64_decode( $_GET['return_url'] ) ); ?>" />
<?php
elseif( !$modal and $id ):
?>
				<input form="form" type="hidden" name="referer" value="activities_edit.php?id=<?php echo $id; if( $tabindex ) echo '&amp;tabindex=' . html( $tabindex ); ?>" />
<?php
else:
?>
				<input form="form" type="hidden" name="referer" value="timetable.php?d=<?php echo $fromdate; ?>" />
<?php
endif;
?>
			</div>
		</div>
	</div>
</div>
</div>
</div>
<!-- end content / right -->
<?php
if( !$modal ):
?>
		</div>

		<!-- end content -->
<?php
require_once( 'includes/footer.php' );
?>
	</div>
		<script>
		//<![CDATA[
		$( function()
		{
			$( '#pdf-export' ).click( function( e ) {
				e.preventDefault();
				content = '<!doctype html><html><head><title>Session Plan</title><meta charset="utf-8" /><link rel="stylesheet" type="text/css" href="resources/css/reset.css" media="all" /><link rel="stylesheet" type="text/css" href="resources/css/custom-theme/jquery-ui-1.8.14.custom.css" /><link rel="stylesheet" type="text/css" href="resources/css/style.css"  media="all" /><link rel="stylesheet" type="text/css" href="resources/css/style_fixed.css" media="all" /><link rel="stylesheet" type="text/css" href="resources/css/colors/black.css" media="all" /><link rel="stylesheet" type="text/css" href="resources/css/engaging.css" media="all" /><link rel="stylesheet" type="text/css" href="resources/css/print.css" media="print" /><link rel="stylesheet" type="text/css" href="resources/css/theme.blue.css" media="all" /><style type="text/css">body{padding:2em !important;}table{width:100% !important;}th,td{padding:0.5em !important;border:1px solid #ccc !important;text-align:left !important;}th{background-color:#99bfe6 !important;}.hidefromprint{display:none !important;}</style></head><body><h1>Session Planning for ';
				content += <?php echo json_encode( html( $student_names )); ?>;
				content += '</h1><br /><p><strong>Session Time:</strong> <?php echo date( 'd/m/Y', strtotime( $fromdate ) ), ' ', $fromtime, ' &ndash; ',  $totime ; ?></p>';
				content += '<br /><p><strong>Ref:</strong> ' + $("input[name='ref']").val() + '</p>';//ref
				if ($("select[name='curriculum_area_1'] option:selected").text() != "None")
					content += '<br /><p><strong>Curriculum Focus 1:</strong> ' + $("select[name='curriculum_area_1'] option:selected").text() + '</p>';//curric areas1
				if ($("select[name='curriculum_area_2'] option:selected").text() != "None")
					content += '<br /><p><strong>Curriculum Focus 2:</strong> ' + $("select[name='curriculum_area_2'] option:selected").text() + '</p>';//curric areas2
				if ($("select[name='curriculum_area_3'] option:selected").text() != "None")
					content += '<br /><p><strong>Curriculum Focus 3:</strong> ' + $("select[name='curriculum_area_3'] option:selected").text() + '</p>';//curric areas3
				if ($("select[name='learning_focus_id'] option:selected").text() != "None")
					content += '<br /><p><strong>Curriculum Focus 1:</strong> ' + $("select[name='learning_focus_id'] option:selected").text() + '</p>';//learning focus
				if ($("select[name='learning_focus_id_2'] option:selected").text() != "None")
					content += '<br /><p><strong>Curriculum Focus 1:</strong> ' + $("select[name='learning_focus_id_2'] option:selected").text() + '</p>';//learning focus 2
				if ($("select[name='learning_focus_id_3'] option:selected").text() != "None")
					content += '<br /><p><strong>Curriculum Focus 1:</strong> ' + $("select[name='learning_focus_id_3'] option:selected").text() + '</p>';//learning focus 3
				content += '<br /><p><strong>Objectives:</strong> ' + $("#objectives").val() +'</p>';//objectives
				content += '<br /><p><strong>Actions/Methods:</strong> ' + $("#actions_method").val() + '</p>';//actions/methods
				content += '<br /><p><strong>Resources:</strong> ' + $("#resources").val() + '</p>';//resources
				content += '<br /><p><strong>Success Criteria:</strong> ' + $("#success_criteria").val() + '</p>';//success criteria
				content += '<br /><p><strong>Evaluation:</strong> ' + $("#evaluation").val() + '</p>';//evaluation
				content += '<br /><p><strong>Evidence:</strong> ' + $("#evidence").val() + '</p>';//evidence
				content += '</body></html>';
				$( '#pdf-content' ).val( content );
				$( "#pdf_form" ).submit();
			} );
		} );
		//]]>
		</script>
	</body>
</html>
<?php
endif; // modal
require_once( 'includes/disconnect.php' );
