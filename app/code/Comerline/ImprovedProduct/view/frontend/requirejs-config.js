var config = {
    map: {
        '*': {
            configurable: 'Comerline_ImprovedProduct/js/configurable',
            advancedOptions: 'Comerline_ImprovedProduct/js/advancedOptions',
            minimumQty: 'Comerline_ImprovedProduct/js/minimumQty'
        }
    },
    config: {
        mixins: {
            'Magento_ConfigurableProduct/js/configurable': {
                'Comerline_ImprovedProduct/js/configurable-mixin': true
            }
        }
    }
};
