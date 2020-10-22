/* JS relating to admin-bar slide out */
var adminOut = "No";

$("#admin-tab").click( function (){
  if( adminOut == "No" ) {
    $("#admin-bar-and-tab").parent().animate({ width: ($("body").width() - $("#admin-bar").width()), "margin-left": $("#admin-bar").width()}, 1000, "easeOutBounce");
    $("#admin-bar-and-tab").parent().children().each( function() {
      if( $(this).attr("id") != "admin-bar-and-tab" ) {
          $(this).animate({ width: ($("body").width() - $("#admin-bar").width())}, 1000, "easeOutBounce");
      }
    });
    adminOut = "Yes";
  } else {
    $("#admin-bar-and-tab").parent().animate({ width: "100%", "margin-left": "0px"}, 1000, "easeOutBounce");
    $("#admin-bar-and-tab").parent().children().each( function() {
      if( $(this).attr("id") != "admin-bar-and-tab" ) {
          $(this).css({ width: "100%"});
      }
    });
    adminOut = "No";
    $('#meta').hide();
  }
});
//Hide Tab button
var tabOpen = "Yes";
$('#hide-tab').click( function() {
    if( tabOpen == "Yes" ){
      $('#admin-tab').hide();
      $('#hide-tab').html("Show tab");
      tabOpen = "No";
    } else {
      $('#admin-tab').css( "display", "block" );
      $('#hide-tab').html("Hide tab");
      tabOpen = "Yes";
    }
});

// Functionality for show meta button
$("#show-meta").click( function () {
    $("#meta").modal('toggle');
    ( $('#show-meta').html() == "Show Meta" )? $('#show-meta').html("Hide Meta"): $('#show-meta').html("Show Meta");
});
