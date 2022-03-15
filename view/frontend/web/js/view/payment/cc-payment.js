define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'eupago_cc',
                component: 'Eupago_Cc/js/view/payment/method-renderer/cc-method'
            }
        );
        return Component.extend({});
    }
);