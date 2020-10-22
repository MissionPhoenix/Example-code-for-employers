<?php
/**
 * View the details for a specific session.
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

if( isset( $_GET['session_id'] ) and $_GET['session_id'] )
{
	$session_id = $_GET['session_id'];
	$query = 	"SELECT `displayname`,
						`page_visited`,
						`date_time_loaded`,
						`session_start`,
						`last_poll`
				FROM `staff_sessions`
				JOIN `staff_session_details` ON `staff_sessions`.`session_id` = `staff_session_details`.`session_id`
				JOIN `staff` ON `staff_sessions`.`staff_id` = `staff`.`id`
				WHERE `staff_sessions`.`session_id` = :session_id
				AND `staff_session_details`.`page_visited` NOT LIKE '%ajax%'
				AND `staff_session_details`.`page_visited` NOT LIKE '%save%'
				AND `staff_session_details`.`page_visited` NOT LIKE '%handler%'
				ORDER BY `date_time_loaded` DESC";

	$statement = $db->prepare( $query );
	$statement->bindValue( ':session_id', $session_id, PDO::PARAM_INT );
	$statement->execute();
	$row = $statement->fetch( PDO::FETCH_ASSOC );
	$displayname = $row['displayname'];
	$session_start = $row['session_start'];
	$session_end = $row['last_poll'];
	date_default_timezone_set("Europe/London");
	$loads = '';
	foreach( $statement->fetchAll( PDO::FETCH_ASSOC ) as $row )
	{
		$loads .= '<tr>';
		$loads .= '<td><a href="https://' . $_SERVER['HTTP_HOST'] . '/office/' . str_replace( '&modal=true', '', $row['page_visited'] ). '">' . $row['page_visited'] . '</td>';
		$loads .= '<td>' . date( "m jS h:i A", strtotime( $row['date_time_loaded'] ) ) . '</td>';
		$loads .= '</tr>';
	}

}
else echo "You must first select a session from staff_login_report.php in order to view the session details.";
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
						<h5>Session details</h5>
					</div>
					<div class="table">
						<form style='display: inline-block;' class="hidefromprint" method='post' action="excel.php?report=table">
							<input type='hidden' name='table' id='table-content' />
							<input type="hidden" name="filename" value="Staff Login Details >" />
							<input type="hidden" name="last-column" value="F" />
							<a href='#' id='excel-export' title='Export to Excel'>
								<img style='vertical-align: middle;' alt="Excel icon" src="resources/images/icons/excel.png" />
								Export to Excel |
							</a>
						</form>
						<form style='display: inline-block;' class="hidefromprint" method='post' action="pdf.php">
							<input type='hidden' name='html' id='pdf-content' />
							<input type='hidden' name='layout' value='landscape' />
							<input type="hidden" name="filename" value="Staff Login Details" />
							<a href='#' id='pdf-export' title='Export to PDF (warning: this may take a long time)'>
								<img style='vertical-align: middle;' alt="PDF icon" src="resources/images/icons/pdf.png" />
								Export to PDF |
							</a>
						</form>
						<p style='display: inline-block;' class="hidefromprint">
							<a href="#" id="window-print">Print</a>
						</p>
						<table id='products'> 
							<thead>
								<h4>Session details for <?php echo $displayname . ' Session started ' . date( "m jS h:i A", strtotime( $session_start ) );
						if( ( strtotime( $row['last_poll'] ) + 60 ) >= time() )
						{
							echo '. This session is still active';
						}  
						else echo ' to ' . date( "m jS h:i A", strtotime( $session_end ) ) ?>.</h4>
						<h6><a href="<?php echo $_SESSION['login_search_url'] ?>">Click here to return to your filtered login report</a></h6>
								<tr>
									<th>Page visited</th>
									<th>When</th>
								</tr>
							</thead>
							<tbody>
<?php
echo $loads;
?>
							</tbody>
							<tfoot>
								<tr>
									<th>Page visited</th>
									<th>When</th>
								</tr>
							</tfoot>
						</table>
					</div>
				</div>
			</div>
		</div><!-- content -->
		<?php require_once("includes/footer.php"); ?>
		<script type='text/javascript'>
		//<![CDATA[
		$( function()
		{
			$( 'table' ).tablesorter( { theme: 'blue', widgets: ['saveSort', 'zebra'] } );
			$( '#excel-export' ).click( function( e ) {
				e.preventDefault();
				$( '#table-content' ).val( '<!doctype html><html><head><title>Staff Login Details</title><meta charset="utf-8" /><style type="text/css">th { font-weight: bolder; }</style></head><body><table>' + $( '#products' ).html() + '</table></body></html>' );
				$( this ).parent().submit();
			} );
			$( '#pdf-export' ).click( function( e ) {
				e.preventDefault();
				$( '#pdf-content' ).val( '<!doctype html><html><head><title>Staff Login Details</title><meta charset="utf-8" /><link rel="stylesheet" type="text/css" href="resources/css/reset.css" media="all" /><link rel="stylesheet" type="text/css" href="resources/css/custom-theme/jquery-ui-1.8.14.custom.css" /><link rel="stylesheet" type="text/css" href="resources/css/style.css"  media="all" /><link rel="stylesheet" type="text/css" href="resources/css/style_fixed.css" media="all" /><link rel="stylesheet" type="text/css" href="resources/css/colors/black.css" media="all" /><link rel="stylesheet" type="text/css" href="resources/css/engaging.css" media="all" /><link rel="stylesheet" type="text/css" href="resources/css/print.css" media="print" /><link rel="stylesheet" type="text/css" href="resources/css/theme.blue.css" media="all" /><style type="text/css">body{padding:2em !important;}table{width:100% !important;}th,td{padding:0.5em !important;border:1px solid #ccc !important;text-align:left !important;}th{background-color:#99bfe6 !important;}.hidefromprint{display:none !important;}</style></head><body><h1>Staff Login Details</h1><br /><table>' + $( '#products' ).html() + '</table></body></html>' );
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
