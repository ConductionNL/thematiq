/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/workplace-app-sheets/spec.md
 * @e2e openspec/specs/zuiddrecht-token-set/spec.md
 *
 * The live look the two changes ask for (workplace-app-sheets-entry-states 3.1,
 * zuiddrecht-workplace-type-and-focus 2.3), as computed styles on a real
 * Nextcloud with the Zuiddrecht set, which carries the light workplace layout:
 *
 *   - Calendar: an entry that has ended is drawn in the muted text colour, an
 *     entry not answered yet has a dashed frame with a 3px edge.
 *   - Deck: the card under the pointer is lifted (a shadow), tilted only when
 *     no reduced motion is asked for.
 *   - The keyboard ring: Tab on the dashboard and in Files draws a 2px ring in
 *     the set's action blue.
 *
 * Every test puts back what it changed: the token set through withThemeState(),
 * the calendar entries and the board it made in a `finally`. An app the
 * instance does not have is reported as skipped with its name, not passed.
 */
import { test, expect, type Page } from '@playwright/test'

import { api } from './_fixtures'
import { withThemeState } from './_theme-state'

const RING = 'rgb(54, 105, 165)'

/** Whether an app is enabled for the current user, read from its navigation entry. */
async function appEnabled(page: Page, appId: string): Promise<boolean> {
	const res = await api(
		page,
		'GET',
		'/ocs/v2.php/core/navigation/apps?format=json',
	)
	const entries: Array<{ id: string }> = res.json?.ocs?.data ?? []
	return entries.some((entry) => entry.id === appId)
}

/** The current user's id. */
async function currentUser(page: Page): Promise<string> {
	await page.waitForFunction(
		() => typeof (window as any).OC?.getCurrentUser === 'function',
	)
	return page.evaluate(() => String((window as any).OC.getCurrentUser().uid))
}

/** Two digits. */
const pad = (n: number): string => String(n).padStart(2, '0')

/** A local iCalendar date-time for today at the given hour and minute. */
function todayAt(hour: number, minute: number): string {
	const d = new Date()
	return `${d.getFullYear()}${pad(d.getMonth() + 1)}${pad(d.getDate())}T${pad(hour)}${pad(minute)}00`
}

/** PUT or DELETE one object in the user's personal calendar over CalDAV. */
async function calDav(
	page: Page,
	method: 'PUT' | 'DELETE',
	user: string,
	uid: string,
	body?: string,
): Promise<number> {
	return page.evaluate(
		async ({ method, user, uid, body }) => {
			const res = await fetch(
				`/remote.php/dav/calendars/${user}/personal/${uid}.ics`,
				{
					method,
					headers: {
						requesttoken: (window as any).OC.requestToken,
						'Content-Type': 'text/calendar; charset=utf-8',
					},
					body,
				},
			)
			return res.status
		},
		{ method, user, uid, body },
	)
}

/** One VEVENT, optionally with the user invited and not answered yet. */
function event(
	uid: string,
	title: string,
	start: string,
	end: string,
	invited?: string,
): string {
	const lines = [
		'BEGIN:VCALENDAR',
		'VERSION:2.0',
		'PRODID:-//Conduction//thematiq e2e//EN',
		'BEGIN:VEVENT',
		`UID:${uid}`,
		`DTSTAMP:${todayAt(0, 0)}Z`,
		`DTSTART:${start}`,
		`DTEND:${end}`,
		`SUMMARY:${title}`,
	]
	if (invited !== undefined) {
		lines.push('ORGANIZER;CN=Organiser:mailto:organiser@example.org')
		lines.push(
			`ATTENDEE;CN=${invited};PARTSTAT=NEEDS-ACTION;RSVP=TRUE:mailto:${invited}@example.org`,
		)
	}
	lines.push('END:VEVENT', 'END:VCALENDAR', '')
	return lines.join('\r\n')
}

/** Tab until a control has keyboard focus, and return its outline. */
async function tabToRing(
	page: Page,
): Promise<{ color: string; width: string; style: string }> {
	for (let i = 0; i < 15; i++) {
		await page.keyboard.press('Tab')
		const ring = await page.evaluate(() => {
			const el = document.activeElement
			if (
				el === null
				|| el === document.body
				|| !el.matches(':focus-visible')
			) {
				return null
			}
			const style = getComputedStyle(el)
			return {
				color: style.outlineColor,
				width: style.outlineWidth,
				style: style.outlineStyle,
			}
		})
		if (ring !== null) {
			return ring
		}
	}
	throw new Error('no control took keyboard focus within 15 Tab presses')
}

