/**
 * The first-time setup's model: the steps, the choices the administrator makes, and the one request
 * Apply sends. No Vue here, so the rules are tested on their own (tests/vitest/setupWizard.spec.js).
 *
 * Nothing is written before Apply (design.md, "Nothing changes until Apply"): the steps only change
 * `choices`; `applyRequest(choices)` is the body of `POST /apps/thematiq/api/setup/action/apply`.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md
 */

/** The steps in order (rail: Welcome, House style, Nextcloud theme, Login page, Apps menu, Done). */
export const STEP_IDS = ['welcome', 'house-style', 'nextcloud-theme', 'login-page', 'apps-menu', 'done']

/** The set that means "no house style, only the apps menu". */
export const NEXTCLOUD_DEFAULT = 'nextcloud'

/** The walkthrough the done step hands over to (change apps-menu-walkthrough, REQ-TQWT-002). */
export const WALKTHROUGH = {
	key: 'apps_menu_walkthrough_seen_version-progress',
	tourId: 'apps-menu',
	firstStep: 'open',
}

/** The workplace menu defaults step 5 shows while the menu is on, with their ranges. */
export const MENU_RANGES = { columns: [3, 5], rowsAboveFold: [1, 3] }

/**
 * The choices the wizard opens with: the stored ones (`status.current`, so Set up again shows what is
 * set), else the shipped defaults. The theme switch and the apps menu are on by default.
 *
 * @param {object|null} status The setup status document.
 * @return {object} The choices.
 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-setup-contract-is-for-administrators-only-req-tqfs-005
 */
export function initialChoices(status) {
	const current = (status && status.current) || {}
	const set = (status && (status.completed || status.configured)) ? current : {}
	const menu = current.menu || {}
	return {
		tokenSet: typeof current.tokenSet === 'string' && current.tokenSet !== '' ? current.tokenSet : NEXTCLOUD_DEFAULT,
		confirmRestricted: false,
		nextcloudThemeFollows: set.nextcloudThemeFollows === undefined ? true : set.nextcloudThemeFollows === true,
		loginStyle: current.loginStyle === 'nextcloud' ? 'nextcloud' : 'house-style',
		hideSlogan: current.hideSlogan === true,
		menu: {
			enabled: menu.enabled !== false,
			columns: clamp(menu.columns, MENU_RANGES.columns, 4),
			rowsAboveFold: clamp(menu.rowsAboveFold, MENU_RANGES.rowsAboveFold, 2),
			payoff: menu.payoff !== false,
			// One "Show Create" switch on the board; it sets both create switches (quick-actions-toggles).
			showCreate: menu.showCreateButton !== false || menu.showCreateRow !== false,
			showExternalApps: menu.showExternalApps !== false,
		},
	}
}

/**
 * A whole number held inside a range, or the fallback.
 *
 * @param {*} value The value.
 * @param {number[]} range The lowest and highest allowed.
 * @param {number} fallback Used when the value is not a number.
 * @return {number}
 */
export function clamp(value, range, fallback) {
	const number = Number(value)
	if (!Number.isFinite(number)) {
		return fallback
	}
	return Math.min(range[1], Math.max(range[0], Math.round(number)))
}

/**
 * The steps shown for these choices: the Nextcloud theme step is left out for Nextcloud default,
 * which has no theme to apply (REQ-TQFS-008).
 *
 * @param {object} choices The choices.
 * @return {string[]} The step ids.
 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-nextcloud-theme-step-keeps-nextclouds-theme-in-line-req-tqfs-008
 */
export function visibleSteps(choices) {
	return STEP_IDS.filter((id) => id !== 'nextcloud-theme' || choices.tokenSet !== NEXTCLOUD_DEFAULT)
}

/**
 * Whether the chosen set needs the licence statement before Next: it is restricted and nobody
 * confirmed it yet (REQ-TQFS-007).
 *
 * @param {string} tokenSet The chosen set.
 * @param {Array<object>} licences The `sets` of `GET /settings/tokensets/licences`.
 * @return {object|null} The set's licence entry when a confirmation is needed, else null.
 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-house-style-step-chooses-the-token-set-req-tqfs-007
 */
export function confirmationNeeded(tokenSet, licences) {
	const entry = (licences || []).find((item) => item && item.id === tokenSet)
	if (!entry || !entry.licence || entry.licence.restricted !== true || entry.confirmation) {
		return null
	}
	return entry
}

/**
 * Whether Next may leave the house style step: a restricted set needs the statement ticked.
 *
 * @param {object} choices The choices.
 * @param {Array<object>} licences The licence entries.
 * @return {boolean}
 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-house-style-step-chooses-the-token-set-req-tqfs-007
 */
export function houseStyleReady(choices, licences) {
	return confirmationNeeded(choices.tokenSet, licences) === null || choices.confirmRestricted === true
}

/**
 * The body of Apply: every choice, once. With the apps menu off only `{enabled: false}` goes, so
 * none of the workplace defaults is stored (REQ-TQFS-010); `confirmRestricted` only when a
 * confirmation is needed and given.
 *
 * @param {object} choices The choices.
 * @param {Array<object>} licences The licence entries.
 * @return {object} The request body.
 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-apps-menu-step-is-one-switch-req-tqfs-010
 */
export function applyRequest(choices, licences) {
	const body = {
		tokenSet: choices.tokenSet,
		nextcloudThemeFollows: choices.tokenSet !== NEXTCLOUD_DEFAULT && choices.nextcloudThemeFollows === true,
		loginStyle: choices.loginStyle,
		hideSlogan: choices.hideSlogan === true,
		menu: choices.menu.enabled
			? {
				enabled: true,
				columns: clamp(choices.menu.columns, MENU_RANGES.columns, 4),
				rowsAboveFold: clamp(choices.menu.rowsAboveFold, MENU_RANGES.rowsAboveFold, 2),
				payoff: choices.menu.payoff === true,
				showCreateButton: choices.menu.showCreate === true,
				showCreateRow: choices.menu.showCreate === true,
				showExternalApps: choices.menu.showExternalApps === true,
			}
			: { enabled: false },
	}
	if (confirmationNeeded(choices.tokenSet, licences) !== null && choices.confirmRestricted === true) {
		body.confirmRestricted = true
	}
	return body
}

/**
 * The parts Apply reports, as the done step lists them, in order.
 *
 * @param {object} result The answer of Apply (`{applied, failed}`).
 * @return {Array<{id: string, applied: boolean}>}
 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-done-step-sums-up-and-offers-the-apps-menu-walkthrough-req-tqfs-011
 */
export function partsOf(result) {
	const applied = (result && result.applied) || []
	const failed = (result && result.failed) || []
	return ['house-style', 'nextcloud-theme', 'login-page', 'apps-menu']
		.filter((id) => applied.includes(id) || failed.includes(id))
		.map((id) => ({ id, applied: applied.includes(id) }))
}

/**
 * The walkthrough progress that starts the tour at once after the reload (REQ-TQFS-011).
 *
 * @return {string} The stored value.
 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-done-step-sums-up-and-offers-the-apps-menu-walkthrough-req-tqfs-011
 */
export function immediateWalkthrough() {
	return JSON.stringify({ tourId: WALKTHROUGH.tourId, stepId: WALKTHROUGH.firstStep, index: 0, immediate: true })
}
