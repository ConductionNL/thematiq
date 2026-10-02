/**
 * NL Design System Theme - Preview Banner ("proefdraaien")
 *
 * Loaded ONLY when the injection layer (lib/AppInfo/Application.php) has
 * decided a theme preview is active for the current request — see
 * openspec/specs/theme-preview/spec.md#requirement-preview-banner. Reads its
 * state via OCP.InitialState.loadState() (never a DOM data-attribute) and
 * renders a persistent, keyboard-operable banner with Publish and Discard
 * controls.
 */
;(function nldesignPreviewBannerInit() {
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', nldesignPreviewBannerMain)
	} else {
		nldesignPreviewBannerMain()
	}

	function nldesignPreviewBannerMain() {
		if (
			typeof OCP === 'undefined'
			|| !OCP.InitialState
			|| typeof OCP.InitialState.loadState !== 'function'
		) {
			return
		}

		var state = OCP.InitialState.loadState('thematiq', 'preview', null)
		if (!state || !state.tokenSet) {
			return
		}

		var name = state.name || state.tokenSet

		var banner = document.createElement('div')
		banner.id = 'nldesign-preview-banner'
		banner.className = 'nldesign-preview-banner'
		banner.setAttribute('role', 'status')

		var text = document.createElement('span')
		text.className = 'nldesign-preview-banner-text'
		text.textContent = t(
			'thematiq',
			'Preview: {name} — this is only visible to you',
			{ name: name },
		)
		banner.appendChild(text)

		var actions = document.createElement('span')
		actions.className = 'nldesign-preview-banner-actions'

		var publishLink = document.createElement('a')
		publishLink.className = 'nldesign-preview-banner-publish button'
		publishLink.href =
			OC.generateUrl('/settings/admin/theming') + '#nldesign-settings'
		publishLink.textContent = t('thematiq', 'Publish')
		actions.appendChild(publishLink)

		var discardBtn = document.createElement('button')
		discardBtn.type = 'button'
		discardBtn.className = 'nldesign-preview-banner-discard button'
		discardBtn.textContent = t('thematiq', 'Discard')
		discardBtn.addEventListener('click', function () {
			discardBtn.disabled = true
			fetch(OC.generateUrl('/apps/thematiq/settings/preview'), {
				method: 'DELETE',
				headers: { requesttoken: OC.requestToken },
			})
				.then(function () {
					window.location.reload()
				})
				.catch(function (err) {
					console.error('Error discarding theme preview:', err)
					discardBtn.disabled = false
					// OC.Notification was removed in NC 34; guard so a missing
					// toast API can never throw on top of the original failure.
					try {
						if (
							window.OCP
							&& OCP.Toast
							&& typeof OCP.Toast.message === 'function'
						) {
							OCP.Toast.message(
								t('thematiq', 'Failed to discard theme preview.'),
							)
						}
					} catch (e) {
						console.info('[nldesign] Failed to discard theme preview.')
					}
				})
		})
		actions.appendChild(discardBtn)

		banner.appendChild(actions)

		document.body.appendChild(banner)
	}
})()
