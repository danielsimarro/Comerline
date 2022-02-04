define(['jquery', 'mage/cookies'], function ($) {
    "use strict";
    return function showModal() {
        if ($.mage.cookies.get('llantas_user_car')) {
            $('#compatible-rims').show();
        } else {
            $('#compatible-rims').hide();
        }
    }
},);
