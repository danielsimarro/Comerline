require(["jquery"],
    function($){
        let breadcrumbs = $('.breadcrumbs').children().find('li');
        breadcrumbs.each(function (i) {
            if (($(this).text().includes('Llantas')) || ($(this).text().includes('Neumáticos'))) {
                $('#qty').val('4');
            }
        });
    })
