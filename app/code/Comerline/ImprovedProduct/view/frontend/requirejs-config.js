var config = {
    map: {
        '*': {
            advancedOptions: 'Comerline_ImprovedProduct/js/advancedOptions',
            minimumQty: 'Comerline_ImprovedProduct/js/minimumQty'
        }
    },
    config: {
        mixins: {
            'Magento_ConfigurableProduct/js/configurable': {
                'Comerline_ImprovedProduct/js/configurable': true
            }
        }
    }
};
