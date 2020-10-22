//JavaSript for SeedOfPeace.org

//The following code will filter the event list for online, in-person or all
// php and html for this can be found in page-events.php AccessPress Pro Child theme
var filterChoice = "All";
var allEvents = document.getElementsByClassName("events");
var filterButtons = document.getElementsByClassName("filter-button");
//Whenever a filter button is clicked change the filterChoice and filterEvents
for (const filterButton of filterButtons) {
	filterButton.addEventListener( "click", function() {
		filterChoice = this.innerHTML;
		filterEvents(filterChoice, allEvents);
		document.querySelector(".entry-title").innerHTML = this.innerHTML + " Events"
	});
}

//Filters the events on whether the title contains the word online or not.
function filterEvents(filterChoice, allEvents) {
	if( filterChoice == "Online") {
		for( const event of allEvents ) {
			let title = event.children[1].children[0].innerHTML;
			if ( title.indexOf("Online") > -1 || title.indexOf("online") > -1 ) { 
				event.style.display = "block"; 
			} else { 
				event.style.display = "none"; 
			}			
		}
	}
	if( filterChoice == "In Person") {
		for( const event of allEvents ) {
			let title = event.children[1].children[0].innerHTML;
			if ( title.indexOf("Online") < 0 && title.indexOf("online") < 0 ) { 
				event.style.display = "block"; 
			} else { 
				event.style.display = "none"; 
			}			
		}
	}
	if( filterChoice == "All") {
		for( const event of allEvents ) {
			event.style.display = "block"; 
		}
	}
}

// Code for the booking form loading "alert". 
// It's not actually an alert is just displays a fixed postition text widget whenever a booking button is clicked.
// There is no need to hide it again as the "alert" is cleared when the booking form loads
var bookingButtons = document.getElementsByClassName("booking-button");
addToChildren("booking-button", bookingButtons);
function addToChildren(bookingButtonClass, parent) {
	var bookingAlert = document.getElementById("text-401584140");
	for( const button of parent ) {
		if( button.tagName == "A") {
			button.addEventListener("click", function() {
				bookingAlert.style.display = "block";
			});
		} else if( button.children.length > 0 ) {
			let childButtons = button.getElementsByClassName(bookingButtonClass);
			addToChildren(bookingButtonClass, childButtons);
			for( const child of button.getElementsByTagName("a") ) {
					child.addEventListener("click", function() {
						bookingAlert.style.display = "block";
					});
			}
		}
	}
}

