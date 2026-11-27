/**
 * The button icons the Tq boards draw, as elements.
 *
 * A button names its icon with `data-thematiq-icon` (css/admin-page.css holds
 * the MDI path of each name). This gives every such button on the Theming page
 * one `<span class="thematiq-button-icon" aria-hidden="true">`, as its first
 * child, the way the boards draw the icon inside the button. It watches the
 * page, because the panels build their header actions and lists after load,
 * and some set a button's text again later, which removes the span.
 *
 * `window.ThematiqButtonIcons.decorate(root)` decorates one subtree at once.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/admin-settings/spec.md#requirement-each-tool-is-the-page-or-panel-its-board-draws-req-aset-103
 */
;(function thematiqButtonIconsInit() {
	/**
	 * Give one button its icon span when it has none.
	 *
	 * @param {Element} button The button.
	 * @return {void}
	 */
	function give(button) {
		var first = button.firstElementChild
		for (var child = first; child !== null; child = child.nextElementSibling) {
			if (child.classList.contains('thematiq-button-icon')) {
				return
			}
		}
		var icon = document.createElement('span')
		icon.className = 'thematiq-button-icon'
		icon.setAttribute('aria-hidden', 'true')
		button.insertBefore(icon, button.firstChild)
	}

	/**
	 * Decorate every icon button in a subtree, the root included.
	 *
	 * @param {Node} root The subtree.
	 * @return {void}
	 */
	function decorate(root) {
		if (!root || root.nodeType !== 1) {
			return
		}
		if (root.hasAttribute('data-thematiq-icon')) {
			give(root)
		}
		Array.prototype.forEach.call(
			root.querySelectorAll('[data-thematiq-icon]'),
			give,
		)
	}

	window.ThematiqButtonIcons = { decorate: decorate }

	/**
	 * Decorate the page now and whatever is added or rewritten later.
	 *
	 * @return {void}
	 */
	function start() {
		decorate(document.body)
		if (typeof window.MutationObserver !== 'function') {
			return
		}
		new window.MutationObserver(function (records) {
			records.forEach(function (record) {
				if (
					record.target.nodeType === 1
					&& record.target.hasAttribute('data-thematiq-icon')
				) {
					give(record.target)
				}
				Array.prototype.forEach.call(record.addedNodes, decorate)
			})
		}).observe(document.body, { childList: true, subtree: true })
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start)
	} else {
		start()
	}
})()
