require(["jquery"],
    function($){
        $('.sm_megamenu_title_lv-4').parents('.sm_megamenu_title_lv-2').children('a').addClass('arrow');
        $('.sm_megamenu_title_lv-5').parents('.sm_megamenu_title_lv-4').children('a').addClass('arrow');

        $(function() {
            $('.sm_megamenu_title a').click(function (e) {
                let child = $(this).parent().find('div:first').toggle();
                $(this).toggleClass("arrow arrow-clicked")
                if (child.length > 0) {
                    child.siblings('.sm_megamenu_title').toggle();
                    e.preventDefault();
                }
            });
        });
    })
