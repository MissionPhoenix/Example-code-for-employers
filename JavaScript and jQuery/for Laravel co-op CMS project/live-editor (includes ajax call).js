// JS file which handles the live editing funtionality for the site

/*
* Toggle edit functionality
*/
//
//CKEDITOR.disableAutoInline = "true";
var editToggle = "off";
var editableElements = document.getElementsByClassName('LiveEdit');
var editCount = 0;
var hideMenuButton = document.getElementById("hide-menu");

// Toggle button
document.getElementById("toggle-editor").addEventListener( "click", function() {
    ( editToggle == "off" )? editToggle = "on": editToggle = "off";
    this.innerHTML = "Edit page: " + editToggle;
    toggleEdit( editableElements );
});

// toggle function
function toggleEdit( editableElements ) {
    for( const element of editableElements ) {
        if( editToggle == "on" ) {
            let countName = "editor" + editCount;
            element.setAttribute("name", countName );
            CKEDITOR.inline( countName );
            editCount++;
            element.setAttribute("contenteditable", true);
        } else {
            element.setAttribute("contenteditable", false);
            let new_element = element.cloneNode(true);
            element.parentNode.replaceChild(new_element, element);
        }
    }
}

/*
* Save functionality
*/
var textArray = new Object();
var metaArray = new Object();

document.getElementById("save-editor").addEventListener( "click", function() {
    this.innerHTML = "Saving...";
    for( const element of editableElements ) {
        if( element.id != "meta" ) {
            let routeId = element.getAttribute("data-routeId");
            let textKey = element.getAttribute("data-textKey");
            textArray[textKey] = { "text" : element.innerHTML,
                                    "routeId" : routeId };
        } else {
            let routeId = document.getElementById("meta").getAttribute("data-routeId");
            metaArray["meta_title"] = { "meta_text" : element.children[1].innerHTML,
                                        "routeId" : routeId };
            metaArray["meta_description"] = { "meta_text" : element.children[3].innerHTML,
                                            "routeId" : routeId };
        }

    }
    ajaxSave(textArray, "text");
});

function ajaxSave(dataArray, type) {
    var xhr = new XMLHttpRequest();
    xhr.onload = function() {
            if( this.status == 200) {
                if( type == "text" ) document.getElementById("save-editor").innerHTML = "Save page?";
                $("#saved").modal();
            }
        }
        if( type == "text" ) {
            xhr.open("POST", "/LiveEdit", true);
        }
        if( type == "meta" ) {
            xhr.open("POST", "/LiveEdit/meta", true);
        }
        xhr.setRequestHeader("X-CSRF-TOKEN", document.getElementById("csrf-token").getAttribute("content") );
        xhr.setRequestHeader("Content-Type","application/json");
        if( type == "text" ) {
            xhr.send(JSON.stringify(dataArray));
        }
        if( type == "meta" ) {
            xhr.send(dataArray);
        }
}

var metaForm = document.getElementById("meta-form");
metaForm.addEventListener("submit", function(e) {
    e.preventDefault();
    const metaTitle =  document.getElementById("meta-title").value;
    const metaDescription = document.getElementById("meta-description").innerHTML;
    const routeId = document.getElementById("route-id").value;
    const metaData = JSON.stringify({"meta_title": metaTitle, "meta_description": metaDescription, "routeId": routeId});
    ajaxSave(metaData, "meta");
});
