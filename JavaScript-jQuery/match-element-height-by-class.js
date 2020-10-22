// Function to set all elements of className to have the height of the tallest element
function setHeights(className) {
	function getMaxHeight(className) {
		let elements = document.getElementsByClassName(className);
		let maxElementHeight = 0;
		for ( const element of elements ) {
			let elementHeight = element.clientHeight;
			if( elementHeight > maxElementHeight ) maxElementHeight = elementHeight;
		}
		return maxElementHeight;
	}
	let elements = document.getElementsByClassName(className);
	for( const element of elements ) {
    	element.style.height = getMaxHeight(className) + "px";
    }
}
// Call function for required elements
setHeights("uagb-post__excerpt");
setHeights("uagb-post__title");
setHeights("uagb-post-grid-byline");