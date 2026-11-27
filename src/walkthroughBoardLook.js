/**
 * The board-look sheet for the walkthrough bundle: empty.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @conduction/nextcloud-vue puts every component's board-look rules (some
 * 300 KB of text) on the page the first time a component draws the board
 * look. The walkthrough draws its board coachmark from its own scoped rules
 * and needs none of them, so webpack.menu-walkthrough.config.js points the
 * library's boardLookSheet.js here and the tour costs a page none of it.
 *
 * @spec openspec/changes/apps-menu-walkthrough/specs/apps-menu-walkthrough/spec.md#requirement-the-tour-starts-on-the-first-open-of-the-apps-menu-req-tqwt-002
 */

export const BOARD_LOOK_CSS = ''
export const BOARD_LOOK_BUILT = ''
