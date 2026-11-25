/**
 * @vitest-environment jsdom
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The DOM helpers of js/lib/layerSwap.js beside `swap()`'s failure path:
 * which elements belong to a set, where a new run goes, the elements a layer
 * becomes, inline layers in a swap, a sheet that never answers, and the
 * stylesheet refresh after a theming sync.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import layerSwap from '../../js/lib/layerSwap.js'

const {
	findLayerElements,
	insertBeforeAnchor,
	createLayerElement,
	swap,
	refreshStylesheets,
	refreshThemeStylesheets,
} = layerSwap

const link = (href, extra = '') => `<link rel="stylesheet" href="${href}"${extra}>`

describe('the layer DOM helpers', () => {
	beforeEach(() => {
		document.head.innerHTML = ''
	})

	afterEach(() => {
		vi.useRealTimers()
	})

	it('finds the file and inline elements of a manifest in document order, whatever their cache-buster', () => {
		document.head.innerHTML =
			'<style id="nldesign-logo">a{}</style>'
			+ link('/apps/thematiq/css/tokens/utrecht.css?v=9')
			+ link('/apps/thematiq/css/other.css')
			+ '<style id="unrelated">b{}</style>'
			+ link('https://cloud.example.org/apps/thematiq/css/shared.css?v=1#x')

		const found = findLayerElements(document, {
			layers: [
				{ kind: 'file', href: '/apps/thematiq/css/shared.css?v=2' },
				{ kind: 'inline', id: 'nldesign-logo' },
				{ kind: 'file', href: '/apps/thematiq/css/tokens/utrecht.css?v=1' },
			],
		})

		expect(found.map((el) => el.id || el.getAttribute('href'))).toEqual([
			'nldesign-logo',
			'/apps/thematiq/css/tokens/utrecht.css?v=9',
			'https://cloud.example.org/apps/thematiq/css/shared.css?v=1#x',
		])
		expect(findLayerElements(document, null)).toEqual([])
		expect(findLayerElements(document, {})).toEqual([])
	})

	it('anchors a new run before the admin layers, else before core theming, else nowhere', () => {
		document.head.innerHTML =
			link('/apps/theming/theme/default.css', ' class="theme"')
			+ link('/apps/thematiq/css/custom-overrides.css?v=3')
		expect(insertBeforeAnchor(document).getAttribute('href')).toContain(
			'custom-overrides',
		)

		document.head.innerHTML = link(
			'/apps/theming/theme/default.css',
			' class="theme"',
		)
		expect(insertBeforeAnchor(document).className).toBe('theme')

		document.head.innerHTML = link('/apps/thematiq/css/tokens/utrecht.css')
		expect(insertBeforeAnchor(document)).toBeNull()
	})

	it('makes a file layer a stylesheet link and an inline layer a style, each marked with its layer', () => {
		const file = createLayerElement(document, {
			kind: 'file',
			href: '/a.css',
			layer: 'tokens',
		})
		expect(file.tagName).toBe('LINK')
		expect(file.rel).toBe('stylesheet')
		expect(file.getAttribute('href')).toBe('/a.css')
		expect(file.getAttribute('data-nldesign-layer')).toBe('tokens')
		expect(
			createLayerElement(document, {
				kind: 'file',
				href: '/b.css',
			}).getAttribute('data-nldesign-layer'),
		).toBe('file')

		const inline = createLayerElement(document, {
			kind: 'inline',
			id: 'nldesign-logo',
			css: ':root{}',
			layer: 'logo',
		})
		expect(inline.tagName).toBe('STYLE')
		expect(inline.id).toBe('nldesign-logo')
		expect(inline.textContent).toBe(':root{}')
		expect(inline.getAttribute('data-nldesign-layer')).toBe('logo')

		const bare = createLayerElement(document, { kind: 'inline' })
		expect(bare.hasAttribute('id')).toBe(false)
		expect(bare.textContent).toBe('')
		expect(bare.getAttribute('data-nldesign-layer')).toBe('inline')
	})

	it('swaps an inline-only run at once, in the place of the old one, and drops the old style with the same id', async () => {
		document.head.innerHTML =
			link('/first.css')
			+ '<style id="nldesign-logo">old{}</style>'
			+ link('/last.css')
		const result = await swap(
			document,
			{ layers: [{ kind: 'inline', id: 'nldesign-logo' }] },
			{ layers: [{ kind: 'inline', id: 'nldesign-logo', css: 'new{}' }] },
		)

		expect(result.ok).toBe(true)
		expect(result.removed).toHaveLength(1)
		const styles = document.head.querySelectorAll('style#nldesign-logo')
		expect(styles).toHaveLength(1)
		expect(styles[0].textContent).toBe('new{}')
		expect(styles[0].previousElementSibling.getAttribute('href')).toBe(
			'/first.css',
		)
		expect(styles[0].nextElementSibling.getAttribute('href')).toBe('/last.css')
	})

	it('puts a run without an old one before the admin layers, or at the end of the head', async () => {
		document.head.innerHTML = link('/apps/thematiq/css/custom-overrides.css')
		await swap(document, null, {
			layers: [
				{ kind: 'inline', id: 'a' },
				{ kind: 'inline', id: 'b' },
			],
		})
		expect(
			Array.from(document.head.children).map((el) => el.id || 'overrides'),
		).toEqual(['a', 'b', 'overrides'])

		document.head.innerHTML = '<meta charset="utf-8">'
		await swap(
			document,
			{ layers: [] },
			{ layers: [{ kind: 'inline', id: 'c' }] },
		)
		expect(document.head.lastElementChild.id).toBe('c')

		const nothing = await swap(document, null, null)
		expect(nothing).toEqual({ ok: true, added: [], removed: [] })
	})

	it('rolls back a sheet that never answers, after the timeout', async () => {
		vi.useFakeTimers()
		document.head.innerHTML = link('/apps/thematiq/css/tokens/old.css')
		const done = swap(
			document,
			{
				layers: [
					{ kind: 'file', href: '/apps/thematiq/css/tokens/old.css' },
				],
			},
			{
				layers: [
					{ kind: 'file', href: '/apps/thematiq/css/tokens/new.css' },
				],
			},
		)
		expect(document.head.querySelectorAll('link')).toHaveLength(2)

		await vi.advanceTimersByTimeAsync(10000)
		const result = await done

		expect(result).toEqual({ ok: false, added: [], removed: [] })
		expect(
			Array.from(document.head.querySelectorAll('link')).map((el) =>
				el.getAttribute('href'),
			),
		).toEqual(['/apps/thematiq/css/tokens/old.css'])
	})

	it('counts a sheet that loads and then errors as loaded', async () => {
		document.head.innerHTML = ''
		const done = swap(document, null, {
			layers: [{ kind: 'file', href: '/x.css' }],
		})
		const added = document.head.querySelector('link')
		added.dispatchEvent(new window.Event('load'))
		added.dispatchEvent(new window.Event('error'))

		const result = await done
		expect(result.ok).toBe(true)
		expect(document.head.querySelector('link')).toBe(added)
	})

	it('refreshes the links a selector matches by bumping their version', () => {
		document.head.innerHTML =
			link('/apps/theming/theme/default.css?v=1')
			+ link('/apps/theming/theme/light.css')
			+ link('/apps/thematiq/css/custom-overrides.css?v=4')

		const themed = refreshThemeStylesheets(document, '77')
		expect(themed.map((el) => el.getAttribute('href'))).toEqual([
			'/apps/theming/theme/default.css?v=77',
			'/apps/theming/theme/light.css?v=77',
		])

		const overrides = refreshStylesheets(
			document,
			'link[href*="custom-overrides"]',
			'5',
		)
		expect(overrides[0].getAttribute('href')).toBe(
			'/apps/thematiq/css/custom-overrides.css?v=5',
		)
		expect(refreshStylesheets(document, 'link.none')).toEqual([])
	})

	it('stamps the refresh with the time when no version is given', () => {
		vi.spyOn(Date, 'now').mockReturnValue(1234)
		document.head.innerHTML = link('/apps/theming/theme/default.css')
		refreshThemeStylesheets(document)
		expect(document.head.querySelector('link').getAttribute('href')).toBe(
			'/apps/theming/theme/default.css?v=1234',
		)
		vi.restoreAllMocks()
	})
})
