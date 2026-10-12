/**
 * House styles of other organisations: every shipped token set whose licence
 * limits its use to its owner, with the warning, the licence text and the
 * confirmation an administrator gives before the set can be applied.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md
 */
;(function thematiqSetLicenceInit() {
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', thematiqSetLicenceMain)
	} else {
		thematiqSetLicenceMain()
	}

	/**
	 * Load the block and wire it.
	 *
	 * @return {Promise|undefined} The request.
	 */
	function thematiqSetLicenceMain() {
		var block = document.getElementById('nldesign-set-licences')
		var list = document.getElementById('nldesign-set-licences-list')
		var awaiting = document.getElementById('nldesign-set-licences-awaiting')
		var hint = document.getElementById('nldesign-set-licence-hint')
		var select = document.getElementById('nldesign-token-set-select')
		if (!block || !list || typeof OC === 'undefined') {
			return undefined
		}
		var base = OC.generateUrl('/apps/thematiq/settings/tokensets')
		var state = { sets: [], awaiting: [] }

		function request(url, options) {
			options = options || {}
			options.headers = Object.assign(
				{ requesttoken: OC.requestToken },
				options.headers || {},
			)
			return fetch(url, options).then(function (res) {
				return res.json().then(function (body) {
					return { ok: res.ok, status: res.status, body: body }
				})
			})
		}

		function el(tag, className, text) {
			var node = document.createElement(tag)
			if (className) {
				node.className = className
			}
			if (text !== undefined) {
				node.textContent = text
			}
			return node
		}

		function fill(text, params) {
			return Object.keys(params).reduce(function (acc, key) {
				return acc.replace('{' + key + '}', params[key])
			}, text)
		}

		function placeName(place) {
			if (place.kind === 'instance') {
				return t('thematiq', 'the whole instance')
			}
			if (place.kind === 'group') {
				return fill(t('thematiq', 'group {id}'), { id: place.id })
			}
			if (place.kind === 'app') {
				return fill(t('thematiq', 'app {id}'), { id: place.id })
			}
			return t('thematiq', 'a planned switch')
		}

		function statusText(set) {
			if (!set.confirmation) {
				return t('thematiq', 'Not confirmed')
			}
			return fill(t('thematiq', 'Confirmed by {uid} on {date}'), {
				uid: set.confirmation.uid,
				date: new Date(set.confirmation.at).toLocaleString(),
			})
		}

		function loadText(set, target) {
			if (target.dataset.loaded === '1') {
				return undefined
			}
			target.dataset.loaded = '1'
			return request(
				base + '/' + encodeURIComponent(set.id) + '/licence',
			).then(function (res) {
				target.textContent =
					res.ok && res.body.text
						? res.body.text
						: t(
								'thematiq',
								'This house style ships without a licence text.',
							)
			})
		}

		function confirmControls(set, feedback) {
			var wrap = el('div', 'nldesign-set-licence-confirm')
			var id = 'nldesign-set-licence-check-' + set.id
			var check = el('input')
			check.type = 'checkbox'
			check.id = id
			var label = el(
				'label',
				'',
				t(
					'thematiq',
					'We are this organisation, or we have its permission to use this house style.',
				),
			)
			label.htmlFor = id
			var button = el('button', 'button primary', t('thematiq', 'Confirm'))
			button.type = 'button'
			button.disabled = true
			check.addEventListener('change', function () {
				button.disabled = !check.checked
			})
			button.addEventListener('click', function () {
				request(
					base
						+ '/'
						+ encodeURIComponent(set.id)
						+ '/licence/confirmation',
					{
						method: 'POST',
						headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify({ confirmed: check.checked }),
					},
				).then(function (res) {
					if (!res.ok) {
						feedback.textContent = t(
							'thematiq',
							'The confirmation was not saved. Try again.',
						)
						return undefined
					}
					return load(set.id)
				})
			})
			var row = el('p', 'nldesign-set-licence-statement')
			row.appendChild(check)
			row.appendChild(label)
			wrap.appendChild(row)
			wrap.appendChild(
				el(
					'p',
					'settings-hint',
					t(
						'thematiq',
						'Your name and the time are recorded in the audit log.',
					),
				),
			)
			wrap.appendChild(button)
			return wrap
		}

		function withdrawControls(set, feedback) {
			var button = el(
				'button',
				'button',
				t('thematiq', 'Withdraw confirmation'),
			)
			button.type = 'button'
			button.addEventListener('click', function () {
				request(
					base
						+ '/'
						+ encodeURIComponent(set.id)
						+ '/licence/confirmation',
					{ method: 'DELETE' },
				).then(function (res) {
					if (res.status === 409) {
						feedback.textContent = fill(
							t(
								'thematiq',
								'This house style is still in use. Choose another one there first: {places}.',
							),
							{
								places: (res.body.appliedIn || [])
									.map(placeName)
									.join(', '),
							},
						)
						return undefined
					}
					if (!res.ok) {
						feedback.textContent = t(
							'thematiq',
							'The confirmation was not withdrawn. Try again.',
						)
						return undefined
					}
					return load(set.id)
				})
			})
			return button
		}

		function row(set, openId) {
			var holder = set.licence.holder || set.name
			var details = el('details', 'nldesign-set-licence')
			details.id = 'nldesign-set-licence-' + set.id
			details.open = set.id === openId
			var summary = el('summary')
			summary.appendChild(el('strong', '', set.name))
			summary.appendChild(document.createTextNode(' · ' + statusText(set)))
			details.appendChild(summary)

			var warning = el(
				'p',
				'nldesign-set-licence-warning',
				fill(
					t(
						'thematiq',
						'This house style belongs to {holder}. Its licence allows use only by {holder}, or with its permission.',
					),
					{ holder: holder },
				).replace('{holder}', holder),
			)
			warning.setAttribute('role', 'note')
			details.appendChild(warning)
			details.appendChild(
				el(
					'p',
					'settings-hint',
					fill(t('thematiq', 'Source: {package} {version}'), {
						package: set.licence.package,
						version: set.licence.version,
					}),
				),
			)
			details.appendChild(el('h4', '', t('thematiq', 'Licence text')))
			var text = el('pre', 'nldesign-set-licence-text')
			text.tabIndex = 0
			details.appendChild(text)

			var feedback = el('p', 'nldesign-set-licence-feedback')
			feedback.setAttribute('role', 'status')
			details.appendChild(
				set.confirmation
					? withdrawControls(set, feedback)
					: confirmControls(set, feedback),
			)
			details.appendChild(feedback)

			details.addEventListener('toggle', function () {
				if (details.open) {
					loadText(set, text)
				}
			})
			if (details.open) {
				loadText(set, text)
			}
			return details
		}

		function renderAwaiting() {
			if (!awaiting) {
				return
			}
			awaiting.textContent = ''
			awaiting.hidden = state.awaiting.length === 0
			state.awaiting.forEach(function (entry) {
				var set = state.sets.find(function (s) {
					return s.id === entry.tokenSet
				})
				var line = el(
					'p',
					'',
					fill(
						t(
							'thematiq',
							'{name} is in use, and nobody has confirmed that your organisation may use it. It stays applied.',
						),
						{ name: set ? set.name : entry.tokenSet },
					) + ' ',
				)
				var link = el('a', '', t('thematiq', 'Confirm it now'))
				link.href = '#nldesign-set-licence-' + entry.tokenSet
				link.addEventListener('click', function () {
					open(entry.tokenSet)
				})
				line.appendChild(link)
				awaiting.appendChild(line)
			})
		}

		function renderHint() {
			if (!hint || !select) {
				return
			}
			var set = state.sets.find(function (s) {
				return s.id === select.value
			})
			hint.textContent = ''
			hint.hidden = !set || !!set.confirmation
			if (hint.hidden) {
				return
			}
			hint.appendChild(
				document.createTextNode(
					t(
						'thematiq',
						'Confirm first that your organisation may use this house style.',
					) + ' ',
				),
			)
			var link = el('a', '', t('thematiq', 'Go to the confirmation'))
			link.href = '#nldesign-set-licence-' + set.id
			link.addEventListener('click', function () {
				open(set.id)
			})
			hint.appendChild(link)
		}

		function open(setId) {
			var target = document.getElementById('nldesign-set-licence-' + setId)
			if (target) {
				target.open = true
			}
		}

		function render(openId) {
			list.textContent = ''
			block.hidden = state.sets.length === 0
			state.sets.forEach(function (set) {
				list.appendChild(row(set, openId))
			})
			renderAwaiting()
			renderHint()
		}

		function load(openId) {
			return request(base + '/licences').then(function (res) {
				if (!res.ok) {
					return
				}
				state.sets = res.body.sets || []
				state.awaiting = res.body.awaiting || []
				render(openId)
			})
		}

		if (select) {
			select.addEventListener('change', renderHint)
		}
		return load()
	}
})()
