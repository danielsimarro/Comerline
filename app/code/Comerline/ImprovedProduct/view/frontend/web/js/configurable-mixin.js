define([
    'mage/utils/wrapper',
    'jquery',
    'underscore',
    'jquery-ui-modules/widget',
    'jquery/jquery.parsequery',
    'fotoramaVideoEvents'
], function (wrapper, $, _) {
    'use strict';

    const resetLabels = function (attributes) {
        $('select.super-attribute-select option').each(function () {
            const selectOption = $(this),
                optionId = selectOption.attr('value');

            _.each(attributes, function ({options}) {
                options.forEach(({id, initialLabel}) => {
                    if (id === optionId) {
                        selectOption.text(initialLabel);
                    }
                });
            })
        })
    };

    return function (configurable) {
        return wrapper.wrap(configurable, function (configurable, config, element) {
            configurable(config, element);
            resetLabels(config.spConfig.attributes);
        });
    }
});
