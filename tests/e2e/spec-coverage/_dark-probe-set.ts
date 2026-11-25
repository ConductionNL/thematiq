/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The token set the dark-mode specs make active while they run.
 *
 * Deliberately NOT named `*.spec.ts` (see _fixtures.ts for why).
 *
 * It must wear a design system that reads `--nldesign-*` variables (so its
 * token file is linked), ship a generated `css/tokens/dark/<id>.css` (so the
 * dark layer is linked after it) and be unrestricted (so applying it needs no
 * licence confirmation). Zuiddrecht is the demo municipality that does not
 * exist, made for exactly this kind of run.
 */
export const DARK_PROBE_SET = 'zuiddrecht'
