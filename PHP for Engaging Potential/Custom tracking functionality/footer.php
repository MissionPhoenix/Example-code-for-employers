		<!-- footer -->
		<div id="footer">
			<p>Copyright &copy; 2012&ndash;<?php echo date( 'Y' ); ?> <a href="//engagingpotential.com/">Engaging Potential</a>. All Rights Reserved.</p>
		</div>
		<!-- end footer -->
<!-- Dan's code -->
<div id="session-tracker" style="display:none;"></div>
<div id="tab-tracker" style="display:none;"></div>
<!-- End of Dan's code ( more further down )-->
<div id="timeout_alert" title="Your session is about to expire" style="display:none;"><p>No activity detected in the last 30 minutes. Please click here to stay logged in.</p></div>
<div id="timed_out" title="Your session has expired" style="display:none;"><p>No further activity has been detected after the session expiry warning, therefore this session has been terminated. Please close this window to be redirected to the login page.</p></div>

<script>
	
//<![CDATA[

$(function()
{
	// Dan's code 
	//When a page is loaded and every 60 seconds therafter, run login-check.php which updates the staff_session_details table.
	$( '#session-tracker' ).load( 'login_check.php' );
	window.setInterval( function() {
        $( '#session-tracker' ).load( 'login_check.php' );
        }, 60000 );

	// When each tab is clicked record this in the session details
	$('.ui-tabs-tab a').on('click', function() {
		document.cookie = "last_tab=" + window.location.pathname + window.location.search + $(this).attr('href');
		$( '#tab-tracker' ).load( 'session_tab_update.php' );
	});
	// End of Dan's code 
	function timeoutAlert()
	{
		$( '#timeout_alert' ).dialog();
	}
	// Set a timer for 10 seconds after which the #timeout_alert div will be display in a dialog box
	var time_out_alert = setTimeout( timeoutAlert, 99999910000 );
	var timed_out = setTimeout( timedOut, 99999920000 );
//	var time_out_alert = setTimeout( timeoutAlert, 60000 );
//	var timed_out = setTimeout( timedOut, 1800000 );

	function timedOut()
	{
		$( '#timed_out' ).dialog({
		});
	}

	$( '#timeout_alert' ).bind( 'dialogclose', function()
	{
		clearTimeout( timed_out );
		setTimeout( timeoutAlert, 99999910000 );
		setTimeout( timedOut, 99999920000 );
//		setTimeout( timeoutAlert, 60000 );
//		setTimeout( timedOut, 1800000 );
		// Make an AJAX request to any old page to refresh the 'last activity' cookie
		$.get( 'index.php' );
	});
	$( '#timed_out' ).bind( 'dialogclose', function()
	{
		location.reload();
	});

});
//]]>
</script>
