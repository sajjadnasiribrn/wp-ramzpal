( function () {
	'use strict';

	const settings = window.wc.wcSettings.getSetting( 'ramzpal_data', {} );
	const decode = window.wp.htmlEntities.decodeEntities;
	const createElement = window.wp.element.createElement;
	const title = decode( settings.title || 'پرداخت با تتر (رمزپال)' );

	const label = createElement(
		'span',
		{ className: 'ramzpal-block-label' },
		createElement( 'img', {
			src: settings.icon,
			alt: '',
			width: 28,
			height: 28,
			style: { marginInlineEnd: '10px', verticalAlign: 'middle' },
		} ),
		createElement( 'span', null, title )
	);

	const Content = function () {
		return createElement(
			'p',
			{ className: 'ramzpal-block-description' },
			decode( settings.description || 'مبلغ سفارش را با تتر و از طریق درگاه رمزپال پرداخت کنید.' )
		);
	};

	window.wc.wcBlocksRegistry.registerPaymentMethod( {
		name: 'ramzpal',
		label: label,
		content: createElement( Content ),
		edit: createElement( Content ),
		canMakePayment: function () { return true; },
		ariaLabel: title,
		supports: { features: settings.supports || [ 'products' ] },
	} );
}() );
