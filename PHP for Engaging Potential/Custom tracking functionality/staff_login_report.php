<?php
/**
 * Display table of staff login history for the last month. Sortable by staff member.
 *
 * Portfolio notes: All the code here is mine except the Export to excel/pdf code which is copied.
 *
 * @author     Pete Donnell <pete@kitson-consulting.co.uk>
 * @author     Dan Watkins <dan@kitson-consulting.co.uk>
 * @copyright  2020 Kitson Consulting Limited
 * @date       12/10/2020
 * @licence    All rights reserved
 * @package    engagingpotential
 * @see        https://engagingpotential.com/office/
 */

require_once( 'includes/connect.php' );
require_once( 'includes/functions.php' );
require_once( 'includes/admin.php' );

if( !isSeniorManager() )
{
	header( "Location: error.php?error=You don't have permission to access this section." );
	die();
}

$query = "SELECT 	`session_id`,					
					`session_start`,
					`last_poll`,
					`displayname`
					 FROM `staff_sessions`
					 JOIN `staff` ON `staff`.`id` = `staff_sessions`.`staff_id`
					 WHERE `session_start` >= DATE_SUB( NOW(), INTERVAL 1 MONTH )";
if( isset( $_GET['staff_id'] ) and count( $_GET['staff_id'] ) )
{
	$query .= "AND `staff_sessions`.`staff_id` IN ( ";
	for( $i = 0; $i < count( $_GET['staff_id'] ); ++$i )
	{
		if( $i ) $query .= ', ';
		$query .= (int) $_GET['staff_id'][$i];
	}
	$query .= " )";
}

$query .= " ORDER BY `session_start` DESC, `last_poll` DESC, `displayname` ASC";

$statement = $db->prepare( $query );
$statement->execute();

$logins = '';
date_default_timezone_set("Europe/London");
foreach( $statement->fetchAll( PDO::FETCH_ASSOC ) as $row )
{
	$logins .= "<tr>";
	$logins .= '<td>' . html( $row['displayname'] ) . '</td>';
	$logins .= '<td>' . date( "m jS h:i A", strtotime( $row['session_start'] ) ) . '</td>';
	if( ( strtotime( $row['last_poll'] ) + 60 ) >= time() ) 
	{
		$logins .= '<td>Staff is currently logged in</td>';
	}
	else $logins .= '<td>' . date( "m jS h:i A", strtotime( $row['last_poll'] ) ) . '</td>';
	$logins .= '<td>' . gmdate( "H:i", ( strtotime( $row['last_poll'] ) - strtotime( $row['session_start'] ) ) ) . '</td>';
	$logins .= '<td><a href="staff_session_details.php?session_id=' . $row['session_id'] . '" style="float:right;">View Session Details</a></td>';
	$logins .= '</tr>' . PHP_EOL;
}

//Save appended url as session varible. This variable will be used to redirect users back to their login search in staff_concern_edit.php
$_SESSION['login_search_url'] = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
if( isset( $_GET['staff_id'] ) )
{
	if (mb_strpos($_SESSION['login_search_url'], "?") === false) $_SESSION['login_search_url'] .= "?";
	else $_SESSION['login_search_url'] .= "&";
	$_SESSION['login_search_url'] .= 'staff_id[]=' . $_GET['staff_id'][0];
}
?>

<!doctype html>
<html>
<?php
require_once( "includes/head.php" );
?>
	<body>
<?php
require_once( 'includes/header.php' );
?>
		<!-- content -->
		<div id="content">
