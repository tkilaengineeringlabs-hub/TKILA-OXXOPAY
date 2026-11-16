
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';

const settings = getSetting( 'spin_data', {} );
const labelSpin = decodeEntities( settings.title );
const ICON_SIZE = 64;
/**
 * Content component
 */

const ContentSpin = () => {
	return decodeEntities( settings.description) ;
};


const LabelSpin = ( props ) => {
	const { PaymentMethodLabel } = props.components;

	const Icons = () => (
		<div style={{ display: 'flex',  alignItems: 'center' }}>
			<img src={`${wcSettings.pluginUrl}/images/oxxopay_b2c.svg`} alt="cash" style={{ marginLeft: '8px', width: `${ICON_SIZE}px`, height: 'auto' }} />
		</div>
	);

	return (
		<div style={{ display: 'flex', width: '99%', justifyContent: 'space-between', alignItems: 'center' }}>
			<PaymentMethodLabel text={ labelSpin } />
			<Icons />
		</div>
	);
};


/**
 * digitalfemsa payment method config object.
 */
const spin = {
	name:  settings.name,
	label: <LabelSpin />,
	content: <ContentSpin />,
	edit: <ContentSpin />,
	canMakePayment: () => {
		return  settings.is_enabled || false;
	},
	ariaLabel: labelSpin,
	supports: {},
	icons: [],
};


registerPaymentMethod( spin );
