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
export const Check = icon('CheckIcon', 'check-icon', 'M21,7L9,19L3.5,13.5L4.91,12.09L9,16.17L19.59,5.59L21,7Z')
export const AlertCircleOutline = icon('AlertCircleOutlineIcon', 'alert-circle-outline-icon', 'M11,15H13V17H11V15M11,7H13V13H11V7M12,2C6.47,2 2,6.5 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20Z')
export const CircleOutline = icon('CircleOutlineIcon', 'circle-outline-icon', 'M12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2Z')
export const HelpCircleOutline = icon('HelpCircleOutlineIcon', 'help-circle-outline-icon', 'M11,18H13V16H11V18M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,20C7.59,20 4,16.41 4,12C4,7.59 7.59,4 12,4C16.41,4 20,7.59 20,12C20,16.41 16.41,20 12,20M12,6A4,4 0 0,0 8,10H10A2,2 0 0,1 12,8A2,2 0 0,1 14,10C14,12 11,11.75 11,15H13C13,12.75 16,12.5 16,10A4,4 0 0,0 12,6Z')
export const Magnify = icon('MagnifyIcon', 'magnify-icon', 'M9.5,3A6.5,6.5 0 0,1 16,9.5C16,11.11 15.41,12.59 14.44,13.73L14.71,14H15.5L20.5,19L19,20.5L14,15.5V14.71L13.73,14.44C12.59,15.41 11.11,16 9.5,16A6.5,6.5 0 0,1 3,9.5A6.5,6.5 0 0,1 9.5,3M9.5,5C7,5 5,7 5,9.5C5,12 7,14 9.5,14C12,14 14,12 14,9.5C14,7 12,5 9.5,5Z')
