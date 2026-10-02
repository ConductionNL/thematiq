/**
 * Brand per app: map an app to a token set and, optionally, a large and a
 * small logo. One row per branded app with its set, contrast result, logo
 * uploads and a remove button; a form below adds or changes a brand.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/per-app-theming/spec.md
 */
;(function thematiqAppBrandsInit() {
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', thematiqAppBrandsMain)
	} else {
		thematiqAppBrandsMain()
	}

	/**
	 * Load the block and wire it.
	 *
	 * @return {Promise|undefined} The request.
	 */
	function thematiqAppBrandsMain() {
		var list = document.getElementById('nldesign-app-brands-list')
		var appSelect = document.getElementById('nldesign-app-brands-app')
		var setSelect = document.getElementById('nldesign-app-brands-set')
		var addBtn = document.getElementById('nldesign-app-brands-add')
		var feedback = document.getElementById('nldesign-app-brands-feedback')
		if (
			!list
			|| !appSelect
			|| !setSelect
			|| !addBtn
			|| typeof OC === 'undefined'
		) {
			return undefined
		}
		var base = OC.generateUrl('/apps/thematiq/settings/app-brands')
		var state = { brands: {}, apps: [], tokenSets: [] }

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

		function appName(appId) {
			var app = state.apps.find(function (a) {
				return a.id === appId
			})
			return app ? app.name : appId
		}

		function setInfo(setId) {
			return (
				state.tokenSets.find(function (s) {
					return s.id === setId
				}) || { id: setId, name: setId, wcagLevel: null }
			)
		}

		function fillSelect(select, items, label) {
			select.textContent = ''
			items.forEach(function (item) {
				var opt = document.createElement('option')
				opt.value = item.id
				opt.textContent = label(item)
				select.appendChild(opt)
			})
		}

		function logoInput(appId, size) {
			var id = 'nldesign-app-brands-' + appId + '-' + size
			var wrap = document.createElement('span')
			var label = document.createElement('label')
			label.setAttribute('for', id)
			label.textContent =
				size === 'large'
					? t('thematiq', 'Large logo')
					: t('thematiq', 'Small logo')
			var input = document.createElement('input')
			input.type = 'file'
			input.id = id
			input.accept = 'image/png,image/jpeg,image/webp,image/svg+xml'
			input.addEventListener('change', function () {
				if (!input.files || input.files.length === 0) {
					return
				}
				var form = new FormData()
				form.append('file', input.files[0])
				request(base + '/' + encodeURIComponent(appId) + '/logo/' + size, {
					method: 'POST',
					body: form,
				}).then(function (result) {
					feedback.textContent = result.ok
						? t('thematiq', 'Logo saved.')
						: result.body.error
					load()
				})
			})
			wrap.appendChild(label)
			wrap.appendChild(input)
			return wrap
		}

		function render() {
			list.textContent = ''
			var ids = Object.keys(state.brands)
			if (ids.length === 0) {
				var empty = document.createElement('p')
				empty.className = 'settings-hint'
				empty.textContent = t('thematiq', 'No app has its own brand.')
				list.appendChild(empty)
			}
			ids.forEach(function (appId) {
				var brand = state.brands[appId]
				var set = setInfo(brand.tokenSet)
				var row = document.createElement('div')
				row.className = 'nldesign-app-brand-row'
				row.setAttribute('data-app', appId)
				var title = document.createElement('strong')
				title.textContent = appName(appId) + ': ' + set.name
				row.appendChild(title)
				var contrast = document.createElement('span')
				contrast.className = 'settings-hint'
				contrast.textContent =
					' ('
					+ (set.wcagLevel
						? t('thematiq', 'Contrast: WCAG {level}', {
								level: set.wcagLevel,
							})
						: t('thematiq', 'Contrast: below WCAG AA'))
					+ ')'
				row.appendChild(contrast)
				if (brand.stale) {
					var stale = document.createElement('span')
					stale.className = 'nldesign-app-brand-stale'
					stale.textContent =
						' '
						+ t(
							'thematiq',
							'Not applied: the app is not installed, excluded, or its token set is gone.',
						)
					row.appendChild(stale)
				}
				row.appendChild(logoInput(appId, 'large'))
				row.appendChild(logoInput(appId, 'small'))
				var remove = document.createElement('button')
				remove.type = 'button'
				remove.className = 'button'
				remove.textContent = t('thematiq', 'Remove brand')
				remove.setAttribute(
					'aria-label',
					t('thematiq', 'Remove the brand of {app}', {
						app: appName(appId),
					}),
				)
				remove.addEventListener('click', function () {
					request(base + '/' + encodeURIComponent(appId), {
						method: 'DELETE',
					}).then(load)
				})
				row.appendChild(remove)
				list.appendChild(row)
			})
		}

		function load() {
			return request(base)
				.then(function (result) {
					state = result.body
					fillSelect(appSelect, state.apps, function (a) {
						return a.name
					})
					fillSelect(setSelect, state.tokenSets, function (s) {
						return s.name
					})
					render()
				})
				.catch(function (err) {
					console.error('Error loading the brands per app:', err)
				})
		}

		addBtn.addEventListener('click', function () {
			feedback.textContent = ''
			request(base + '/' + encodeURIComponent(appSelect.value), {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ tokenSet: setSelect.value }),
			}).then(function (result) {
				if (!result.ok) {
					feedback.textContent = result.body.error
					return
				}
				feedback.textContent = t('thematiq', 'Brand saved.')
				load()
			})
		})

		return load()
	}
})()
