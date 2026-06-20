/**
 * AROPay Blocks — Yo Uganda payment method for WooCommerce Blocks checkout.
 *
 * @package AROPay
 * @author  AROSOFT
 * @link    https://arosoftlabs.com/market/plugins/aropay
 */

const { registerPaymentMethod } = window.wc.blocksRegistry;
const { getSetting }            = window.wc.wcSettings;
const { createElement, useState } = window.wp.element;
const { decodeEntities }        = window.wp.htmlEntities;

const settings = getSetting( 'aropay_yo_data', {} );
const label    = decodeEntities( settings.title || 'Mobile Money (MTN / Airtel)' );

/* ── Uganda phone prefix maps ─────────────────────────────── */
const MTN_PREFIXES    = ['77', '78', '76', '39', '31'];
const AIRTEL_PREFIXES = ['70', '75', '74', '20'];

function detectNetwork( phone ) {
    let digits = phone.replace(/\D/g, '');
    if ( digits.startsWith('256') ) digits = digits.slice(3);
    else if ( digits.startsWith('0') ) digits = digits.slice(1);
    const prefix = digits.slice(0, 2);
    if ( MTN_PREFIXES.includes(prefix) )    return 'mtn';
    if ( AIRTEL_PREFIXES.includes(prefix) ) return 'airtel';
    return null;
}

/* ── Content component ────────────────────────────────────── */
const AROPayYoContent = ( { eventRegistration, emitResponse } ) => {
    const [ phone,   setPhone   ] = useState('');
    const [ network, setNetwork ] = useState(null);

    const handlePhoneChange = (e) => {
        const val = e.target.value;
        setPhone( val );
        setNetwork( detectNetwork(val) );
    };

    return createElement(
        'div',
        { className: 'aropay-blocks-fields' },

        createElement( 'p', { className: 'aropay-description' },
            decodeEntities( settings.description || '' )
        ),

        createElement( 'div', { className: 'aropay-field-row' },
            createElement( 'label', { htmlFor: 'aropay_phone_block' }, 'Mobile Money Phone Number *' ),
            createElement( 'input', {
                type:        'tel',
                id:          'aropay_phone_block',
                name:        'aropay_phone',
                value:       phone,
                onChange:    handlePhoneChange,
                placeholder: 'e.g. 0771234567',
                className:   'aropay-phone-input',
                maxLength:   13,
            }),
            network && createElement( 'span', {
                className: 'aropay-network-badge ' + network
            }, network === 'mtn' ? 'MTN MoMo' : 'Airtel Money' )
        ),

        createElement( 'input', {
            type:  'hidden',
            name:  'aropay_network',
            value: network || 'mtn',
        }),

        createElement( 'p', { className: 'aropay-momo-hint' },
            'You will receive a payment prompt on this number. Enter your PIN to confirm.'
        )
    );
};

/* ── Label component ──────────────────────────────────────── */
const AROPayYoLabel = () => createElement(
    'span',
    { className: 'aropay-blocks-label' },
    label,
    createElement( 'span', { className: 'aropay-icons' },
        createElement( 'span', { className: 'aropay-icon aropay-icon-mtn',    title: 'MTN' } ),
        createElement( 'span', { className: 'aropay-icon aropay-icon-airtel', title: 'Airtel' } )
    )
);

/* ── Register ─────────────────────────────────────────────── */
registerPaymentMethod({
    name:              'aropay_yo',
    label:             createElement( AROPayYoLabel ),
    content:           createElement( AROPayYoContent ),
    edit:              createElement( AROPayYoContent ),
    canMakePayment:    () => true,
    ariaLabel:         label,
    supports: {
        features: settings.supports || [],
    },
});
