require(["jquery"],
    function ($) {
        $(window).on('load', function () {
            let categories = $('#categories').data('categories');
            if (categories && (categories.indexOf("Llantas") >= 0 || categories.indexOf("Neumáticos") >= 0)) {
                $('#qty').val('4');
            }
        });
    })
