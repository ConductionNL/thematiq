/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for js/lib/layerSwap.js — the pure parts of applying a token set
 * to the page without a reload: how a page element and a manifest layer are
 * matched (by pathname, never by cache-busting query), what a swap changes,
 * and how a stylesheet is re-requested. The DOM half (swap, findLayerElements,
 * refreshStylesheets) is covered by the Playwright workflow
 * tests/e2e/workflows/apply-without-reload.workflow.spec.ts, because it needs
 * real stylesheet `load` events.
 */

import { describe, it, expect } from 'vitest'
import layerSwap from '../../js/lib/layerSwap.js'

const { pathnameOf, layerKey, diffLayers, bumpVersion } = layerSwap

describe('pathnameOf', () => {
	it('strips origin, query and hash', () => {
		expect(
			pathnameOf(
				'https://cloud.example/custom_apps/thematiq/css/tokens/x.css?v=abc-36#top',
			),
		).toBe('/custom_apps/thematiq/css/tokens/x.css')
	})

	it('leaves a root-relative path alone apart from the query', () => {
		expect(pathnameOf('/custom_apps/thematiq/css/tokens/x.css?v=1.1.12')).toBe(
			'/custom_apps/thematiq/css/tokens/x.css',
		)
	})

	it('tolerates empty input', () => {
		expect(pathnameOf('')).toBe('')
		expect(pathnameOf(undefined)).toBe('')
	})
})

describe('layerKey', () => {
	it('keys a file layer by pathname so two cache-busters are the same file', () => {
		const server = {
			kind: 'file',
			href: '/custom_apps/thematiq/css/tokens/a.css?v=d98319d8-36',
		}
		const manifest = {
			kind: 'file',
			href: '/custom_apps/thematiq/css/tokens/a.css?v=1.1.12',
		}
		expect(layerKey(server)).toBe(layerKey(manifest))
		expect(layerKey(server)).toBe('file:/custom_apps/thematiq/css/tokens/a.css')
	})

	it('keys an inline layer by its element id', () => {
		expect(
			layerKey({ kind: 'inline', id: 'nldesign-logo-url', css: ':root{}' }),
		).toBe('inline:nldesign-logo-url')
	})
})

describe('diffLayers', () => {
	const designSystem = [
		{
			kind: 'file',
			layer: 'design-system',
			href: '/a/css/systems/nldesign/theme.css?v=1',
		},
		{
			kind: 'file',
			layer: 'design-system',
			href: '/a/css/systems/nldesign/overrides.css?v=1',
		},
	]
	const amsterdam = designSystem.concat([
		{ kind: 'file', layer: 'tokens', href: '/a/css/tokens/amsterdam.css?v=1' },
		{
			kind: 'inline',
			layer: 'logo-url',
			id: 'nldesign-logo-url',
			css: ':root{--nldesign-logo-url:url(/a/img/logos/amsterdam.svg)}',
		},
	])
	const zwolle = designSystem.concat([
		{ kind: 'file', layer: 'tokens', href: '/a/css/tokens/zwolle.css?v=2' },
		{
			kind: 'inline',
			layer: 'logo-url',
			id: 'nldesign-logo-url',
			css: ':root{--nldesign-logo-url:none}',
		},
	])

	it('reports the set-specific file as removed and added, the shared ones as unchanged', () => {
		const diff = diffLayers(amsterdam, zwolle)
		expect(diff.removed.map(layerKey)).toEqual([
			'file:/a/css/tokens/amsterdam.css',
		])
		expect(diff.added.map(layerKey)).toEqual(['file:/a/css/tokens/zwolle.css'])
		expect(diff.unchanged.map(layerKey)).toEqual([
			'file:/a/css/systems/nldesign/theme.css',
			'file:/a/css/systems/nldesign/overrides.css',
			'inline:nldesign-logo-url',
		])
	})

	it('treats stock Nextcloud (no layers) as removing everything', () => {
		const diff = diffLayers(amsterdam, [])
		expect(diff.added).toEqual([])
		expect(diff.unchanged).toEqual([])
		expect(diff.removed).toHaveLength(amsterdam.length)
	})

	it('treats leaving stock Nextcloud as adding everything', () => {
		const diff = diffLayers([], zwolle)
		expect(diff.removed).toEqual([])
		expect(diff.added).toHaveLength(zwolle.length)
	})

	it('ignores cache-busting differences', () => {
		const rebusted = amsterdam.map((layer) =>
			layer.kind === 'file'
				? { ...layer, href: layer.href.replace(/v=\d/, 'v=9') }
				: layer,
		)
		const diff = diffLayers(amsterdam, rebusted)
		expect(diff.removed).toEqual([])
		expect(diff.added).toEqual([])
	})

	it('accepts null or undefined lists', () => {
		expect(diffLayers(null, undefined)).toEqual({
			removed: [],
			added: [],
			unchanged: [],
		})
	})
})

describe('bumpVersion', () => {
	it('replaces an existing v= whatever its value', () => {
		expect(
			bumpVersion('/apps/theming/theme/default.css?plain=1&v=11cfaf9b', '42'),
		).toBe('/apps/theming/theme/default.css?plain=1&v=42')
		expect(bumpVersion('/x.css?v=1.1.12-unstable.20260831125624', 'now')).toBe(
			'/x.css?v=now',
		)
	})

	it('appends v= when there is none', () => {
		expect(bumpVersion('/x.css', '7')).toBe('/x.css?v=7')
		expect(bumpVersion('/x.css?plain=1', '7')).toBe('/x.css?plain=1&v=7')
	})

	it('does not touch a hash fragment after the version', () => {
		expect(bumpVersion('/x.css?v=1#frag', '2')).toBe('/x.css?v=2#frag')
	})
})
