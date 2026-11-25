/**
 * Registers js/lib/markupRender.js on window for every DOM spec, the way the
 * settings page loads it before admin.js and playground.js.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */

import markupRender from '../../../js/lib/markupRender.js'
import uploadResult from '../../../js/lib/uploadResult.js'

if (typeof window !== 'undefined') {
	window.NldesignMarkupRender = markupRender
	window.ThematiqUploadResult = uploadResult
}
