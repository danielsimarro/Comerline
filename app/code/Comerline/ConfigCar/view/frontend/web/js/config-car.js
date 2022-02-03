define(['jquery', 'Magento_Ui/js/modal/modal', 'domReady', 'mage/cookies'], function ($, modal) {
    "use strict";
    return function (config) {
        $(document).ready(function () {
            $('#configcar-modal-button').html($.cookie('llantas_user_text')); // Replace button text on page load for the one we have in the cookie
            if ($.cookie('llantas_user_car')) {
                changeModal($.cookie('llantas_user_car'), config.categoriesAttribute, '#compatible-options', null, '#car-name');
            }
        });
        let options = {
            type: 'popup', responsive: true, innerScroll: true, title: 'Configurador de coche', buttons: [{
                text: $.mage.__('Select'), class: 'accept', click: function () {
                    let diameter = $("th[attribute-text = 'diameter']").attr('attribute-id');
                    let width = $("th[attribute-text = 'width']").attr('attribute-id');
                    let offset = $("th[attribute-text = 'offset']").attr('attribute-id');
                    let hub = $("th[attribute-text = 'hub']").attr('attribute-id');

                    $.cookie('llantas_user_car', ($('#llantas_ano option:selected').val()));
                    $.cookie('llantas_user_text', ($('#llantas_marca option:selected').text() + ' ' + $('#llantas_modelo option:selected').text() + ' ' + $('#llantas_ano option:selected').text())); // We get car model in a cookie
                    $.cookie('llantas_user_car_diameter', diameter); // We get all the attribute IDs on cookies
                    $.cookie('llantas_user_car_width', width);
                    $.cookie('llantas_user_car_offset', offset);
                    $.cookie('llantas_user_car_hub', hub);
                    $('#configcar-modal-button').html($.cookie('llantas_user_text')); // We set the button text with the car model stored in the cookie
                    $('#feedback').append('Vehículo seleccionado correctamente'); // We add a message when the change is done
                    location.reload();
                }
            }, {
                text: $.mage.__('Delete'), class: 'delete', click: function () {
                    $.cookie('llantas_user_car', null); // We delete the cookies
                    $.cookie('llantas_user_car_diameter', null);
                    $.cookie('llantas_user_car_width', null);
                    $.cookie('llantas_user_car_offset', null);
                    $.cookie('llantas_user_car_hub', null);
                    $.cookie('llantas_user_text', 'Configurador de coche');
                    $('#configcar-modal-button').html('Configurador de coche');
                    $('#feedback').append('Vehículo borrado correctamente'); // We add a message when the change is done
                    location.reload();
                }
            }]
        };
        modal(options, $('#modal-content'));
        $("#configcar-modal-button").click(function () {
            $('#modal-content').modal('openModal');
        });
        $("#llantas_ano").on('change', function () {
            $('.accept').prop('disabled', !$(this).val());
        }).trigger('change');

        $(document).on('change', '#llantas_marca', function () {
            changeModal($('#llantas_marca').val(), config.frameAction, '#llantas_modelo', '#llantas_ano');
        });
        $(document).on('change', '#llantas_modelo', function () {
            changeModal($('#llantas_modelo').val(), config.frameAction, '#llantas_ano');
        });
        $(document).on('change', '#llantas_ano', function () {
            changeModal($('#llantas_ano').val(), config.categoriesAttribute, '#compatible-options');
        });

        function changeModal(idOption, block, idOutput, idExtra = null, idButton = null) {
            let param = 'frame=' + idOption;
            $.ajax({
                showLoader: true,
                url: block,
                data: param,
                type: "GET",
                dataType: 'json'
            }).done(function (data) {
                $(idOutput).html(data.output);
                if (idExtra) {
                    $(idExtra).empty();
                    $(idExtra).append('<option selected="selected" value="">Seleccione una opción</option>');
                }
                if (idButton) {
                    $(idButton).html($.cookie('llantas_user_text'));
                }
            });
        }
    }
});
