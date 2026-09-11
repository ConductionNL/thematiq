/**
 * @vitest-environment jsdom
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * What `swap()` does to the document when a new stylesheet does NOT arrive.
 *
 * The happy path is covered end to end by the Playwright workflow, which has a
 * real server and real `load` events. This file pins the failure path, which a
 * browser test cannot produce on demand: a 404, a dropped connection, or a sheet
 * that simply never answers. The rule is that the page must be left on a theme
 * that works — the previous one — rather than on half of the new one.
 *
 * jsdom does not fetch a `<link>`, so the events are dispatched by hand. That is
 * the point here: the test decides whether the sheet "arrived".
 */

import { beforeEach, describe, it, expect } from 'vitest'
import layerSwap from '../../js/lib/layerSwap.js'

const { swap } = layerSwap

/** A one-file manifest for a set, the shape the server's endpoint returns. */
function manifestFor(set) {
	return {
		layers: [
			{
				layer: 'tokens',
				kind: 'file',
				href: '/apps/thematiq/css/tokens/' + set + '.css?v=1',
			},
		],
	}
}

/** The set names of the token stylesheets currently in the head, in order. */
function tokenSheets() {
	return Array.from(
		document.head.querySelectorAll('link[rel="stylesheet"][href]'),
	).map((link) =>
		link.getAttribute('href').replace(/^.*\/tokens\/(.*)\.css.*$/, '$1'),
	)
}

/**
 * Answer for the sheet of `set` once swap has inserted it: `load` for a sheet
 * that arrives, `error` for one that does not.
 */
function answerFor(set, type) {
	setTimeout(() => {
		document.head
			.querySelectorAll('link[rel="stylesheet"][href]')
			.forEach((link) => {
				if (
					link.getAttribute('href').indexOf('/tokens/' + set + '.css')
					!== -1
				) {
					link.dispatchEvent(new window.Event(type))
				}
			})
	}, 0)
}

describe('swap — when the new stylesheet does not arrive', () => {
	beforeEach(() => {
		document.head.innerHTML =
			'<link rel="stylesheet" href="/apps/thematiq/css/tokens/amsterdam.css?v=1">'
	})

	it('replaces the run once the new sheet has loaded', async () => {
		answerFor('zwolle', 'load')

		const result = await swap(
			document,
			manifestFor('amsterdam'),
			manifestFor('zwolle'),
		)

		expect(result.ok).toBe(true)
		expect(tokenSheets()).toEqual(['zwolle'])
		expect(result.removed).toHaveLength(1)
	})

	it('keeps the working run and rolls back when the new sheet 404s', async () => {
		answerFor('zwolle', 'error')

		const result = await swap(
			document,
			manifestFor('amsterdam'),
			manifestFor('zwolle'),
		)

		// The page is still on a theme that works, not on half of a new one.
		expect(result.ok).toBe(false)
		expect(tokenSheets()).toEqual(['amsterdam'])
		expect(result.added).toEqual([])
		expect(result.removed).toEqual([])
	})

	it('rolls back the whole run when only one of several sheets fails', async () => {
		const next = {
			layers: [
				...manifestFor('zwolle').layers,
				{
					layer: 'dark-variant',
					kind: 'file',
					href: '/apps/thematiq/css/tokens/zwolle-dark.css?v=1',
				},
			],
		}

		answerFor('zwolle', 'load')
		answerFor('zwolle-dark', 'error')

		const result = await swap(document, manifestFor('amsterdam'), next)

		expect(result.ok).toBe(false)
		expect(tokenSheets()).toEqual(['amsterdam'])
	})
})
