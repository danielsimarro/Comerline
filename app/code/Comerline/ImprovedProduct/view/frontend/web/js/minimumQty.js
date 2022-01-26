require(["jquery"],
    function($){
        let breadcrumbs = $('.breadcrumbs').children().find('li');
        breadcrumbs.each(function (i) {
            if ($(this).text().includes('Llantas')) {
                $('#qty').val('4');
            }
        });
    })
