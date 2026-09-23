module.exports = {
	extends: 'stylelint-config-recommended',
	// Generated files, re-emitted from a vendored upstream stylesheet. They are
	// faithful to their source by definition, including where the source says
	// something twice — `core/css/guest.css` declares `a` in two places — and
	// making them lint-clean would mean making them wrong.
	ignoreFiles: ['css/playground-guest.css'],
	rules: {
		// This is a theming/override app — descending specificity is intentional
		// when overriding Nextcloud's built-in styles.
		'no-descending-specificity': null,
		// Override stylesheets intentionally use shorthand after longhand
		// to reset properties in specific contexts.
		'declaration-block-no-shorthand-property-overrides': null,
	},
}
