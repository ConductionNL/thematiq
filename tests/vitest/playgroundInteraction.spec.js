/**
 * @vitest-environment jsdom
 *
 * The playground's interaction layer, driven directly: what a click or a key
 * on a drawn specimen does to it.
 *
 * tests/vitest/playgroundSelection.spec.js covers the pure helpers and the
 * markup strings, and tests/vitest/playgroundInstrument.spec.js boots the
 * whole instrument onto the editor. Neither reaches pick(), interact() or the
 * keyboard on a stage: these do, on stages drawn by renderStage() itself, so
 * a specimen whose selection, name or keys stop working fails here.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/component-playground/specs/component-playground/spec.md
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import * as fs from 'fs'
import * as path from 'path'
import playground from '../../js/playground.js'

const ROOT = path.resolve(__dirname, '../..')

const inventory = JSON.parse(
	fs.readFileSync(path.join(ROOT, 'js/playground/components.json'), 'utf8'),
)

/**
 * Draw one component's stage the way the instrument does, wired.
 *
 * @param {string} id The component id.
 * @return {Element} The stage.
 */
function stageFor(id) {
	const preview = document.createElement('div')
	preview.id = 'nldesign-preview'
	const stage = document.createElement('div')
	stage.className = 'nldesign-preview-stage nldesign-pg-stage'
	preview.appendChild(stage)
	document.body.appendChild(preview)
	playground.bindStage(stage)
	playground.renderStage(
		{
			inventory,
			stage,
			preview,
			versions: {},
			serverVersion: 35,
		},
		playground.componentById(inventory, id),
	)
	return stage
}

/** Fire a key on an element, the way the browser does. */
function press(element, key) {
	const event = new window.KeyboardEvent('keydown', {
		key,
		bubbles: true,
		cancelable: true,
	})
	element.dispatchEvent(event)
	return event
}

/** Click an element. */
function click(element) {
	element.dispatchEvent(
		new window.MouseEvent('click', { bubbles: true, cancelable: true }),
	)
}

/** What the stage's live line says. */
function said(stage) {
	return stage.querySelector('.nldesign-pg-say').textContent
}

