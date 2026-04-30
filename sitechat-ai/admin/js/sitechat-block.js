/* SiteChat AI — Gutenberg Block */

(function (blocks, blockEditor, components, element, i18n) {
	const el = element.createElement;
	const __ = i18n.__;
	const InspectorControls = blockEditor.InspectorControls;
	const PanelBody = components.PanelBody;
	const RangeControl = components.RangeControl;

	blocks.registerBlockType('sitechat-ai/chatbot', {
		title: __('SiteChat AI Chatbot', 'sitechat-ai'),
		description: __('Embed the AI chatbot inline on any page.', 'sitechat-ai'),
		category: 'widgets',
		icon: 'format-chat',
		attributes: {
			height: {
				type: 'number',
				default: 600,
			},
		},
		edit: function (props) {
			var height = props.attributes.height;

			return [
				el(InspectorControls, { key: 'controls' },
					el(PanelBody, { title: __('Chatbot Settings', 'sitechat-ai') },
						el(RangeControl, {
							label: __('Height (px)', 'sitechat-ai'),
							value: height,
							onChange: function (val) { props.setAttributes({ height: val }); },
							min: 300,
							max: 1200,
						})
					)
				),
				el('div', {
					key: 'preview',
					style: {
						height: height + 'px',
						background: '#f0f4ff',
						border: '2px dashed #93c5fd',
						borderRadius: '12px',
						display: 'flex',
						alignItems: 'center',
						justifyContent: 'center',
						flexDirection: 'column',
						gap: '12px',
						color: '#2563eb',
						fontFamily: 'sans-serif',
					}
				},
					el('span', { style: { fontSize: '40px' } }, '💬'),
					el('strong', {}, __('SiteChat AI Chatbot', 'sitechat-ai')),
					el('span', { style: { fontSize: '13px', color: '#6b7280' } },
						__('Height: ', 'sitechat-ai') + height + 'px'
					)
				)
			];
		},
		save: function () {
			return null; // Server-side render
		},
	});

}(
	window.wp.blocks,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.element,
	window.wp.i18n
));
