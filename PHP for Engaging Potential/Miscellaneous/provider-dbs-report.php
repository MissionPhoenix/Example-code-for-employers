<?php
/**
 * Engaging Potential provider DBS report
 *
 *NOTE dbs_valid_to should be called dbs_valid_from, public_insurance_valid_to should be called public_insurance_valid_from, business *insurance_valid_to should be called business_insurance_valid_from and SLA_valid_to should be called SLA_valid_from.
 *
 * Portfolio notes: Duplicated dbs-report for staff and edited for service providers. Added fields for public insurance, business insurance and sla. 
 * The above mistake was made when naming the database columns, when service providers were first added to the system.
 *
 * @author Pete Donnell <pete@kitson-consulting.co.uk>
 * @author Mark Donnell <mark@kitson-consulting.co.uk>
 * @author Dan Watkins <dan@kitson-consulting.co.uk>
 * @copyright 2016-2019 Kitson Consulting Limited
 * @date       13/05/2019
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
//die( 'This page is not yet complete. Please check back later.');


// Load DBS, Insurance and SLA records
$query = "SELECT * FROM `providers` WHERE ( `finishdate` > NOW() OR `finishdate` = '0000-00-00 00:00:00' ) ORDER BY `contactname` ASC";
$statement = $db->prepare( $query );
$statement->execute();
$dbs = '';
//the DBS is out of date, 3 years from the clearance date. Insurance and SLA is out of date after 1 year.
foreach( $statement->fetchAll( PDO::FETCH_ASSOC ) as $row )
{
	if ( (strtotime('now') > strtotime($row['dbs_valid_to'] . '+ 3 years') ) or (strtotime('now') > strtotime($row['business_insurance_valid_to'] . '+ 1 years') ) or (strtotime('now') > strtotime($row['public_liability_valid_to'] . '+ 1 years') ) or (strtotime('now') > strtotime($row['SLA_valid_to'] . '+ 1 years') ) ) $colour =  COLOUR_RED;
	elseif ( (strtotime('+ 2 months') > strtotime($row['dbs_valid_to'] . ' + 3 years') or (strtotime('+ 2 months') > strtotime($row['business_insurance_valid_to'] . '+ 1 years') ) or (strtotime('+ 2 months') > strtotime($row['public_liability_valid_to'] . '+ 1 years') ) or (strtotime('+ 2 months') > strtotime($row['SLA_valid_to'] . '+ 1 years') ) ) ) $colour = COLOUR_AMBER;
	else $colour = COLOUR_GREEN;
	$dbs .= "<tr style='color:$colour' id='" . $row['id'] . "'>";
	$dbs .= '<td>' . html( $row['contactname'] ) . '</td>';
	if( $row['dbs_valid_to'] == '0000-00-00 00:00:00' or $row['dbs_valid_to'] == NULL )
	{
		$dbs .= '<td>Not completed</td>';
		$dbs .= '<td>N/A</td>';
	}
	else {
		$dbs .= '<td>' . html( date('d/m/Y', strtotime($row['dbs_valid_to']) ) ) . '</td>';
		$dbs .= '<td>' . date('d/m/Y', strtotime($row['dbs_valid_to'] . ' + 3 years' ) ) . '</td>';
	}

		if( $row['business_insurance_valid_to'] == '0000-00-00' or $row['business_insurance_valid_to'] == NULL )
	{
		$dbs .= '<td>Not completed</td>';
		$dbs .= '<td>N/A</td>';
	}
	else {
		$dbs .= '<td>' . html( date('d/m/Y', strtotime($row['business_insurance_valid_to']) ) ) . '</td>';
		$dbs .= '<td>' . date('d/m/Y', strtotime($row['business_insurance_valid_to'] . ' + 1 years' ) ) . '</td>';
	}

		if( $row['public_liability_valid_to'] == '0000-00-00' or $row['public_liability_valid_to'] == NULL )
	{
		$dbs .= '<td>Not completed</td>';
		$dbs .= '<td>N/A</td>';
	}
	else {
		$dbs .= '<td>' . html( date('d/m/Y', strtotime($row['public_liability_valid_to']) ) ) . '</td>';
		$dbs .= '<td>' . date('d/m/Y', strtotime($row['public_liability_valid_to'] . ' + 1 years' ) ) . '</td>';
	}

		if( $row['sla_valid_to'] == '0000-00-00' or $row['sla_valid_to'] == NULL )
	{
		$dbs .= '<td>Not completed</td>';
		$dbs .= '<td>N/A</td>';
	}
	else {
		$dbs .= '<td>' . html( date('d/m/Y', strtotime($row['sla_valid_to']) ) ) . '</td>';
		$dbs .= '<td>' . date('d/m/Y', strtotime($row['sla_valid_to'] . ' + 1 years' ) ) . '</td>';
	}
	$dbs .= '<td><a href="contacts_edit.php?id='.$row['id'].'&amp;type=providers&amp;tabindex=3">Click here to view</a></td>';

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
						<h5>Provider HR report</h5>
					</div>
					<div class="table">
						<form style='display: inline-block;' class="hidefromprint" method='post' action="excel.php?report=table">
							<input type='hidden' name='table' id='table-content' />
							<input type="hidden" name="filename" value="Provider DBS Report <?php echo date( 'd-m-Y' ); ?>" />
							<input type="hidden" name="last-column" value="F" />
							<a href='#' id='excel-export' title='Export to Excel'>
								<img style='vertical-align: middle;' alt="Excel icon" src="resources/images/icons/excel.png" />
								Export to Excel |
							</a>
						</form>
						<form style='display: inline-block;' class="hidefromprint" method='post' action="pdf.php">
							<input type='hidden' name='html' id='pdf-content' />
							<input type='hidden' name='layout' value='landscape' />
							<input type="hidden" name="filename" value="Provider DBS Report <?php echo date( 'd-m-Y' ); ?>" />
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
									<th>Provider</th>
									<th>DBS Clearance Date</th>
									<th>DBS Expiry Date</th>
									<th>Business Insurance<br />Clearance Date</th>
									<th>Business Insurance<br />Expiry Date</th>
									<th>Public Liability<br />Clearance Date</th>
									<th>Public Liability<br />Expiry Date</th>
									<th>SLA Clearance Date</th>
									<th>SLA Expiry Date</th>
									<th>View Provider</th>
								</tr>
							</thead>
							<tbody>
<?php
echo $dbs;
?>
							</tbody>
							<tfoot>
								<tr>
									<th>Provider</th>
									<th>DBS Clearance Date</th>
									<th>DBS Expiry Date</th>
									<th>Business Insurance<br />Clearance Date</th>
									<th>Business Insurance<br />Expiry Date</th>
									<th>Public Liability<br />Clearance Date</th>
									<th>Public Liability<br />Expiry Date</th>
									<th>SLA Clearance Date</th>
									<th>SLA Expiry Date</th>
									<th>View Provider</th>
								</tr>
							</tfoot>
						</table>
					</div><!-- table -->
				</div><!-- box -->
			</div><!-- right -->
		</div><!-- content -->
		<script type='text/javascript'>
			//<![CDATA[
			$( function()
			{
				$( 'table' ).tablesorter( { theme: 'blue', widgets: ['saveSort', 'zebra'] } );
				$( '#excel-export' ).click( function( e ) {
					e.preventDefault();
					$( '#table-content' ).val( '<!doctype html><html><head><title>Provider DBS Report</title><meta charset="utf-8" /><style type="text/css">th { font-weight: bolder; }</style></head><body><table>' + $( '#products' ).html() + '</table></body></html>' );
					$( this ).parent().submit();
				} );
				$( '#pdf-export' ).click( function( e ) {
					e.preventDefault();
					$( '#pdf-content' ).val( '<!doctype html><html><head><title>Provider DBS Report</title><meta charset="utf-8" /><link rel="stylesheet" type="text/css" href="resources/css/reset.css" media="all" /><link rel="stylesheet" type="text/css" href="resources/css/custom-theme/jquery-ui-1.8.14.custom.css" /><link rel="stylesheet" type="text/css" href="resources/css/style.css"  media="all" /><link rel="stylesheet" type="text/css" href="resources/css/style_fixed.css" media="all" /><link rel="stylesheet" type="text/css" href="resources/css/colors/black.css" media="all" /><link rel="stylesheet" type="text/css" href="resources/css/engaging.css" media="all" /><link rel="stylesheet" type="text/css" href="resources/css/print.css" media="print" /><link rel="stylesheet" type="text/css" href="resources/css/theme.blue.css" media="all" /><style type="text/css">body{padding:2em !important;}table{width:100% !important;}th,td{padding:0.5em !important;border:1px solid #ccc !important;text-align:left !important;}th{background-color:#99bfe6 !important;}.hidefromprint{display:none !important;}</style></head><body><h1>Provider DBS Report</h1><br /><table>' + $( '#products' ).html() + '</table></body></html>' );
					$( this ).parent().submit();
				} );
			} );
			//]]>
		</script>
	</body>
</html>
