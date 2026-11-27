/**
 * The three icons CnWalkthrough draws, as render functions.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * vue-material-design-icons ships single-file components, which this app has no
 * loader for. webpack.menu-walkthrough.config.js points the walkthrough's three
 * icon imports here; each draws the same markup as the package's component.
 *
 * @spec openspec/specs/apps-menu-walkthrough/spec.md#requirement-the-apps-menu-has-a-walkthrough-with-one-step-per-feature-req-tqwt-001
 */

import { h } from 'vue'

/**
 * An icon component for one path.
 *
 * @param {string} name The component name.
 * @param {string} cls The icon's class.
 * @param {string} d The path.
 * @return {object} The component.
 */
export function icon(name, cls, d) {
	return {
		name,
		emits: ['click'],
		props: {
			title: { type: String, default: undefined },
			fillColor: { type: String, default: 'currentColor' },
			size: { type: Number, default: 24 },
		},
		render() {
			return h('span', {
				'aria-hidden': this.title ? null : 'true',
				'aria-label': this.title,
				class: 'material-design-icon ' + cls,
				role: 'img',
				onClick: (event) => this.$emit('click', event),
			}, [
				h('svg', {
					fill: this.fillColor,
					class: 'material-design-icon__svg',
					width: this.size,
					height: this.size,
					viewBox: '0 0 24 24',
				}, [h('path', { d }, this.title ? [h('title', this.title)] : [])]),
			])
		},
	}
}

export const Close = icon('CloseIcon', 'close-icon', 'M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z')
export const ChevronLeft = icon('ChevronLeftIcon', 'chevron-left-icon', 'M15.41,16.58L10.83,12L15.41,7.41L14,6L8,12L14,18L15.41,16.58Z')
export const ChevronRight = icon('ChevronRightIcon', 'chevron-right-icon', 'M8.59,16.58L13.17,12L8.59,7.41L10,6L16,12L10,18L8.59,16.58Z')
