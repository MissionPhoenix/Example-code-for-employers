<?php
/**
 * Vehicle related documents expiry report
 *
 * Portfolio notes: converted from a smaller file. Most of the PHP and MySQL is mine. Created new columns in the database for the added 
 * funtionality. I did not write the jQuery or the html section on this one.
 *
 * @author     Mark Donnell <mark@kitson-consulting.co.uk>
 * @author     Pete Donnell <pete@kitson-consulting.co.uk>
 * @author     Daniel Watkins <dan@kitson-consulting.co.uk>
 * @copyright  2018-2020 Kitson Consulting Limited
 * @date       06/07/2020
 * @licence    All rights reserved
 * @package    engagingpotential
 * @see        https://engagingpotential.com/office/
 */

require_once( 'includes/connect.php' );
require_once( 'includes/functions.php' );
require_once( 'includes/admin.php' );

if( !isAdmin() )
{
	header( "Location: error.php?error=You don't have permission to access this section." );
	die();
}

// Vehicle expiry document records
$query = "SELECT * FROM `staff` WHERE `finishdate` = '0000-00-00 00:00:00' OR `finishdate` > NOW() ORDER BY `lastname` ASC";
$statement = $db->prepare( $query );
$statement->execute();

$vehicle_expiry = '';

foreach( $statement->fetchAll( PDO::FETCH_ASSOC ) as $row )
{
	if( strtotime( 'now' ) > strtotime( $row['car_insurance_end'] ) ) $colour = COLOUR_RED;
	elseif( strtotime( '+ 1 month' ) > strtotime( $row['car_insurance_end'] ) ) $colour = COLOUR_AMBER;
	else $colour = COLOUR_GREEN;
	$vehicle_expiry .= "<tr id='" . $row['id'] . "'>";
	$vehicle_expiry.= '<td>' . html( $row['displayname'] ) . '</td>';
	if( $row['car_insurance_end'] == '0000-00-00' or $row['car_insurance_end'] == NULL )
	{
		$vehicle_expiry .= "<td style='color:$colour'>Not Set</td>";
	}
	else 
	{
		$vehicle_expiry .= "<td style='color:$colour'>" . html( date('d-m-Y', strtotime($row['car_insurance_end']) ) ) . "</td>";
	}

	if( strtotime( 'now' ) > strtotime( $row['mot_expiry'] ) ) $colour = COLOUR_RED;
	elseif( strtotime( '+ 1 month' ) > strtotime( $row['mot_expiry'] ) ) $colour = COLOUR_AMBER;
	else $colour = COLOUR_GREEN;
	if( $row['mot_expiry'] == '0000-00-00' or $row['mot_expiry'] == NULL )
	{
		$vehicle_expiry .= "<td style='color:$colour'>Not Set</td>";
	}
	else 
	{
		$vehicle_expiry .= "<td style='color:$colour'>" . html( date('d-m-Y', strtotime($row['mot_expiry']) ) ) . "</td>";
	}

	if( strtotime( 'now' ) > strtotime( $row['road_tax_expiry'] ) ) $colour = COLOUR_RED;
	elseif( strtotime( '+ 1 month' ) > strtotime( $row['road_tax_expiry'] ) ) $colour = COLOUR_AMBER;
	else $colour = COLOUR_GREEN;
	if( $row['road_tax_expiry'] == '0000-00-00' or $row['road_tax_expiry'] == NULL )
	{
		$vehicle_expiry .= "<td style='color:$colour'>Not Set</td>";
	}
	else 
	{
		$vehicle_expiry .= "<td style='color:$colour'>" . html( date('d-m-Y', strtotime($row['road_tax_expiry']) ) ) . "</td>";
	}

	$licensecheckeddate = $row['driving_license_checked'];
	if( $licensecheckeddate == '0000-00-00' or $licensecheckeddate == NULL )
	{
		$colour = COLOUR_RED;
	}
	else
	{
		$licensecheckdeadline = date( 'Y-m-d', strtotime("+6 months", strtotime( $licensecheckeddate ) ) );
		if( strtotime( 'now' ) > strtotime( $licensecheckdeadline ) ) $colour = COLOUR_RED;
		elseif( strtotime( '+ 1 month' ) > strtotime( $licensecheckdeadline ) ) $colour = COLOUR_AMBER;
		else $colour = COLOUR_GREEN;
	}

	if( $licensecheckeddate == '0000-00-00' or $licensecheckeddate == NULL )
	{
		$vehicle_expiry .= "<td style='color:$colour'>Not Set</td>";
	}
	else 
	{
		$vehicle_expiry .= "<td style='color:$colour'>" . html( $licensecheckdeadline ) . "</td>";
	}

	if( isSeniorManager() )
	{
		$vehicle_expiry .= '<td><a href="staff_edit.php?id=' . $row['id'] . '&amp;tabindex=3">Click here to view</a></td>';
	}


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
						<h5>Vehicle Documents Report</h5>
					</div>
					<div class="table">
						<form style='display: inline-block;' class="hidefromprint" method='post' action="excel.php?report=table">
							<input type='hidden' name='table' id='table-content' />
							<input type="hidden" name="filename" value="Vehicle Documents Report <?php echo date( 'd-m-Y' ); ?>" />
							<input type="hidden" name="last-column" value="F" />
							<a href='#' id='excel-export' title='Export to Excel'>
								<img style='vertical-align: middle;' alt="Excel icon" src="resources/images/icons/excel.png" />
								Export to Excel |
							</a>
						</form>
						<form style='display: inline-block;' class="hidefromprint" method='post' action="pdf.php">
							<input type='hidden' name='html' id='pdf-content' />
							<input type='hidden' name='layout' value='landscape' />
							<input type="hidden" name="filename" value="Vehicle Documents Report <?php echo date( 'd-m-Y' ); ?>" />
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
								<tr>
									<th>Staff</th>
									<th>Vehicle Insurance Expires</th>
									<th>MOT Expires</th>
									<th>Road Tax Expires</th>
									<th>Driving License Check Expires</th>
<?php if( isSeniorManager() ): ?>
									<th>View Staff</th>
<?php endif; ?>
								</tr>
							</thead>
							<tbody>
<?php
echo $vehicle_expiry;
?>
							</tbody>
							<tfoot>
								<tr>
									<th>Staff</th>
									<th>Vehicle Insurance Expiry Date</th>
									<th>MOT Expires</th>
									<th>Road Tax Expires</th>
									<th>Driving License Check Expires</th>
<?php if( isSeniorManager() ): ?>
									<th>View Staff</th>
<?php endif; ?>
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
					$( '#table-content' ).val( '<!doctype html><html><head><title>Vehicle Documents Report</title><meta charset="utf-8" /><style type="text/css">th { font-weight: bolder; }</style></head><body><table>' + $( '#products' ).html() + '</table></body></html>' );
					$( this ).parent().submit();
				} );
				$( '#pdf-export' ).click( function( e ) {
					e.preventDefault();
					$( '#pdf-content' ).val( '<!doctype html><html><head><title>Vehicle Documents Report</title><meta charset="utf-8" /><link rel="stylesheet" type="text/css" href="resources/css/reset.css" media="all" /><link rel="stylesheet" type="text/css" href="resources/css/custom-theme/jquery-ui-1.8.14.custom.css" /><link rel="stylesheet" type="text/css" href="resources/css/style.css"  media="all" /><link rel="stylesheet" type="text/css" href="resources/css/style_fixed.css" media="all" /><link rel="stylesheet" type="text/css" href="resources/css/colors/black.css" media="all" /><link rel="stylesheet" type="text/css" href="resources/css/engaging.css" media="all" /><link rel="stylesheet" type="text/css" href="resources/css/print.css" media="print" /><link rel="stylesheet" type="text/css" href="resources/css/theme.blue.css" media="all" /><style type="text/css">body{padding:2em !important;}table{width:100% !important;}th,td{padding:0.5em !important;border:1px solid #ccc !important;text-align:left !important;}th{background-color:#99bfe6 !important;}.hidefromprint{display:none !important;}</style></head><body><h1>Vehicle Documents Report</h1><br /><table>' + $( '#products' ).html() + '</table></body></html>' );
					$( this ).parent().submit();
				} );
			} );
			//]]>
		</script>
	</body>
</html>
