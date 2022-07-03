define(['jquery', 'priceUtils'], function ($, priceUtils) {
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
                var attributeId = element.id.replace(/[a-z]*/, ''),
                    options = this._getAttributeOptions(attributeId),
                    prevConfig,
                    index = 1,
                    allowedProducts,
                    allowedProductsByOption,
                    allowedProductsAll,
                    i,
                    j,
                    finalPrice = parseFloat(this.options.spConfig.prices.finalPrice.amount),
                    optionFinalPrice,
                    optionPriceDiff,
                    optionPrices = this.options.spConfig.optionPrices,
                    allowedOptions = [],
                    indexKey,
                    allowedProductMinPrice,
                    allowedProductsAllMinPrice;

                this._clearSelect(element);
                element.options[0] = new Option('', '');
                element.options[0].innerHTML = this.options.spConfig.chooseText;
                prevConfig = false;

                if (element.prevSetting) {
                    prevConfig = element.prevSetting.options[element.prevSetting.selectedIndex];
                }

                if (options) {
                    for (indexKey in this.options.spConfig.index) {
                        /* eslint-disable max-depth */
                        if (this.options.spConfig.index.hasOwnProperty(indexKey)) {
                            allowedOptions = allowedOptions.concat(_.values(this.options.spConfig.index[indexKey]));
                        }
                    }

                    if (prevConfig) {
                        allowedProductsByOption = {};
                        allowedProductsAll = [];

                        for (i = 0; i < options.length; i++) {
                            /* eslint-disable max-depth */
                            for (j = 0; j < options[i].products.length; j++) {
                                // prevConfig.config can be undefined
                                if (prevConfig.config &&
                                    prevConfig.config.allowedProducts &&
                                    prevConfig.config.allowedProducts.indexOf(options[i].products[j]) > -1) {
                                    if (!allowedProductsByOption[i]) {
                                        allowedProductsByOption[i] = [];
                                    }
                                    allowedProductsByOption[i].push(options[i].products[j]);
                                    allowedProductsAll.push(options[i].products[j]);
                                }
                            }
                        }

                        if (typeof allowedProductsAll[0] !== 'undefined' &&
                            typeof optionPrices[allowedProductsAll[0]] !== 'undefined') {
                            allowedProductsAllMinPrice = this._getAllowedProductWithMinPrice(allowedProductsAll);
                            finalPrice = parseFloat(optionPrices[allowedProductsAllMinPrice].finalPrice.amount);
                        }
                    }

                    for (i = 0; i < options.length; i++) {
                        if (prevConfig && typeof allowedProductsByOption[i] === 'undefined') {
                            continue; //jscs:ignore disallowKeywords
                        }

                        allowedProducts = prevConfig ? allowedProductsByOption[i] : options[i].products.slice(0);
                        optionPriceDiff = 0;

                        if (typeof allowedProducts[0] !== 'undefined' &&
                            typeof optionPrices[allowedProducts[0]] !== 'undefined') {
                            allowedProductMinPrice = this._getAllowedProductWithMinPrice(allowedProducts);
                            optionFinalPrice = parseFloat(optionPrices[allowedProductMinPrice].finalPrice.amount);
                            optionPriceDiff = optionFinalPrice - finalPrice;
                            options[i].label = options[i].initialLabel;

                            if (optionPriceDiff !== 0) {
                                options[i].label += ' ' + priceUtils.formatPrice(
                                    optionPriceDiff,
                                    this.options.priceFormat,
                                    true
                                );
                            }
                        }

                        if (allowedProducts.length > 0 || _.include(allowedOptions, options[i].id)) {
                            options[i].allowedProducts = allowedProducts;
                            element.options[index] = new Option(this._getOptionLabel(options[i]), options[i].id);

                            if (typeof options[i].price !== 'undefined') {
                                element.options[index].setAttribute('price', options[i].price);
                            }

                            if (allowedProducts.length === 0) {
                                element.options[index].disabled = true;
                            }

                            element.options[index].config = options[i];
                            index++;
                        }
                        /* eslint-enable max-depth */
                    }

                    /** INI MOD **/
                    for (i = 0; i < options.length; i++) {
                        if (options[i].allowedProducts && options[i].allowedProducts.length > 0) {
                            // Selected fist options available
                            this.options.values[attributeId] = options[i].id;
                            break;
                        }
                    }
                    /** END MOD **/
                }
            },

            /** END MOD **/
        });
        return $['mage']['configurable'];
    };
});
