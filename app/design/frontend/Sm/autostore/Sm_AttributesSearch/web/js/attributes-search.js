/**
 * @category Magentech
 * @package Sm_AttributesSearch
 * @version 1.0.0
 * @copyright Copyright (c) 2018 YouTech Company. All Rights Reserved.
 * @license - Copyrighted Commercial Software
 * @author YouTech Company <contact@ytcvn.com>
 * @link http://www.magentech.com
 */

define(
    [
        "jquery",
        "underscore",
        "jquery/ui",
        'mage/translate',
        "Sm_AttributesSearch/js/chosen"
    ], function ($, _) {
        $.widget(
            'Attributes.SearchAttributes', {
                options: {
                    elementId: 'attributessearch',
                    actionUrl: '',
                    selectElement: '.smas-select',
                    eleLoading: '.smas-loading',
                    eleSearch: '.btn-search',
                    eleReset: '.btn-reset',
                    selectsArr: [],
                },

                _create: function () {
                    var _self = this, _options = _self.options, _element = $('#' + _options.elementId);
                    _options.selectsArr = _element.find(_options.selectElement);
                    var _codeFirst = _options.selectsArr.first().attr('name');
                    _.each(
                        _options.selectsArr, function (ele) {
                            var _el = $(ele);
                            if (_el.is('select')) {
                                _el.chosen();
                            }
                        }
                    );
                    _self._loadFirst(_codeFirst);
                    _element.on('change', _options.selectElement, _self, _self._onSelectChange);
                },

                _loadFirst: function (code) {
                    var _self = this, _options = _self.options, _element = $('#' + _options.elementId),
                        _eleLoading = $(_options.eleLoading, _element),
                        _select = $('select[name=' + code + ']', _element);
                    $.ajax(
                        {
                            url: _options.actionUrl + '?as=1&filtersList=1&' + code,
                            data: {
                                code: code
                            },
                            type: 'GET',
                            dataType: 'json',
                            beforeSend: function () {
                                _eleLoading.show();
                            },
                            success: function (json) {
                                _select.empty().append(json.contents);
                                _select.trigger("chosen:updated");
                                _eleLoading.hide();
                            }
                        }
                    );
                    return false;
                },

                _onSelectChange: function (eve) {
                    var _idsProducts, _valueOpt, _currOpt, _select = this,
                        _self = eve.data, _options = _self.options, _selectLoad = null;
                    if ($(_select).is('select')) {
                        _valueOpt = _select.value;
                        _idsProducts = $('option[value=' + _valueOpt + ']', $(_select)).attr('data-ids');
                    }

                    for (var i = 0; i < _options.selectsArr.length; i++) {
                        if (_options.selectsArr[i].id == _select.id && i != _options.selectsArr.length - 1) {
                            _selectLoad = _options.selectsArr[i + 1];
                            break;
                        }
                    }

                    _self._clearAllValues(_select);
                    if (_selectLoad && _valueOpt != 0 && _idsProducts != "") {
                        _currOpt = $(_select).attr('data-attr-id');
                        _self._loadSelectValues(_selectLoad, _idsProducts, _currOpt);
                    }
                },

                _loadSelectValues: function (selectLoad, idsProducts, currOpt) {
                    var _self = this, _options = _self.options, _selectLoad = $(selectLoad),
                        _element = $('#' + _options.elementId), _code = _selectLoad.attr('name'),
                        _eleLoading = $(_options.eleLoading, _element);
                    var _form = _element.find('form'), _params = _form.serialize();
                    if (_code == null) {
                        return false;
                    }
                    $.ajax(
                        {
                            url: _options.actionUrl + '?as=1&filtersList=1&' + _params + '&' + _code,
                            type: 'GET',
                            data: {
                                code: _code
                            },
                            dataType: 'json',
                            beforeSend: function () {
                                _eleLoading.show();
                            },
                            success: function (json) {
                                _eleLoading.hide();
                                var _selectValue = 0;
                                _isSelect = _selectLoad.is('select');
                                _selectLoad = _isSelect ? _selectLoad : _selectLoad.next();
                                _selectLoad.empty().append(json.contents);
                                if (_selectLoad.children().length) {
                                    $('[name=' + _code + ']', _element).removeAttr("disabled");
                                    _selectValue = _isSelect ? _selectLoad.val() : _selectValue;
                                }

                                if (_selectValue && _selectValue != 0) {
                                    if (_isSelect) {
                                        _selectLoad.change();
                                    } else {
                                        _selectLoad.prev().trigger('change');
                                    }
                                }

                                if (_isSelect) {
                                    _selectLoad.trigger("chosen:updated");
                                }
                            }
                        }
                    );
                    return false;
                },

                _clearAllValues: function (_select) {
                    var _self = this, _options = _self.options, _startClear = false,
                        _element = $('#' + _options.elementId),
                        _currSelect, _isSelect, _eleSearch = $(_options.eleSearch, _element),
                        _eleReset = $(_options.eleReset, _element);
                    for (var i = 0; i < _options.selectsArr.length; i++) {
                        _currSelect = $(_options.selectsArr[i]);
                        _isSelect = _currSelect.is('select');

                        if (_startClear) {
                            _currSelect.attr("disabled", "disabled");
                            if (!_isSelect) {
                                _currSelect.next().children('option:not(:first)').remove();
                            } else {
                                _currSelect.children('option:not(:first)').remove();
                            }
                        }
                        if (_options.selectsArr[i].id == _select.id) {
                            _startClear = true;
                            if (i == 0) {
                                if (_select.value != 0) {
                                    _eleSearch.removeAttr("disabled");
                                    _eleReset.show();
                                    _self._btnAction(_select);
                                } else {
                                    _eleSearch.attr("disabled", "disabled");
                                    _eleReset.hide();
                                }
                            }
                        }
                        if (_isSelect) {
                            _currSelect.trigger("chosen:updated");
                        }
                    }
                },

                _btnAction: function (_select) {
                    var _self = this, _options = _self.options, _element = $('#' + _options.elementId),
                        _eleReset = $(_options.eleReset, _element), _eleSearch = $(_options.eleSearch, _element);
                    _eleReset.off('click').on(
                        'click', function (e) {
                            e.preventDefault();
                            $(_select).val("0");
                            _self._clearAllValues(_select);
                            return false;
                        }
                    );
                    _eleSearch.off('click').on(
                        'click', function (e) {
                            e.preventDefault();
                            var _that = $(this), _form = _that.parents('form'), _urlAction,
                                _attName, _params = _form.serialize();
                            for (var i = 0; i < _options.selectsArr.length; i++) {
                                if (_options.selectsArr[i].value <= 0) {
                                    _attName = _options.selectsArr[i].name;
                                    console.log(_attName);
                                    break;
                                }
                            }
                            _params = _params.replace('&' + _attName + '=0', '');
                            _urlAction = _options.actionUrl + '?as=1&' + _params;
                            _form.attr('action', _urlAction);
                            window.location = _urlAction;
                            return true;
                        }
                    );
                },
            }
        );
        return $.Attributes.SearchAttributes;
    }
);

