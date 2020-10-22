// Function to set all elements of className to have the height of the tallest element
function setHeights(className) {
	var elements = document.getElementsByClassName(className);
	function getMaxHeight(className, elements) {
		let maxElementHeight = 0;
		for ( const element of elements ) {
			let elementHeight = element.clientHeight;
			if( elementHeight > maxElementHeight ) maxElementHeight = elementHeight;
		}
		return maxElementHeight;
	}
	let maxHeight = getMaxHeight(className, elements);
	for( const element of elements ) {
    	element.style.height = maxHeight + "px";
    }
}
// Call function for required elements
setHeights("uagb-post__excerpt");
setHeights("uagb-post__title");
setHeights("uagb-post-grid-byline");