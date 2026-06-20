/**
 * AROPay Blocks — Pesapal payment method for WooCommerce Blocks checkout.
 *
 * @package AROPay
 * @author  AROSOFT
 * @link    https://arosoftlabs.com/market/plugins/aropay
 */

const { registerPaymentMethod } = window.wc.blocksRegistry;
const { getSetting }            = window.wc.wcSettings;
const { createElement }         = window.wp.element;
const { decodeEntities }        = window.wp.htmlEntities;

const settings = getSetting( 'aropay_pesapal_data', {} );
const label    = decodeEntities( settings.title || 'Card / Mobile Money (Pesapal)' );

const AROPayPesapalContent = () => createElement(
    'div',
    { className: 'aropay-blocks-fields' },
    createElement( 'p', { className: 'aropay-description' },
        decodeEntities( settings.description || 'Pay securely with Visa, Mastercard, MTN MoMo or Airtel Money via Pesapal.' )
    ),
    createElement( 'p', { className: 'aropay-momo-hint' },
        'You will be redirected to Pesapal\'s secure payment page to complete your payment.'
    )
);

const AROPayPesapalLabel = () => createElement(
    'span',
    { className: 'aropay-blocks-label' },
    label,
    createElement( 'span', { className: 'aropay-icons' },
        createElement( 'span', { className: 'aropay-icon aropay-icon-visa',       title: 'Visa' } ),
        createElement( 'span', { className: 'aropay-icon aropay-icon-mastercard', title: 'Mastercard' } ),
        createElement( 'span', { className: 'aropay-icon aropay-icon-mtn',        title: 'MTN' } ),
        createElement( 'span', { className: 'aropay-icon aropay-icon-airtel',     title: 'Airtel' } )
    )
);

registerPaymentMethod({
    name:           'aropay_pesapal',
    label:          createElement( AROPayPesapalLabel ),
    content:        createElement( AROPayPesapalContent ),
    edit:           createElement( AROPayPesapalContent ),
    canMakePayment: () => true,
    ariaLabel:      label,
    supports: {
        features: settings.supports || [],
    },
});
