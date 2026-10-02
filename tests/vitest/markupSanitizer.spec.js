/**
 * @vitest-environment jsdom
 *
 * js/lib/markupSanitizer.js: the allowlist, the removal report, and markup that must
 * come through unchanged.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/own-component-preview/spec.md#requirement-markup-is-cleaned-against-an-allowlist-every-time-it-renders
 */

import { describe, expect, it } from 'vitest'
import sanitizer from '../../js/lib/markupSanitizer.js'

const { sanitizeHtml, sanitizeCss } = sanitizer

describe('markupSanitizer', () => {
	it('removes a javascript link and names the href', () => {
		const out = sanitizeHtml('<a href="javascript:alert(1)">Open</a>')
		expect(out.html).toBe('<a>Open</a>')
		expect(out.removed).toEqual({ 'script link': 1 })
	})

	it('lets allowed structure through unchanged', () => {
		const html =
			'<nav aria-label="Menu"><ul><li><a href="#start" class="link">Start</a></li></ul></nav>'
		const out = sanitizeHtml(html)
		expect(out.html).toBe(html)
		expect(out.removed).toEqual({})
	})

	it('removes a script and an event handler, and reports both', () => {
		const out = sanitizeHtml(
			'<button onclick="alert(1)">Test</button><script>alert(2)</script>',
		)
		expect(out.html).toBe('<button>Test</button>')
		expect(out.removed).toEqual({ script: 1, 'event handler': 1 })
	})

	it.each([
		['<iframe src="x"></iframe>', ''],
		['<object data="x"></object>', ''],
		['<embed src="x">', ''],
		['<link rel="stylesheet" href="x">', ''],
		['<meta http-equiv="refresh" content="0">', ''],
		['<base href="https://example.org/">', ''],
		['<form action="https://example.org"><input name="a"></form>', ''],
		['<template><p>x</p></template>', ''],
		['<style>p{color:red}</style>', ''],
	])('removes %s with its content', (input, expected) => {
		const out = sanitizeHtml(input)
		expect(out.html).toBe(expected)
		expect(out.removed.element).toBe(1)
	})

	it('removes an external src and keeps a data image', () => {
		const out = sanitizeHtml(
			'<img src="https://example.org/pixel.gif" alt="a"><img src="data:image/png;base64,AAAA" alt="b">',
		)
		expect(out.html).toBe(
			'<img alt="a"><img src="data:image/png;base64,AAAA" alt="b">',
		)
		expect(out.removed).toEqual({ 'external address': 1 })
	})

	it('removes url() to a remote host in style attributes and in the CSS field', () => {
		const html = sanitizeHtml(
			'<div style="background: url(https://example.org/a.png); color: red">x</div>',
		)
		expect(html.html).toBe('<div style="background: none; color: red">x</div>')
		const css = sanitizeCss(
			'@import url(https://example.org/x.css); .a { background: url("https://example.org/b.png") } .b { background: url(data:image/png;base64,AA) } </style><script>',
		)
		// Without `</style` the rest cannot leave the frame's style element; it is inert CSS text.
		expect(css.css).toBe(
			' .a { background: none } .b { background: url(data:image/png;base64,AA) } ><script>',
		)
		expect(css.removed).toEqual({
			import: 1,
			'external address': 1,
			'style tag': 1,
		})
	})

	it('keeps SVG drawings', () => {
		const html =
			'<svg viewBox="0 0 10 10" aria-hidden="true"><path d="M0 0h10v10z" fill="currentColor"></path></svg>'
		expect(sanitizeHtml(html).html).toBe(html)
	})
})
