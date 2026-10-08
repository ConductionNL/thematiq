/**
 * The one part of @nextcloud/vue the code editor's CnJsonViewer imports.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * CnJsonViewer imports NcButton from the package index, which pulls in every
 * component and its stylesheet. webpack.code-editor.config.js points that import
 * here, so the bundle carries NcButton alone.
 */

export { default as NcButton } from '@nextcloud/vue/components/NcButton'
