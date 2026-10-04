/**
 * @vitest-environment jsdom
 *
 * js/lib/ownComponentFrame.js: the sandbox and the frame policy are pinned, the names a
 * component reads are scanned, and the bridge copies their values onto the frame.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/own-component-preview/spec.md#requirement-pasted-markup-renders-in-a-sandboxed-frame-without-scripts
 */

import { describe, expect, it } from 'vitest'
import frame from '../../js/lib/ownComponentFrame.js'

describe('ownComponentFrame', () => {
	it('builds the frame with exactly allow-same-origin', () => {
		const iframe = frame.createFrame(document, 'Your component: Afvalkaart')
		expect(iframe.getAttribute('sandbox')).toBe('allow-same-origin')
		expect(iframe.getAttribute('sandbox')).not.toContain('allow-scripts')
		expect(iframe.getAttribute('title')).toBe('Your component: Afvalkaart')
	})

	it('starts the frame document with the fixed policy', () => {
		const doc = frame.srcdoc('<p>x</p>', 'p{color:red}', '')
		expect(frame.POLICY).toBe(
			"default-src 'none'; style-src 'unsafe-inline'; font-src 'self' data:; img-src 'self' data:",
		)
		expect(doc).toContain(
			'<meta http-equiv="Content-Security-Policy" content="'
				+ frame.POLICY
				+ '">',
		)
		expect(doc.indexOf('Content-Security-Policy')).toBeLessThan(
			doc.indexOf('<style>'),
		)
		expect(doc).not.toMatch(/script-src/)
	})

	it('names the page origin next to self for fonts, and nothing else (#940)', () => {
		expect(
			frame.srcdoc('<p>x</p>', '', '', 'https://cloud.example.nl:8443'),
		).toContain(
			"font-src 'self' https://cloud.example.nl:8443 data:; img-src 'self' data:",
		)
		expect(frame.policyFor('https://x.nl" onload="a')).toBe(frame.POLICY)
		expect(frame.policyFor('')).toBe(frame.POLICY)
	})

	it('scans the names a component reads, from the CSS and style attributes', () => {
		expect(
			frame.scanVars(
				'<p style="color: var(--nldesign-org-brand-accent)">x</p>',
				'.card { background: var(--nldesign-color-primary); border: 1px solid var( --nldesign-color-primary ) }',
			),
		).toEqual(['--nldesign-color-primary', '--nldesign-org-brand-accent'])
	})

	it('copies the scanned names onto the frame root, the bridge a token edit repaints through', () => {
		const iframe = frame.createFrame(document, 'x')
		document.body.appendChild(iframe)
		const values = { '--nldesign-color-primary': '#154273' }
		expect(
			frame.applyVars(
				iframe,
				['--nldesign-color-primary', '--missing'],
				(n) => values[n],
			),
		).toBe(1)
		const rootStyle = iframe.contentDocument.documentElement.style
		expect(rootStyle.getPropertyValue('--nldesign-color-primary')).toBe(
			'#154273',
		)

		values['--nldesign-color-primary'] = '#c00000'
		frame.applyVars(iframe, ['--nldesign-color-primary'], (n) => values[n])
		expect(rootStyle.getPropertyValue('--nldesign-color-primary')).toBe(
			'#c00000',
		)
	})
})
