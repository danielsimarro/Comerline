define(['jquery'], function ($) {
    'use strict';

    return function (configurable) {
        $.widget('mage.configurable', $['mage']['configurable'], {

            /**
             * Configure an option, initializing it's state and enabling related options, which
             * populates the related option's selection and resets child option selections.
             * @private
             * @param {*} element - The element associated with a configurable option.
             */
            _configureElement: function (element) {
                this.simpleProduct = this._getSimpleProductId(element);

                if (element.value) {
                    this.options.state[element.config.id] = element.value;

                    if (element.nextSetting) {
                        element.nextSetting.disabled = false;
                        let nextId = element.nextSetting.id;
                        let nextSelectedVal = $("#" + nextId).val();
                        this._fillSelect(element.nextSetting);
                        this._resetChildren(element.nextSetting);
                        if (nextSelectedVal.length) {
                            $("#" + nextId + ' :nth-child(2)').prop('selected', true);
                            $("#" + nextId).change();
                        }
                    } else {
                        if (!!document.documentMode) { //eslint-disable-line
                            this.inputSimpleProduct.val(element.options[element.selectedIndex].config.allowedProducts[0]);
                        } else {
                            this.inputSimpleProduct.val(element.selectedOptions[0].config.allowedProducts[0]);
                        }
                    }
                } else {
                    this._resetChildren(element);
                }

                this._reloadPrice();
                this._displayRegularPriceBlock(this.simpleProduct);
                this._displayTierPriceBlock(this.simpleProduct);
                this._displayNormalPriceLabel();
                /** INIT MOD
                 *
                 *  Modification: deleted changeProductImage function to avoid it on configurable products
                 *
                 *  END MOD
                 * **/
            },

            /** INIT MOD **/

            /**
             * For a given option element, reset all of its selectable options. Clear any selected
             * index, disable the option choice, and reset the option's state if necessary.
             * @private
             * @param {*} element - The element associated with a configurable option.
             */
            _resetChildren: function (element) {
                if (element.childSettings) {
                    _.each(element.childSettings, function (set) {
                        // set.selectedIndex = 0;
                        set.disabled = true;
                    });

                    if (element.config) {
                        this.options.state[element.config.id] = false;
                    }
                }
            },

            /**
             * Populates an option's selectable choices.
             * @private
             * @param {*} element - Element associated with a configurable option.
             */
            _fillSelect: function (element) {
                let attributeId = element.id.replace(/[a-z]*/, ''),
                    options = this._getAttributeOptions(attributeId),
                    prevConfig,
                    index = 1,
                    allowedProducts,
                    i,
                    j;

                this._clearSelect(element);
                element.options[0] = new Option('', '');
                element.options[0].innerHTML = this.options.spConfig.chooseText;
                prevConfig = false;

                if (element.prevSetting) {
                    prevConfig = element.prevSetting.options[element.prevSetting.selectedIndex];
                }

                if (options) {
                    for (i = 0; i < options.length; i++) {
                        allowedProducts = [];
                        if (prevConfig) {
                            for (j = 0; j < options[i].products.length; j++) {
                                // prevConfig.config can be undefined
                                if (prevConfig.config &&
                                    prevConfig.config.allowedProducts &&
                                    prevConfig.config.allowedProducts.indexOf(options[i].products[j]) > -1) {
                                    allowedProducts.push(options[i].products[j]);
                                }
                            }
                        } else {
                            allowedProducts = options[i].products.slice(0);
                        }

                        if (allowedProducts.length > 0) {
                            options[i].allowedProducts = allowedProducts;
                            element.options[index] = new Option(this._getOptionLabel(options[i]), options[i].id);
                            if (typeof options[i].price !== 'undefined') {
                                element.options[index].setAttribute('price', options[i].prices);
                            }

                            element.options[index].config = options[i];


                            index++;
                        }
                        // Code added to select option
                        if (i === 0) {
                            this.options.values[attributeId] = options[i].id;
                        }
                    }
                    //Code added to check if configurations are set in url and resets them if needed
                    if (window.location.href.indexOf('#') !== -1) {
                        this._parseQueryParams(window.location.href.substr(window.location.href.indexOf('#') + 1));
                    }
                }
            },

            /** END MOD **/
        });
        return $['mage']['configurable'];
    };
});
