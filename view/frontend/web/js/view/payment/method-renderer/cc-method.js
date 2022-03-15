 define(
     [
         'Magento_Checkout/js/view/payment/default'
     ],
     function (Component) {
         'use strict';
         return Component.extend({
            defaults: {
                template: 'Eupago_Cc/payment/cc-form',
                cardNumber: '',
                criptoNumber: '',
                cardDate: ''
            },

            initObservable: function () {

                this._super()
                    .observe([
                        'cardNumber',
                        'criptoNumber',
                        'cardDate'


                    ]);
                return this;
            },


            getCode: function () {
                return 'eupago_cc';
            },

            isActive: function () {
                return true;
            },
            getData: function () {
                return {
                    'method': this.item.method,
                    'additional_data': {
                        'card_number': this.cardNumber(),
                        'cripto_number': this.criptoNumber(),
                        'card_date': this.cardDate() 

                    }
                };
            },
            /**
             * Get value of instruction field.
             * @returns {String}
             */
            getInstructions: function () {
                return window.checkoutConfig.payment.instructions[this.item.method];
            }
        });
     }
);