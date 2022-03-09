define(['jquery'], function($){
    "use strict";
    return function advancedOptions()
    {
        let cssClasses = document.getElementsByClassName("field configurable required");
        let advancedConfigButton = document.getElementById("advanced-options");
        for (let i = 4; i < cssClasses.length; i++) {
            cssClasses[i].style.display = "none";
        }
        $('#advanced-options').click(function (event) {
            event.preventDefault();
            for (let i = 4; i < cssClasses.length; i++) {
                if (cssClasses[i].style.display === "none") {
                    cssClasses[i].style.display = "block";
                    advancedConfigButton.style.display = "none";
                }
            }
        });
    }
});
