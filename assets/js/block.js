/*
 * Project: IconPress development candidate.
 * Development version: 2026.10.31.001-dev
 * Copyright (C) 2026 Matthew C. Murphy.
 * Author: https://MatthewxMurphy.com; product/support: https://builtnotbegged.com.
 * SPDX-License-Identifier: GPL-2.0-or-later
 */
/**
 * IconPress Wall block: editor side.
 * The front end is rendered on the server by IconPress_Render,
 * the same renderer used by the shortcode and the widget.
 */
(function (blocks, element, blockEditor, components, serverSideRender) {
	'use strict';

	var el = element.createElement;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var ServerSideRender = serverSideRender;

	blocks.registerBlockType('iconpress/icon-wall', {
		edit: function (props) {
			var attributes = props.attributes;

			function numberField(label, key) {
				return el(TextControl, {
					label: label,
					type: 'number',
					value: attributes[key] ? String(attributes[key]) : '',
					onChange: function (value) {
						var next = {};
						next[key] = parseInt(value, 10) || 0;
						props.setAttributes(next);
					}
				});
			}

			return el(
				element.Fragment,
				{},
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: 'Wall overrides', initialOpen: true },
						numberField('Columns (0 = use settings)', 'width'),
						numberField('Rows (0 = use settings)', 'height'),
						numberField('Icon size in px (0 = use settings; 16 or 32 free, Pro adds 64 and 128)', 'size')
					)
				),
				el(ServerSideRender, {
					block: 'iconpress/icon-wall',
					attributes: attributes
				})
			);
		},
		save: function () {
			return null;
		}
	});
})(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.serverSideRender
);