test.describe('the workplace, looked at live under the Zuiddrecht set', () => {
	test.describe.configure({ mode: 'serial' })

	// Scenario: Tabbing to a button (live part)
	for (const [name, path] of [
		['the dashboard', '/index.php/apps/dashboard/'],
		['Files', '/index.php/apps/files/'],
	]) {
		test(`Tab draws the 2px action-blue ring on ${name}`, async ({
			page,
			browser,
		}) => {
			await withThemeState(browser, { tokenSet: 'zuiddrecht' }, async () => {
				await page.goto(path, { waitUntil: 'domcontentloaded' })
				await expect(
					page.locator('#content, #content-vue').first(),
				).toBeVisible()
				await page.waitForFunction(
					() => typeof (window as any).OC?.requestToken === 'string',
				)
				const ring = await tabToRing(page)
				expect(ring.style).toBe('solid')
				expect(ring.width).toBe('2px')
				expect(ring.color).toBe(RING)
			})
		})
	}

	// Scenario: Yesterday's meeting (live part)
	// Scenario: A tentative meeting (live part)
	test('Calendar fades an ended entry and dashes one not answered yet', async ({
		page,
		browser,
	}) => {
		await page.goto('/index.php/apps/dashboard/', {
			waitUntil: 'domcontentloaded',
		})
		test.skip(
			!(await appEnabled(page, 'calendar')),
			'Calendar is not enabled on this instance',
		)
		const user = await currentUser(page)
		const stamp = Date.now()
		const past = `thematiq-e2e-past-${stamp}`
		const open = `thematiq-e2e-open-${stamp}`
		try {
			expect(
				await calDav(
					page,
					'PUT',
					user,
					past,
					event(
						past,
						'Thematiq ended entry',
						todayAt(0, 0),
						todayAt(0, 30),
					),
				),
			).toBeLessThan(300)
			expect(
				await calDav(
					page,
					'PUT',
					user,
					open,
					event(
						open,
						'Thematiq open entry',
						todayAt(23, 0),
						todayAt(23, 30),
						user,
					),
				),
			).toBeLessThan(300)

			await withThemeState(browser, { tokenSet: 'zuiddrecht' }, async () => {
				await page.goto('/index.php/apps/calendar/timeGridDay/now', {
					waitUntil: 'domcontentloaded',
				})

				const ended = page
					.locator('.fc-timegrid-event.fc-event-past', {
						hasText: 'Thematiq ended entry',
					})
					.first()
				await expect(ended).toBeVisible()
				const muted = await page.evaluate(() => {
					const probe = document.createElement('span')
					probe.style.color = 'var(--color-text-maxcontrast)'
					document
						.querySelector('#content, #content-vue')
						?.appendChild(probe)
					const color = getComputedStyle(probe).color
					probe.remove()
					return color
				})
				const endedText = await ended
					.locator('.fc-event-main')
					.evaluate((el) => getComputedStyle(el).color)
				expect(endedText).toBe(muted)

				const waiting = page
					.locator('.fc-timegrid-event', {
						hasText: 'Thematiq open entry',
					})
					.first()
				await page
					.locator('.fc-timegrid-slot[data-time="22:00:00"]')
					.first()
					.scrollIntoViewIfNeeded()
				await expect(waiting).toBeVisible()
				await expect(waiting).toHaveClass(
					/fc-event-nc-(needs-action|tentative)/,
				)
				const frame = await waiting.evaluate((el) => {
					const style = getComputedStyle(el)
					return {
						style: style.borderTopStyle,
						top: style.borderTopWidth,
						start: style.borderLeftWidth,
					}
				})
				expect(frame.style).toBe('dashed')
				expect(frame.top).toBe('1px')
				expect(frame.start).toBe('3px')
			})
		} finally {
			await calDav(page, 'DELETE', user, past)
			await calDav(page, 'DELETE', user, open)
		}
	})

	// Scenario: A card between two columns (live part)
	// Scenario: Reduced motion (live part)
	test('Deck lifts the dragged card, and tilts it only without reduced motion', async ({
		page,
		browser,
	}) => {
		await page.goto('/index.php/apps/dashboard/', {
			waitUntil: 'domcontentloaded',
		})
		test.skip(
			!(await appEnabled(page, 'deck')),
			'Deck is not enabled on this instance',
		)

		const board = await api(page, 'POST', '/index.php/apps/deck/boards', {
			title: `Thematiq e2e ${Date.now()}`,
			color: '3669a5',
		})
		expect(board.status).toBeLessThan(300)
		const boardId = board.json.id
		try {
			const todo = await api(page, 'POST', '/index.php/apps/deck/stacks', {
				title: 'To do',
				boardId,
				order: 0,
			})
			await api(page, 'POST', '/index.php/apps/deck/stacks', {
				title: 'Done',
				boardId,
				order: 1,
			})
			expect(todo.status).toBeLessThan(300)
			const card = await api(page, 'POST', '/index.php/apps/deck/cards', {
				title: 'Thematiq moving card',
				stackId: todo.json.id,
				type: 'plain',
				order: 0,
			})
			expect(card.status).toBeLessThan(300)

			await withThemeState(browser, { tokenSet: 'zuiddrecht' }, async () => {
				for (const motion of ['no-preference', 'reduce'] as const) {
					await page.emulateMedia({ reducedMotion: motion })
					await page.goto(`/index.php/apps/deck/board/${boardId}`, {
						waitUntil: 'domcontentloaded',
					})
					const source = page
						.locator('.card', { hasText: 'Thematiq moving card' })
						.first()
					const target = page
						.locator('.stack', { hasText: 'Done' })
						.first()
					await expect(source).toBeVisible()
					const from = (await source.boundingBox())!
					const to = (await target.boundingBox())!

					await page.mouse.move(
						from.x + from.width / 2,
						from.y + from.height / 2,
					)
					await page.mouse.down()
					for (let step = 1; step <= 10; step++) {
						await page.mouse.move(
							from.x
								+ from.width / 2
								+ ((to.x + to.width / 2 - from.x - from.width / 2)
									* step)
									/ 10,
							from.y + from.height / 2 + 4 * step,
						)
					}
					const ghost = page.locator('.smooth-dnd-ghost .card').first()
					await expect(ghost).toBeVisible()
					const lift = await ghost.evaluate((el) => {
						const style = getComputedStyle(el)
						return {
							shadow: style.boxShadow,
							transform: style.transform,
						}
					})
					await page.mouse.up()

					expect(lift.shadow).not.toBe('none')
					if (motion === 'reduce') {
						expect(lift.transform).toBe('none')
					} else {
						expect(lift.transform).not.toBe('none')
					}
				}
			})
		} finally {
			await page.emulateMedia({ reducedMotion: null })
			await api(page, 'DELETE', `/index.php/apps/deck/boards/${boardId}`)
		}
	})
})