<?php
require_once( 'includes/menu_left_contact.php' );
?>
			<div id="right">
				<div class="box">
					<div class="title">
						<h5>Staff Login Report</h5>
					</div>
					<div class="table">
						<form style='display: inline-block;' class="hidefromprint" method='post' action="excel.php?report=table">
							<input type='hidden' name='table' id='table-content' />
							<input type="hidden" name="filename" value="Staff Login Report <?php echo gmdate( 'd-m-Y' ); ?>" />
							<input type="hidden" name="last-column" value="F" />
							<a href='#' id='excel-export' title='Export to Excel'>
								<img style='vertical-align: middle;' alt="Excel icon" src="resources/images/icons/excel.png" />
								Export to Excel |
							</a>
						</form>
						<form style='display: inline-block;' class="hidefromprint" method='post' action="pdf.php">
							<input type='hidden' name='html' id='pdf-content' />
							<input type='hidden' name='layout' value='landscape' />
							<input type="hidden" name="filename" value="Staff Login Report <?php echo gmdate( 'd-m-Y' ); ?>" />
							<a href='#' id='pdf-export' title='Export to PDF (warning: this may take a long time)'>
								<img style='vertical-align: middle;' alt="PDF icon" src="resources/images/icons/pdf.png" />
								Export to PDF |
							</a>
						</form>
						<p style='display: inline-block;' class="hidefromprint">
							<a href="#" id="window-print">Print</a>
						</p>

						<form class="hidefromprint" method='get' action="staff_login_report.php">
							<div id="staff_name_label_logins">
								<label>Staff Name</label>
							</div>
							<div>
								<select id="staff_logins_mulitple_select" multiple name="staff_id[]">
									<?php
										foreach( $db->query( "SELECT * FROM `staff` WHERE `finishdate` = '0000-00-00' OR `finishdate` > NOW() ORDER BY `displayname`") as $staff )
										{
											echo "<option value='" . $staff['id'] . "'";
											if( isset( $_GET['staff_id'] ) and is_array( $_GET['staff_id'] ) )
											{
												foreach( $_GET['staff_id'] as $id )
												{
													if( $id == $staff['id'] ) echo ' selected="selected"';
												}
											}
											echo "> " . html( $staff['displayname'] ) . "</option>";
										}
									?>
								</select> 							
							<input id="staff_logins_submit" type="submit" value="Search" />
							<p>Use the mouse wheel or cursor keys to move through the list. Hold down &lt;Ctrl&gt; while clicking to select or deselect multiple options.</p>
							</div>
						</form>

						<table id='products'>
							<thead>
								<tr>
									<th>Staff</th>
									<th>Session Start</th>
									<th>Session End</th>
									<th>Session Length (Hours:Minutes)</th>
									<th></th>
								</tr>
							</thead>
							<tbody>
<?php
echo $logins;
?>
							</tbody>
							<tfoot>
								<tr>
									<th>Staff</th>
									<th>Session Start</th>
									<th>Session End</th>
									<th>Session Length (Hours:Minutes)</th>
									<th></th>
								</tr>
							</tfoot>
						</table>
					</div><!-- table -->
				</div><!-- box -->
			</div><!-- right -->
		</div><!-- content -->
		<?php require_once("includes/footer.php"); ?>
		<script type='text/javascript'>
		//<![CDATA[
		$( function()
		{
			$( 'table' ).tablesorter( { theme: 'blue', widgets: ['saveSort', 'zebra'] } );
			$( '#excel-export' ).click( function( e ) {
				e.preventDefault();
				$( '#table-content' ).val( '<!doctype html><html><head><title>Staff Login Report</title><meta charset="utf-8" /><style type="text/css">th { font-weight: bolder; }</style></head><body><table>' + $( '#products' ).html() + '</table></body></html>' );
				$( this ).parent().submit();
			} );
			$( '#pdf-export' ).click( function( e ) {
				e.preventDefault();
				$( '#pdf-content' ).val( '<!doctype html><html><head><title>Staff Login Report</title><meta charset="utf-8" /><link rel="stylesheet" type="text/css" href="resources/css/reset.css" media="all" /><link rel="stylesheet" type="text/css" href="resources/css/custom-theme/jquery-ui-1.8.14.custom.css" /><link rel="stylesheet" type="text/css" href="resources/css/style.css"  media="all" /><link rel="stylesheet" type="text/css" href="resources/css/style_fixed.css" media="all" /><link rel="stylesheet" type="text/css" href="resources/css/colors/black.css" media="all" /><link rel="stylesheet" type="text/css" href="resources/css/engaging.css" media="all" /><link rel="stylesheet" type="text/css" href="resources/css/print.css" media="print" /><link rel="stylesheet" type="text/css" href="resources/css/theme.blue.css" media="all" /><style type="text/css">body{padding:2em !important;}table{width:100% !important;}th,td{padding:0.5em !important;border:1px solid #ccc !important;text-align:left !important;}th{background-color:#99bfe6 !important;}.hidefromprint{display:none !important;}</style></head><body><h1>Staff Login Report</h1><br /><table>' + $( '#products' ).html() + '</table></body></html>' );
				$( this ).parent().submit();
			} );
			$( '#undefined-button' ).remove();
			$( '.date' ).datepicker( { dateFormat: 'yy-mm-dd' } );
		} );
		//]]>
		</script>
	</body>
</html>
<?php require_once( "includes/disconnect.php" );