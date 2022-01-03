var config = {
    map: {
        '*': {
            configurable: 'Comerline_ImprovedProduct/js/configurable'
        }
    },
    config: {
        mixins: {
            'Magento_ConfigurableProduct/js/configurable': {
                'Comerline_Syncg/js/configurable-mixin': true
            }
        }
    }
};