describe('the playground interaction layer', () => {
	afterEach(() => {
		document.body.innerHTML = ''
		vi.useRealTimers()
	})

	describe('every pickable set, reached by keyboard and named state', () => {
		// WCAG 2.1.1 and 4.1.2: every row a pointer can pick is in the tab order
		// (or, in a composite widget, reachable from its one tab stop) and says
		// which one is picked in an attribute, not only in a class.
		const drawn = inventory.components
			.map((component) => component.id)
			.filter((id) => typeof playground.STAGES[id] === 'function')

		it('leaves no pickable row out of reach and no pick unsaid', () => {
			const unreachable = []
			drawn.forEach((id) => {
				const stage = stageFor(id)
				playground.PICKABLE.concat([playground.OPTIONS]).forEach((set) => {
					stage.querySelectorAll(set.group).forEach((group) => {
						const rows = [...group.querySelectorAll(set.row)]
						if (rows.length === 0) {
							return
						}
						const stops = rows.filter(
							(row) => row.getAttribute('tabindex') === '0',
						)
						const reachable =
							set.arrows === undefined
								? stops.length === rows.length
								: stops.length === 1
						if (reachable === false) {
							unreachable.push(`${id}: ${set.row} tab stops`)
						}
						const marker = set.on.split(' ')[0]
						rows.forEach((row) => {
							const picked = row.classList.contains(marker)
							const value = row.getAttribute(set.state)
							if (picked === true && value !== set.value) {
								unreachable.push(`${id}: ${set.row} pick unsaid`)
							}
							if (picked === false && value === set.value) {
								unreachable.push(`${id}: ${set.row} pick claimed`)
							}
						})
					})
				})
				document.body.innerHTML = ''
			})

			expect(unreachable).toEqual([])
		})

		it('names the select listbox, which a listbox has to be', () => {
			const stage = stageFor('select')
			const listbox = stage.querySelector('[role="listbox"]')

			expect(listbox).not.toBeNull()
			expect(listbox.getAttribute('aria-label')).toBe('Country')
			expect(
				[...listbox.children].every(
					(option) => option.getAttribute('role') === 'option',
				),
			).toBe(true)
		})
	})

	describe('pick()', () => {
		it('moves the class, the state attribute and the tab stop together', () => {
			const stage = stageFor('sidebar')
			const tabs = [...stage.querySelectorAll('.nldesign-pg-tab')]

			playground.pick(
				stage.querySelector('.nldesign-pg-tabs'),
				tabs[2],
				'.nldesign-pg-tab',
				'is-active',
			)

			expect(tabs.map((tab) => tab.classList.contains('is-active'))).toEqual([
				false,
				false,
				true,
			])
			expect(tabs.map((tab) => tab.getAttribute('aria-selected'))).toEqual([
				'false',
				'false',
				'true',
			])
			expect(tabs.map((tab) => tab.getAttribute('tabindex'))).toEqual([
				'-1',
				'-1',
				'0',
			])
		})

		it('says aria-current on the picked entry only', () => {
			const stage = stageFor('app-navigation')
			const entries = [...stage.querySelectorAll('.nldesign-pg-nav-entry')]

			click(entries[0])

			expect(
				entries.map((entry) => entry.getAttribute('aria-current')),
			).toEqual(['page', null, null, null])
			expect(entries[0].classList.contains('is-selected')).toBe(true)
			expect(entries[1].classList.contains('is-selected')).toBe(false)
			expect(said(stage)).toBe('All files selected')
		})
	})

	describe('the keyboard', () => {
		it('picks a row with Enter and with Space, as a click does', () => {
			const stage = stageFor('list-item')
			const rows = [...stage.querySelectorAll('.nldesign-pg-listitem')]

			const enter = press(rows[0], 'Enter')
			expect(enter.defaultPrevented).toBe(true)
			expect(rows[0].getAttribute('aria-current')).toBe('true')
			expect(rows[2].hasAttribute('aria-current')).toBe(false)
			expect(said(stage)).toBe('Marianne de Vries selected')

			const space = press(rows[1], ' ')
			// Space would otherwise scroll the panel out from under the row.
			expect(space.defaultPrevented).toBe(true)
			expect(rows[1].getAttribute('aria-current')).toBe('true')
		})

		it('picks a crumb and a table row by key', () => {
			const crumbs = stageFor('breadcrumbs')
			const crumb = crumbs.querySelector('.nldesign-pg-crumb')
			press(crumb, 'Enter')
			expect(crumb.getAttribute('aria-current')).toBe('page')
			expect(crumb.getAttribute('role')).toBe('link')

			document.body.innerHTML = ''
			const table = stageFor('table')
			const row = table.querySelectorAll('.nldesign-pg-table tbody tr')[1]
			press(row, ' ')
			expect(row.getAttribute('aria-selected')).toBe('true')
			expect(row.classList.contains('is-selected')).toBe(true)
		})

		it('moves tabs with the arrows, Home and End, the pick following', () => {
			const stage = stageFor('sidebar')
			const tabs = [...stage.querySelectorAll('.nldesign-pg-tab')]
			tabs[0].focus()

			press(tabs[0], 'ArrowRight')
			expect(document.activeElement).toBe(tabs[1])
			expect(tabs[1].getAttribute('aria-selected')).toBe('true')
			expect(tabs[0].getAttribute('aria-selected')).toBe('false')

			press(tabs[1], 'End')
			expect(document.activeElement).toBe(tabs[2])

			press(tabs[2], 'ArrowRight')
			expect(document.activeElement).toBe(tabs[0])
			expect(tabs[0].getAttribute('tabindex')).toBe('0')

			press(tabs[0], 'ArrowLeft')
			expect(document.activeElement).toBe(tabs[2])

			press(tabs[2], 'Home')
			expect(document.activeElement).toBe(tabs[0])
		})

		it('moves through the options without choosing, and chooses on Enter', () => {
			const stage = stageFor('select')
			const options = [...stage.querySelectorAll('.nldesign-pg-option')]
			options[0].focus()

			press(options[0], 'ArrowDown')
			expect(document.activeElement).toBe(options[1])
			expect(options[1].getAttribute('aria-selected')).toBe('false')

			press(options[1], 'Enter')
			expect(options[1].getAttribute('aria-selected')).toBe('true')
			expect(stage.querySelector('.nldesign-pg-select').textContent).toContain(
				'Duitsland',
			)
			expect(said(stage)).toBe('Duitsland selected')
		})

		it('leaves a key pressed inside a field to the field', () => {
			const stage = stageFor('sidebar')
			const input = stage.querySelector('.input-field__input')

			const event = press(input, ' ')

			expect(event.defaultPrevented).toBe(false)
		})

		it('keeps Space on a specimen button from scrolling the panel', () => {
			const stage = stageFor('primary-button')
			const button = stage.querySelector('.nldesign-pg-btn')

			expect(press(button, ' ').defaultPrevented).toBe(true)
		})
	})

	describe('the choices', () => {
		it('names each control by its own label', () => {
			const stage = stageFor('checkbox-switch')
			const names = [
				...stage.querySelectorAll('.checkbox-radio-switch__input'),
			].map((input) => [...input.labels].map((l) => l.textContent).join(''))

			expect(names).toEqual([
				'Not checked',
				'Checked',
				'Not selected',
				'Selected',
				'Off',
				'On',
			])
			expect(
				stage
					.querySelectorAll('.checkbox-radio-switch__input')[4]
					.getAttribute('role'),
			).toBe('switch')
		})

		it('flips a checkbox once when its label is clicked', () => {
			const stage = stageFor('checkbox-switch')
			const choice = stage.querySelector('.nldesign-pg-choice')
			const input = choice.querySelector('input')

			click(choice.querySelector('.checkbox-content__text'))

			expect(input.checked).toBe(true)
			expect(choice.classList.contains('checkbox-radio-switch--checked')).toBe(
				true,
			)
			expect(said(stage)).toBe('Switched on')
		})

		it('turns the other radio off when one is picked', () => {
			const stage = stageFor('checkbox-switch')
			const radios = [
				...stage.querySelectorAll('.checkbox-radio-switch-radio'),
			]

			click(radios[0].querySelector('input'))

			expect(
				radios.map((radio) => radio.querySelector('input').checked),
			).toEqual([true, false])
			expect(
				radios[1].classList.contains('checkbox-radio-switch--checked'),
			).toBe(false)
		})
	})

	describe('the actions menu', () => {
		it('says whether it is open on its trigger, both ways', () => {
			const stage = stageFor('actions-menu')
			const toggle = stage.querySelector('.action-item__menutoggle')

			click(toggle)
			expect(toggle.getAttribute('aria-expanded')).toBe('false')
			expect(said(stage)).toBe('Menu closed')

			click(toggle)
			expect(toggle.getAttribute('aria-expanded')).toBe('true')
			expect(said(stage)).toBe('Menu opened')
		})
	})

	describe('say() and pressButton()', () => {
		it('says a thing, then clears it', () => {
			vi.useFakeTimers()
			const stage = stageFor('primary-button')

			playground.say(stage, 'Saved')
			expect(said(stage)).toBe('Saved')

			vi.advanceTimersByTime(2000)
			expect(said(stage)).toBe('')
		})

		it('keeps a disabled button silent', () => {
			const stage = stageFor('primary-button')
			const button = document.createElement('button')
			button.setAttribute('data-done', 'Saved')
			button.disabled = true
			stage.appendChild(button)

			playground.pressButton(stage, button)

			expect(said(stage)).toBe('')
		})
	})
})
