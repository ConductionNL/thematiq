/**
 * The workspace's mobile logo and home screen icon: one row per image with its
 * preview, an upload button and a remove button.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/mobile-logo-and-touch-icon/specs/mobile-logo-and-touch-icon/spec.md#requirement-an-administrator-sets-a-mobile-logo-and-a-touch-icon
 */
;(function thematiqWorkspaceIconsInit() {
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', thematiqWorkspaceIconsMain)
	} else {
		thematiqWorkspaceIconsMain()
	}

	/**
	 * Load the block and wire it.
	 *
	 * @return {Promise|undefined} The request.
	 */
	function thematiqWorkspaceIconsMain() {
		var list = document.getElementById('nldesign-workspace-icons-list')
		var feedback = document.getElementById('nldesign-workspace-icons-feedback')
		if (!list || !feedback || typeof OC === 'undefined') {
			return undefined
		}
		var base = OC.generateUrl('/apps/thematiq/settings/workspace-icons')
		var kinds = [
			{
				kind: 'mobile-logo',
				label: t('thematiq', 'Mobile logo'),
				hint: t('thematiq', 'PNG, JPEG, WebP or SVG, up to 1 MB.'),
				accept: 'image/png,image/jpeg,image/webp,image/svg+xml',
			},
			{
				kind: 'touch-icon',
				label: t('thematiq', 'Home screen icon'),
				hint: t('thematiq', 'A square PNG of at least 120 by 120 pixels; 180 by 180 is best.'),
				accept: 'image/png',
			},
		]

		function request(url, options) {
			options = options || {}
			options.headers = Object.assign(
				{ requesttoken: OC.requestToken },
				options.headers || {},
			)
			return fetch(url, options).then(function (res) {
				return res.json().then(function (body) {
					return { ok: res.ok, body: body }
				})
			})
		}

		function row(item, url) {
			var id = 'nldesign-workspace-icons-' + item.kind
			var wrap = document.createElement('div')
			wrap.className = 'nldesign-workspace-icons__row'
			wrap.dataset.kind = item.kind
			var label = document.createElement('label')
			label.id = id + '-label'
			label.setAttribute('for', id)
			label.textContent = item.label
			var hint = document.createElement('span')
			hint.className = 'settings-hint'
			hint.id = id + '-hint'
			hint.textContent = item.hint
			var preview = document.createElement('span')
			preview.className = 'nldesign-workspace-icons__preview'
			if (url) {
				var img = document.createElement('img')
				img.src = url
				img.alt = ''
				preview.appendChild(img)
			} else {
				preview.textContent = t('thematiq', 'Not set: Nextcloud\'s own is used.')
			}
			var input = document.createElement('input')
			input.type = 'file'
			input.id = id
			input.hidden = true
			input.accept = item.accept
			var pick = document.createElement('button')
			pick.type = 'button'
			pick.className = 'button'
			pick.id = id + '-btn'
			pick.setAttribute('aria-labelledby', label.id + ' ' + pick.id)
			pick.setAttribute('aria-describedby', hint.id)
			pick.textContent = t('thematiq', 'Choose image')
			pick.addEventListener('click', function () {
				input.click()
			})
			input.addEventListener('change', function () {
				if (!input.files || input.files.length === 0) {
					return
				}
				var form = new FormData()
				form.append('file', input.files[0])
				request(base + '/' + item.kind, { method: 'POST', body: form }).then(function (result) {
					feedback.textContent = result.ok ? t('thematiq', 'Image saved.') : result.body.error
					load()
				})
			})
			wrap.appendChild(label)
			wrap.appendChild(preview)
			wrap.appendChild(input)
			wrap.appendChild(pick)
			if (url) {
				var remove = document.createElement('button')
				remove.type = 'button'
				remove.className = 'button'
				remove.id = id + '-remove'
				remove.setAttribute('aria-labelledby', remove.id + ' ' + label.id)
				remove.textContent = t('thematiq', 'Remove')
				remove.addEventListener('click', function () {
					request(base + '/' + item.kind, { method: 'DELETE' }).then(function (result) {
						feedback.textContent = result.ok ? t('thematiq', 'Image removed.') : result.body.error
						load()
					})
				})
				wrap.appendChild(remove)
			}
			wrap.appendChild(hint)
			return wrap
		}

		function load() {
			return request(base).then(function (result) {
				var status = result.ok ? result.body : {}
				list.textContent = ''
				kinds.forEach(function (item) {
					list.appendChild(row(item, status[item.kind] || null))
				})
			})
		}

		return load()
	}
})()
