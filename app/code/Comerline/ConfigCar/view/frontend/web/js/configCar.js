define([
        "jquery", "Magento_Ui/js/modal/modal"
    ], function($, modal){
        var ConfigCarModal = {
            initModal: function(config, element) {
                $target = $(config.target);
                $target.modal();
                $element = $(element);
                $element.click(function() {
                    $target.modal('openModal');
                });
            }
        };
        return {
            'configCar': ConfigCarModal.initModal
        };
    }
);
