<?php
/**
 * @var array $tokenSets
 * @var string $currentTokenSet
 * @var string $currentDesignSystem
 * @var bool $darkVariantsEnabled
 * @var bool $marianneEnabled
 * @var array{state: string, configReadOnly: bool, foreignClass: ?string} $emailThemingState
 * @var array{orgName: string, accessibilityUrl: string, privacyUrl: string} $emailFooterConfig
 * @var string $occEnableCommand
 * @var string $occDisableCommand
 * @var array{tokenSet: string, name: string}|null $activePreview
 * @var string[] $activeIconPacks
 * @var 'design-system'|'override' $iconPackSource
 * @var bool $mockUi
 * @var string $environment
 * @var string $environmentCommand
 */

// Load the pure token/colour transforms first so admin.js can consume them via
// window.NldesignTokenTransforms (admin.js falls back to inline copies if absent).
script('thematiq', 'lib/tokenTransforms');
// The stylesheet layer swap (window.NldesignLayerSwap): applies a token set to
// the page the admin is on by replacing Thematiq's <link>/<style> elements with
// the ones the server would emit for the new set — no reload. admin.js falls
// back to its old reload-asking toasts when the module is absent.
script('thematiq', 'lib/layerSwap');
// The audit log's value formatting (window.ThematiqAuditFormat): four pure
// functions the panel renders each entry with. A module of its own so they can
// be unit-tested; admin.js falls back to raw values when it is absent.
script('thematiq', 'lib/auditFormat');
// The simple brand form (window.NldesignBrandForm, on window.NldesignTokenConverter): the preview
// derives the set from two colours exactly as BrandFormService stores it.
script('thematiq', 'lib/tokenConverter');
script('thematiq', 'lib/brandForm');
// The per-app theming list's search, count and exclusion list
// (window.NldesignAppTheming); admin.js builds the dropdown from it.
script('thematiq', 'lib/appTheming');
// What syncing Nextcloud's own theming to a set would do
// (window.NldesignThemingPlan); admin.js shows and applies the plan.
script('thematiq', 'lib/themingPlan');
// The inert markup renderer (window.NldesignMarkupRender) admin.js and the
// playground put their built markup through instead of assigning innerHTML.
script('thematiq', 'lib/markupRender');
// The board-drawn dialog frame and the dialogs admin.js opens in it.
script('thematiq', 'dialogs/admin-dialog');
script('thematiq', 'dialogs/reset-theme-dialog');
script('thematiq', 'dialogs/new-theme-dialog');
script('thematiq', 'dialogs/save-overrides-dialog');
script('thematiq', 'dialogs/base-tokens-dialog');
script('thematiq', 'dialogs/theming-update-dialog');
style('thematiq', 'admin-dialog');
script('thematiq', 'admin');
script('thematiq', 'admin-assistant-mark');
script('thematiq', 'admin-active-set-preview');
script('thematiq', 'admin-token-sets-table');
script('thematiq', 'admin-website-lettering');
script('thematiq', 'admin-panels');
script('thematiq', 'admin-layout-panel');
script('thematiq', 'admin-dark-panel');
script('thematiq', 'admin-custom-css-panel');
script('thematiq', 'admin-log-panel');
script('thematiq', 'admin-fonts-panel');
script('thematiq', 'admin-contrast-panel');
script('thematiq', 'admin-bundle-panel');
script('thematiq', 'admin-assistant-panel');
style('thematiq', 'admin-panels');
script('thematiq', 'admin-config-source');
script('thematiq', 'admin-documents');
script('thematiq', 'admin-app-brands');
script('thematiq', 'admin-set-licence');
script('thematiq', 'admin-workspace-icons');
style('thematiq', 'admin');
// Your own tokens and the deprecations list (authoring-token-lifecycle).
script('thematiq', 'ownTokens');
// The component playground: the selector / stage / tokens instrument that
// admin.js's token editor is rebuilt into. Loaded AFTER admin.js because it
// attaches to the editor that script renders, and waits for it.
// The sanitiser and the sandboxed frame of the playground's "Your component" stage.
script('thematiq', 'lib/markupSanitizer');
script('thematiq', 'lib/ownComponentFrame');
// The code editor of that stage, bundled by `npm run build:code-editor`. A
// checkout that has not run the build has no such file, and the stage then keeps
// its plain text areas; asking for a missing script would fail the page.
if (file_exists(__DIR__ . '/../../js/vendor/codeEditor.js') === true) {
	script('thematiq', 'vendor/codeEditor');
}
script('thematiq', 'playground');
style('thematiq', 'playground');
// Nextcloud's own login-page stylesheet, scoped to the playground's login card.
// It is the one part of Nextcloud whose CSS this page does not already load —
// the component chunks for buttons, inputs and checkboxes it does — which is
// why only the login card needed a copy of its own. Generated from a vendored
// upstream file; see scripts/generate-guest-css.mjs.
style('thematiq', 'playground-guest');
if ($_['mockUi'] === true) {
	// Presentation mock — only with `?mock=1` (lib/Settings/Admin.php).
	script('thematiq', 'admin-mock');
	style('thematiq', 'admin-mock');
}
?>

<!-- Server state for js/admin.js (tokenSets, currentTokenSet, activePreview,
     iconPackSource) travels via IInitialState, provided in
     lib/Settings/Admin.php and read with OCP.InitialState.loadState() — NOT
     via data-* attributes on this element. See ADR-004. -->
<div id="nldesign-settings" class="section">
	<!-- The heading row TqTheming draws: the title, the environment chip and
	     one line. The OTAP environment is the one this server declares in
	     config.php, read-only (openspec/specs/environment-marker/spec.md); no
	     control writes it. Without one, the occ command goes on a line under
	     the row. -->
	<div class="nldesign-settings-header">
		<h2>Thematiq</h2>
		<div class="nldesign-environment" id="nldesign-environment">
			<?php // phpcs:disable Squiz.NamingConventions.ValidVariableName.NotCamelCaps -- a template reads its parameters from $_. ?>
			<?php if ($_['environment'] !== ''): ?>
				<span class="nldesign-environment__chip"><?php p($l->t('Environment: {environment}', ['environment' => $_['environment']])); ?></span>
			<?php else: ?>
				<p class="settings-hint"><?php p($l->t('No environment is set, so no page is marked. Declare it in config.php with:')); ?></p>
				<code><?php p($_['environmentCommand']); ?></code>
			<?php endif; ?>
			<?php // phpcs:enable Squiz.NamingConventions.ValidVariableName.NotCamelCaps ?>
		</div>
		<span class="nldesign-settings-header__line">
			<?php p($l->t('Choose a token set from a Dutch government body as a base, or adjust single tokens.')); ?>
		</span>
	</div>

	<!-- The Active token set card TqTheming draws: the picker with its chips, then
	     what can be done with the set. -->
	<section class="nldesign-token-set-selector thematiq-card"
		aria-labelledby="thematiq-active-set-title">
		<h2 id="thematiq-active-set-title" class="thematiq-card__title"><?php p($l->t('Active token set')); ?></h2>
		<label for="nldesign-token-set-select"><?php p($l->t('Token set')); ?></label>
		<!-- Search the list by name (openspec/changes/token-set-picker-search). admin.js
		     hides the sets that do not match; the selected set always stays in the list. -->
		<div class="nldesign-token-set-search">
			<input type="search" id="nldesign-token-set-search" class="nldesign-token-set-search__input"
				   aria-label="<?php p($l->t('Search the design token sets')); ?>"
				   aria-controls="nldesign-token-set-select"
				   placeholder="<?php p($l->t('Search by name')); ?>"
				   autocomplete="off">
			<span id="nldesign-token-set-search-status" class="nldesign-token-set-search__status" role="status" aria-live="polite"></span>
		</div>
		<div class="nldesign-token-set-row">
			<select id="nldesign-token-set-select" name="nldesign-token-set">
				<?php foreach ($_['tokenSets'] as $tokenSet): ?>
					<option value="<?php p($tokenSet['id']); ?>"
							data-design-system="<?php p($tokenSet['design_system'] ?? 'nldesign'); ?>"
							<?php if ($_['currentTokenSet'] === $tokenSet['id']): ?>selected<?php endif; ?>>
						<?php p($tokenSet['name']); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<span id="nldesign-design-system-badge" class="nldesign-badge"></span>
			<!-- Vocabulary-completeness badge (openspec/specs/token-sets/spec.md's
			     "Incomplete sets are surfaced in the admin dropdown"). Empty and
			     hidden until admin.js reads the selected set's `warnings` — the same
			     channel the WCAG contrast warning already travels on — so a set that
			     never declares the tokens the design system reads is not silently
			     presented as its own brand. -->
			<span id="nldesign-token-set-completeness-badge" class="nldesign-badge" hidden></span>
		</div>
		<!-- A running planned switch puts its token set back on every job run, so a
		     set picked here would silently not last. admin.js fills and shows this
		     while one runs (openspec/specs/scheduled-switch/spec.md). -->
		<!-- The preview tabs, swatches and samples; js/admin-active-set-preview.js draws them. -->
		<div class="thematiq-active-set-preview" id="thematiq-active-set-preview"></div>
		<p class="settings-hint" id="nldesign-token-set-switch-note" role="status" hidden></p>
		<!-- The selected set is another organisation's house style and nobody
		     confirmed it yet (openspec/changes/archive/2026-11-22-token-set-licence-gate/specs/token-set-licences/spec.md).
		     Filled by js/admin-set-licence.js. -->
		<p class="settings-hint nldesign-set-licence-hint" id="nldesign-set-licence-hint" role="status" hidden></p>
		<!-- What can be done with the selected set: the buttons act on it, the
		     links read it. A row of their own, so they no longer wrap into the
		     select's line at arbitrary points. -->
		<div class="nldesign-token-set-actions">
			<button type="button" id="nldesign-reset-theme-btn" class="button">
				<?php p($l->t('Back to the Nextcloud theme')); ?>
			</button>
			<!-- The file format of the download, for Figma and other design tools
			     (openspec/changes/figma-token-files). The rows under Custom token sets use it too. -->
			<select id="nldesign-figma-format" class="nldesign-figma-format"
					aria-label="<?php p($l->t('File format of the design tokens download')); ?>">
				<option value="dtcg"><?php p($l->t('W3C design tokens')); ?></option>
				<option value="figma"><?php p($l->t('Figma variables')); ?></option>
				<option value="tokens-studio"><?php p($l->t('Tokens Studio for Figma')); ?></option>
				<option value="tokens-studio-legacy"><?php p($l->t('Tokens Studio for Figma, older spelling')); ?></option>
				<option value="openproject"><?php p($l->t('OpenProject plugin set file')); ?></option>
			</select>
			<button type="button" id="nldesign-dtcg-download" class="button">
				<?php p($l->t('Download as design tokens')); ?>
			</button>
			<!-- Token reference of the selected set (openspec/specs/token-reference/spec.md);
			     admin.js keeps both links on the selected set. -->
			<span class="nldesign-token-set-links">
				<a id="nldesign-token-reference-link" class="nldesign-token-reference-link"
					target="_blank" rel="noopener noreferrer"><?php p($l->t('Open token set')); ?></a>
				<a id="nldesign-token-reference-download" class="nldesign-token-reference-link"><?php p($l->t('Download token overview')); ?></a>
			</span>
		</div>
		<p class="thematiq-card__note">
			<?php
			// One literal, so the translation tools find the sentence whole.
			// phpcs:ignore Generic.Files.LineLength.MaxExceeded
			p($l->t('File formats: W3C design tokens, Figma with every colour as text, Tokens Studio for Figma, Tokens Studio in the old spelling with value and type, and the set file for the OpenProject plugin. A token keeps the same path in every format. The set file moves a set to the plugin without a connection. Any file can be uploaded again as a custom token set.'));
			?>
		</p>
	</section>

	<!-- Token sets as TqTheming draws them; js/admin-token-sets-table.js fills the
	     table from the tokenSets state and sends Use this token set through the
	     picker above. -->
	<section class="thematiq-token-sets" id="thematiq-token-sets" aria-labelledby="thematiq-token-sets-title">
		<div class="thematiq-token-sets__head">
			<div class="thematiq-token-sets__intro">
				<h2 id="thematiq-token-sets-title" class="thematiq-card__title"><?php p($l->t('Token sets')); ?></h2>
				<span class="thematiq-token-sets__count"></span>
			</div>
			<div class="thematiq-token-sets__tools">
				<label for="thematiq-token-sets-search" class="hidden-visually"><?php p($l->t('Search token sets')); ?></label>
				<span class="thematiq-token-sets__field">
					<span class="thematiq-token-sets__icon thematiq-token-sets__icon--search" aria-hidden="true"></span>
					<input type="search" id="thematiq-token-sets-search" class="thematiq-token-sets__search"
						placeholder="<?php p($l->t('Search token sets')); ?>" autocomplete="off">
				</span>
				<button type="button" class="thematiq-token-sets__own"><?php p($l->t('Own token set')); ?></button>
			</div>
		</div>
		<div class="thematiq-card thematiq-token-sets__card">
			<div class="thematiq-token-sets__scroll">
				<table class="thematiq-token-sets__table">
					<thead>
						<tr>
						<th scope="col" data-column="name"><button type="button"><?php p($l->t('Name')); ?></button></th>
						<th scope="col" data-column="designSystem"><button type="button"><?php p($l->t('Design system')); ?></button></th>
						<th scope="col" data-column="primary"><button type="button"><?php p($l->t('Primary colour')); ?></button></th>
						<th scope="col" data-column="layout"><button type="button"><?php p($l->t('Layout')); ?></button></th>
						<th scope="col" data-column="status"><button type="button"><?php p($l->t('Status')); ?></button></th>
							<th scope="col"><span class="hidden-visually"><?php p($l->t('Actions')); ?></span></th>
						</tr>
					</thead>
					<tbody></tbody>
				</table>
			</div>
			<div class="thematiq-token-sets__footer">
				<span class="thematiq-token-sets__shown" aria-live="polite"></span>
				<button type="button" class="thematiq-token-sets__more"><?php p($l->t('Show more')); ?></button>
			</div>
		</div>
	</section>

	<!-- Layout, Website: lettering and Dark variant, in two columns as TqTheming draws them. -->
	<?php // phpcs:disable Squiz.NamingConventions.ValidVariableName.NotCamelCaps,Generic.Files.LineLength -- a template reads its parameters from $_; the options keep their markup. ?>
	<div class="thematiq-card-grid">
		<section class="thematiq-card thematiq-layout-card" aria-labelledby="thematiq-layout-title">
			<div class="thematiq-card__head">
				<h2 id="thematiq-layout-title" class="thematiq-card__title"><?php p($l->t('Layout')); ?></h2>
				<a class="thematiq-card__link" href="#layout"><?php p($l->t('All options')); ?></a>
			</div>
			<span class="thematiq-card__lead"><?php p($l->t('Leave empty to follow the theme. Your choice here always wins.')); ?></span>
			<!-- Workplace layout and brand stripe (openspec/changes/zuiddrecht-workplace-theme).
			     Each has three states. "Follow the theme" stores nothing: the option then
			     takes the default the active token set carries in token-sets.json, and a
			     set that carries none keeps the layout it always had. -->
			<div class="nldesign-option">
				<label for="thematiq-workplace-layout">
					<?php p($l->t('Workplace layout')); ?>
				</label>
				<select name="thematiq-workplace-layout" id="thematiq-workplace-layout">
					<option value="" <?php if (in_array($_['workplaceLayout'], ['default', 'light'], true) === false): ?>selected<?php endif; ?>>
						<?php p($l->t('Follow the theme')); ?>
					</option>
					<option value="default" <?php if ($_['workplaceLayout'] === 'default'): ?>selected<?php endif; ?>>
						<?php p($l->t('Default')); ?>
					</option>
					<option value="light" <?php if ($_['workplaceLayout'] === 'light'): ?>selected<?php endif; ?>>
						<?php p($l->t('Light top bar')); ?>
					</option>
				</select>
				<p class="settings-hint">
					<?php p($l->t('The light layout paints the top bar in the main background, with its text color. A theme can turn it on by itself. Your choice here always wins.')); ?>
				</p>
			</div>

			<div class="nldesign-option">
				<label for="thematiq-brand-stripe">
					<?php p($l->t('Brand stripe under the top bar')); ?>
				</label>
				<select name="thematiq-brand-stripe" id="thematiq-brand-stripe">
					<option value="" <?php if (in_array($_['brandStripe'], ['0', '1'], true) === false): ?>selected<?php endif; ?>>
						<?php p($l->t('Follow the theme')); ?>
					</option>
					<option value="1" <?php if ($_['brandStripe'] === '1'): ?>selected<?php endif; ?>>
						<?php p($l->t('On')); ?>
					</option>
					<option value="0" <?php if ($_['brandStripe'] === '0'): ?>selected<?php endif; ?>>
						<?php p($l->t('Off')); ?>
					</option>
				</select>
				<p class="settings-hint">
					<?php p($l->t('Three colors along the bottom edge of the top bar. The theme sets the colors and their ratio.')); ?>
				</p>
			</div>

			<!-- The newer layout options (openspec/changes/layout-options-navigation-stripe-watermark):
			     where the stripe is drawn, the navigation width, the selected entry's
			     style and the login watermark. Same three states as the two above:
			     an empty value follows the theme. -->
			<div class="nldesign-option">
				<label for="thematiq-brand-stripe-placement">
					<?php p($l->t('Where the brand stripe is drawn')); ?>
				</label>
				<select name="thematiq-brand-stripe-placement" id="thematiq-brand-stripe-placement">
					<option value="" <?php if (in_array($_['brandStripePlacement'], ['header-and-login', 'header', 'login'], true) === false): ?>selected<?php endif; ?>>
						<?php p($l->t('Follow the theme')); ?>
					</option>
					<option value="header-and-login" <?php if ($_['brandStripePlacement'] === 'header-and-login'): ?>selected<?php endif; ?>>
						<?php p($l->t('Top bar and login card')); ?>
					</option>
					<option value="header" <?php if ($_['brandStripePlacement'] === 'header'): ?>selected<?php endif; ?>>
						<?php p($l->t('Top bar only')); ?>
					</option>
					<option value="login" <?php if ($_['brandStripePlacement'] === 'login'): ?>selected<?php endif; ?>>
						<?php p($l->t('Login card only')); ?>
					</option>
				</select>
				<p class="settings-hint">
					<?php p($l->t('Only while the brand stripe is on. A theme that names nothing draws it in both places.')); ?>
				</p>
			</div>

			<div class="nldesign-option">
				<label for="thematiq-navigation-width-input">
					<?php p($l->t('Navigation width in pixels')); ?>
				</label>
				<input type="number"
					   name="thematiq-navigation-width"
					   id="thematiq-navigation-width-input"
					   min="200"
					   max="480"
					   step="1"
					   placeholder="<?php p($l->t('Follow the theme')); ?>"
					   value="<?php p($_['navigationWidth']); ?>">
				<p class="settings-hint">
					<?php p($l->t('The width of the app navigation, from 200 to 480. Leave it empty to follow the theme; a theme that names none keeps the width Nextcloud gives it.')); ?>
				</p>
			</div>

			<div class="nldesign-option">
				<label for="thematiq-navigation-active-style">
					<?php p($l->t('Selected navigation entry')); ?>
				</label>
				<select name="thematiq-navigation-active-style" id="thematiq-navigation-active-style">
					<option value="" <?php if (in_array($_['navigationActiveStyle'], ['default', 'soft'], true) === false): ?>selected<?php endif; ?>>
						<?php p($l->t('Follow the theme')); ?>
					</option>
					<option value="default" <?php if ($_['navigationActiveStyle'] === 'default'): ?>selected<?php endif; ?>>
						<?php p($l->t('Default')); ?>
					</option>
					<option value="soft" <?php if ($_['navigationActiveStyle'] === 'soft'): ?>selected<?php endif; ?>>
						<?php p($l->t('Soft tint with a bold label')); ?>
					</option>
				</select>
				<p class="settings-hint">
					<?php p($l->t('The soft style draws the entry you are on as a light tint of the accent color, with the accent as its label. A theme without an accent uses its primary color.')); ?>
				</p>
			</div>

			<div class="nldesign-option">
				<label for="thematiq-login-watermark">
					<?php p($l->t('Login watermark')); ?>
				</label>
				<select name="thematiq-login-watermark" id="thematiq-login-watermark">
					<option value="" <?php if (in_array($_['loginWatermark'], ['0', '1'], true) === false): ?>selected<?php endif; ?>>
						<?php p($l->t('Follow the theme')); ?>
					</option>
					<option value="1" <?php if ($_['loginWatermark'] === '1'): ?>selected<?php endif; ?>>
						<?php p($l->t('On')); ?>
					</option>
					<option value="0" <?php if ($_['loginWatermark'] === '0'): ?>selected<?php endif; ?>>
						<?php p($l->t('Off')); ?>
					</option>
				</select>
				<p class="settings-hint">
					<?php p($l->t('The emblem a theme draws large and faint on the login page, with the light top bar. Off hides it; a theme that names no image shows none either way.')); ?>
				</p>
			</div>

			<!-- The header style (openspec/changes/header-style-workplace): the
			     workplace top bar of the DqKop board, with the light workplace layout
			     only. Same three states: an empty value follows the theme. -->
			<div class="nldesign-option">
				<label for="thematiq-header-style">
					<?php p($l->t('Top bar style')); ?>
				</label>
				<select name="thematiq-header-style" id="thematiq-header-style">
					<option value="" <?php if (in_array($_['headerStyle'], ['default', 'workplace'], true) === false): ?>selected<?php endif; ?>>
						<?php p($l->t('Follow the theme')); ?>
					</option>
					<option value="default" <?php if ($_['headerStyle'] === 'default'): ?>selected<?php endif; ?>>
						<?php p($l->t('Default')); ?>
					</option>
					<option value="workplace" <?php if ($_['headerStyle'] === 'workplace'): ?>selected<?php endif; ?>>
						<?php p($l->t('Workplace, with your name and role')); ?>
					</option>
				</select>
				<p class="settings-hint">
					<?php p($l->t('Only with the light workplace layout. The workplace bar drops the logo and the app name. It shows your name and the role from your profile.')); ?>
				</p>
			</div>

			<!-- Primary drives every component — the deliberate opt-out of per-component
			     theming (openspec/specs/component-tokens/spec.md). Off by default, and
			     that is not a behaviour change: with no per-component value stored the
			     component tokens already resolve to the brand primary. Turning it on
			     emits css/primary-lock.css, which forces them back to the brand value,
			     and locks the colour controls the primary now owns. Stored values are
			     kept, so turning it off restores them. -->
			<div class="nldesign-option">
				<input type="checkbox"
					   name="nldesign-primary-drives-components"
					   id="nldesign-primary-drives-components"
					   class="checkbox"
					   <?php if ($_['primaryDrivesComponents'] === true): ?>checked<?php endif; ?>>
				<label for="nldesign-primary-drives-components">
					<?php p($l->t('Let the primary color drive every component')); ?>
				</label>
				<p class="settings-hint">
					<?php p($l->t('While this is on, the brand primary overrules any color set on an individual component, and those controls are locked. Switching it off gives each component its own color back.')); ?>
				</p>
			</div>
			<!-- Hide Slogan/Payoff Option -->
			<div class="nldesign-option">
				<input type="checkbox"
					   name="nldesign-hide-slogan"
					   id="nldesign-hide-slogan"
					   class="checkbox"
					   <?php if ($_['hideSlogan'] === true): ?>checked<?php endif; ?>>
				<label for="nldesign-hide-slogan">
					<?php p($l->t('Hide Nextcloud slogan/payoff on login page')); ?>
				</label>
			</div>

			<!-- Show Menu Labels Option -->
			<div class="nldesign-option">
				<input type="checkbox"
					   name="nldesign-show-menu-labels"
					   id="nldesign-show-menu-labels"
					   class="checkbox"
					   <?php if ($_['showMenuLabels'] === true): ?>checked<?php endif; ?>>
				<label for="nldesign-show-menu-labels">
					<?php p($l->t('Show text labels in app menu (hide icons)')); ?>
				</label>
			</div>
		</section>
		<div class="thematiq-card-column">
			<!-- js/admin-website-lettering.js draws the sizes the active set gives the website. -->
			<section class="thematiq-card thematiq-lettering-card" id="thematiq-website-lettering" aria-labelledby="thematiq-lettering-title">
				<div class="thematiq-card__head">
					<h2 id="thematiq-lettering-title" class="thematiq-card__title"><?php p($l->t('Website: lettering')); ?></h2>
					<a class="thematiq-card__link thematiq-lettering__link" target="_blank" rel="noopener noreferrer"><?php p($l->t('In the token set')); ?></a>
				</div>
				<span class="thematiq-card__lead"><?php p($l->t('The website reads these sizes from the token set. Drawn here at 60 percent.')); ?></span>
				<div class="thematiq-lettering"></div>
			</section>
			<section class="thematiq-card thematiq-dark-card" aria-labelledby="thematiq-dark-title">
				<div class="thematiq-card__head">
					<h2 id="thematiq-dark-title" class="thematiq-card__title"><?php p($l->t('Dark variant')); ?></h2>
					<a class="thematiq-card__link" href="#dark"><?php p($l->t('View and adjust')); ?></a>
				</div>
				<!-- Dark mode variants — instance-wide toggle for the generated dark
				     stylesheets (openspec/specs/dark-mode/spec.md). Never touches the
				     user's/instance's Nextcloud dark/light/system theme choice; it only
				     follows whatever Nextcloud itself already decided. -->
				<div class="nldesign-option">
					<input type="checkbox"
						   name="nldesign-dark-variants"
						   id="nldesign-dark-variants"
						   class="checkbox"
						   <?php if ($_['darkVariantsEnabled'] === true): ?>checked<?php endif; ?>>
					<label for="nldesign-dark-variants">
						<?php p($l->t('Enable dark mode variants for the active token set')); ?>
					</label>
				</div>
				<p class="settings-hint">
					<?php p($l->t('When enabled, a generated dark-mode stylesheet follows whichever Nextcloud dark/light/system theme is already active — it never changes your Nextcloud theme choice itself.')); ?>
				</p>
			</section>
		</div>
	</div>
	<?php // phpcs:enable Squiz.NamingConventions.ValidVariableName.NotCamelCaps,Generic.Files.LineLength ?>

	<?php // phpcs:disable Squiz.NamingConventions.ValidVariableName.NotCamelCaps,Generic.Files.LineLength -- a template reads its parameters from $_; the panel keeps the board's markup. ?>
	<!-- Layout and options (TqLayout), a panel of this page at #layout (design.md).
	     The slots borrow the Layout card's options while the panel is open;
	     js/admin-layout-panel.js adds what the theme says and the session's changes. -->
	<section class="thematiq-panel thematiq-layout-panel" data-thematiq-panel="layout" aria-labelledby="thematiq-page-title" hidden>
		<div class="thematiq-panel__columns">
			<form class="thematiq-panel__main" aria-label="<?php p($l->t('Layout and options')); ?>">
				<section class="thematiq-card thematiq-panel__card">
					<h2 class="thematiq-panel__card-title"><?php p($l->t('Workspace')); ?></h2>
					<div data-thematiq-slot="thematiq-workplace-layout"></div>
					<div data-thematiq-slot="thematiq-header-style"></div>
				</section>
				<section class="thematiq-card thematiq-panel__card">
					<h2 class="thematiq-panel__card-title"><?php p($l->t('Menu')); ?></h2>
					<div data-thematiq-slot="thematiq-navigation-active-style"></div>
					<div data-thematiq-slot="thematiq-navigation-width-input"></div>
					<div data-thematiq-slot="nldesign-show-menu-labels"></div>
				</section>
				<section class="thematiq-card thematiq-panel__card">
					<h2 class="thematiq-panel__card-title"><?php p($l->t('Brand stripe and sign-in page')); ?></h2>
					<div data-thematiq-slot="thematiq-brand-stripe"></div>
					<div data-thematiq-slot="thematiq-brand-stripe-placement"></div>
					<div data-thematiq-slot="thematiq-login-watermark"></div>
					<div data-thematiq-slot="nldesign-hide-slogan"></div>
				</section>
				<section class="thematiq-card thematiq-panel__card">
					<h2 class="thematiq-panel__card-title"><?php p($l->t('Icons, colour and website')); ?></h2>
					<div data-thematiq-slot="nldesign-primary-drives-components"></div>
					<div data-thematiq-slot="nldesign-dark-variants"></div>
					<div class="thematiq-panel__fact">
						<div class="thematiq-panel__fact-text">
							<span class="thematiq-panel__fact-label"><?php p($l->t('Icon set')); ?></span>
							<span class="thematiq-panel__fact-hint"><?php p($l->t('Source: the active design system. Changed with occ, not on this page.')); ?></span>
						</div>
						<div class="thematiq-panel__fact-value">
							<?php if (empty($_['activeIconPacks']) === true): ?>
								<span><?php p($l->t('None')); ?></span>
							<?php else: ?>
								<span><?php p(implode(', ', $_['activeIconPacks'])); ?></span>
							<?php endif; ?>
						</div>
					</div>
					<div class="thematiq-panel__fact">
						<div class="thematiq-panel__fact-text">
							<span class="thematiq-panel__fact-label"><?php p($l->t('Website')); ?></span>
							<span class="thematiq-panel__fact-hint"><?php p($l->t('The website reads its lettering, buttons and columns from the token set.')); ?></span>
						</div>
						<div class="thematiq-panel__fact-value">
							<span class="thematiq-layout-panel__website"></span>
							<a class="thematiq-layout-panel__website-link" target="_blank" rel="noopener noreferrer"><?php p($l->t('In the token set')); ?></a>
						</div>
					</div>
					<div class="thematiq-panel__fact">
						<div class="thematiq-panel__fact-text">
							<span class="thematiq-panel__fact-label"><?php p($l->t('Dark variant')); ?></span>
							<span class="thematiq-panel__fact-hint"><?php p($l->t('Generated from the light tokens, adjustable per token.')); ?></span>
						</div>
						<div class="thematiq-panel__fact-value">
							<a href="#dark"><?php p($l->t('Open dark variant')); ?></a>
						</div>
					</div>
				</section>
			</form>
			<aside class="thematiq-panel__aside" aria-label="<?php p($l->t('This session')); ?>">
				<section class="thematiq-card thematiq-panel__card thematiq-layout-panel__session" aria-labelledby="thematiq-layout-session-title">
					<div class="thematiq-panel__card-head">
						<h2 id="thematiq-layout-session-title" class="thematiq-panel__card-title"><?php p($l->t('Changes in this session')); ?></h2>
						<span class="thematiq-panel__count">0</span>
					</div>
					<span class="thematiq-layout-panel__summary"></span>
					<div class="thematiq-layout-panel__changes"></div>
					<span class="thematiq-panel__fact-hint"><?php p($l->t('Each choice is live for everyone as you make it, and writes a line in the log.')); ?></span>
				</section>
			</aside>
		</div>
	</section>

	<!-- Dark variant (TqDonker), a panel of this page at #dark (design.md).
	     js/admin-dark-panel.js draws the previews, the tokens and the contrast check
	     from the active set's light and generated dark token files. -->
	<section class="thematiq-panel thematiq-dark-panel" data-thematiq-panel="dark" aria-labelledby="thematiq-page-title" hidden>
		<div class="thematiq-dark-panel__previews"></div>
		<div class="thematiq-panel__columns">
			<section class="thematiq-card thematiq-panel__card thematiq-dark-panel__tokens" aria-labelledby="thematiq-dark-tokens-title">
				<div class="thematiq-dark-panel__tokens-head">
					<div class="thematiq-dark-panel__tokens-intro">
						<h2 id="thematiq-dark-tokens-title" class="thematiq-panel__card-title"><?php p($l->t('Tokens, light next to dark')); ?></h2>
						<span class="thematiq-panel__fact-hint thematiq-dark-panel__measured"></span>
					</div>
					<label class="thematiq-dark-panel__filter"><input type="checkbox" class="thematiq-dark-panel__only-warnings"> <?php p($l->t('Warnings only')); ?></label>
				</div>
				<div class="thematiq-dark-panel__rows" role="table" aria-labelledby="thematiq-dark-tokens-title"></div>
			</section>
			<aside class="thematiq-panel__aside thematiq-dark-panel__aside">
				<section class="thematiq-card thematiq-panel__card thematiq-dark-panel__check" aria-labelledby="thematiq-dark-check-title">
					<h2 id="thematiq-dark-check-title" class="thematiq-panel__card-title thematiq-dark-panel__aside-title"><?php p($l->t('Contrast check')); ?></h2>
					<strong class="thematiq-dark-panel__score"></strong>
					<span class="thematiq-panel__fact-hint thematiq-dark-panel__score-line"><?php p($l->t('text and border colours meet their standard in dark')); ?></span>
					<span class="thematiq-dark-panel__bar"><span></span></span>
					<ul class="thematiq-dark-panel__problems"></ul>
				</section>
				<section class="thematiq-card thematiq-panel__card thematiq-dark-panel__how" aria-labelledby="thematiq-dark-how-title">
					<h2 id="thematiq-dark-how-title" class="thematiq-panel__card-title thematiq-dark-panel__aside-title"><?php p($l->t('How it works')); ?></h2>
					<ol class="thematiq-dark-panel__steps">
						<li><span>1</span><span><?php p($l->t('The generator reads the light tokens of the set and works out a dark value per colour that keeps its contrast.')); ?></span></li>
						<li><span>2</span><span><?php p($l->t('Your own dark values come first. In the token editor, an empty dark field stays derived from the light value.')); ?></span></li>
						<li><span>3</span><span><?php p($l->t('The dark stylesheet follows the choice Nextcloud makes for system, light or dark.')); ?></span></li>
						<li><span>4</span><span><?php p($l->t('The logo changes along: in dark the set shows its dark logo when it has one. Without a dark logo the light one stays.')); ?></span></li>
						<li><span>5</span><span><?php p($l->t('The High contrast set also follows the operating system: with the preference for more contrast it gets stronger, and with forced colours it leaves the system colours alone.')); ?></span></li>
					</ol>
					<div class="thematiq-dark-panel__toggle" data-thematiq-slot="nldesign-dark-variants"></div>
				</section>
			</aside>
		</div>
	</section>

	<!-- Custom CSS (TqEigenCss), a panel of this page at #css (design.md): admin-authored
	     rules, checked server-side and emitted after every other theming layer.
	     js/admin.js saves them; js/admin-custom-css-panel.js numbers the lines. -->
	<section class="thematiq-panel thematiq-css-panel" data-thematiq-panel="css" aria-labelledby="thematiq-page-title" hidden>
		<div class="thematiq-panel__columns">
			<div class="thematiq-panel__main">
				<section class="thematiq-card thematiq-panel__card nldesign-custom-css" id="nldesign-custom-css" aria-labelledby="thematiq-css-title">
					<div class="thematiq-css-panel__head">
						<h2 id="thematiq-css-title" class="thematiq-panel__card-title thematiq-css-panel__title"><?php p($l->t('Custom CSS')); ?></h2>
						<span class="thematiq-panel__fact-hint thematiq-css-panel__lead"><?php p($l->t('Free CSS for what the tokens cannot express. Use a token where you can, then your CSS follows the house style.')); ?></span>
					</div>
					<div class="thematiq-css-panel__check">
						<input type="checkbox" id="nldesign-custom-css-enabled" class="checkbox" aria-describedby="thematiq-css-enabled-hint">
						<label for="nldesign-custom-css-enabled"><?php p($l->t('Enable custom CSS')); ?></label>
						<span id="thematiq-css-enabled-hint" class="thematiq-panel__fact-hint"><?php p($l->t('Off: the CSS is not loaded.')); ?></span>
					</div>
					<div class="thematiq-css-panel__field">
						<label for="nldesign-custom-css-input" class="thematiq-css-panel__label"><?php p($l->t('Custom CSS')); ?></label>
						<div class="thematiq-css-panel__editor">
							<div class="thematiq-css-panel__gutter" aria-hidden="true"></div>
							<textarea id="nldesign-custom-css-input" rows="10" spellcheck="false" aria-describedby="thematiq-css-input-hint" placeholder=".app-content { padding: 8px; }"></textarea>
						</div>
						<span id="thematiq-css-input-hint" class="thematiq-panel__fact-hint"><?php p($l->t('Comes after all other layers of thematiq, so it always wins.')); ?></span>
					</div>
					<div class="thematiq-css-panel__actions">
						<span id="nldesign-custom-css-feedback" class="thematiq-css-panel__feedback" role="status" aria-live="polite"></span>
						<span class="thematiq-preview__spacer"></span>
						<button type="button" id="nldesign-custom-css-save" class="thematiq-css-panel__save"><?php p($l->t('Save custom CSS')); ?></button>
					</div>
				</section>
			</div>
			<aside class="thematiq-panel__aside" aria-label="<?php p($l->t('Explanation')); ?>">
				<section class="thematiq-card thematiq-panel__card thematiq-css-panel__aside-card" aria-labelledby="thematiq-css-refuses-title">
					<h2 id="thematiq-css-refuses-title" class="thematiq-panel__card-title"><?php p($l->t('What the check refuses')); ?></h2>
					<div class="thematiq-css-panel__rules">
						<div><span>@import</span><strong><?php p($l->t('refused')); ?></strong></div>
						<div><span><?php p($l->t('url() to outside')); ?></span><strong><?php p($l->t('refused')); ?></strong></div>
						<div><span><?php p($l->t('scripts and HTML tags')); ?></span><strong><?php p($l->t('refused')); ?></strong></div>
						<div><span><?php p($l->t('background variables for dark')); ?></span><strong><?php p($l->t('refused')); ?></strong></div>
						<div><span><?php p($l->t('relative paths and data:')); ?></span><strong><?php p($l->t('allowed')); ?></strong></div>
					</div>
					<p class="thematiq-panel__fact-hint"><?php p($l->t('The check runs before saving. If something is refused, thematiq saves nothing and you see why.')); ?></p>
				</section>
				<section class="thematiq-card thematiq-panel__card thematiq-css-panel__aside-card" aria-labelledby="thematiq-css-tokens-title">
					<h2 id="thematiq-css-tokens-title" class="thematiq-panel__card-title"><?php p($l->t('Using custom tokens')); ?></h2>
					<p class="thematiq-panel__fact-hint"><?php p($l->t('A custom token such as --nldesign-org-brand-accent can be read here directly with var(). So you change a colour in one place only.')); ?></p>
				</section>
				<section class="thematiq-card thematiq-panel__card thematiq-css-panel__aside-card" aria-labelledby="thematiq-css-log-title">
					<h2 id="thematiq-css-log-title" class="thematiq-panel__card-title"><?php p($l->t('In the log')); ?></h2>
					<p class="thematiq-panel__fact-hint"><?php p($l->t('Every save goes in the log, with who and when.')); ?></p>
				</section>
			</aside>
		</div>
	</section>

	<!-- Log (TqLogboek), a panel of this page at #log (design.md): who changed which theming
	     setting, from what, to what, and when; evidence for accessibility audits.
	     js/admin.js fills the table; js/admin-log-panel.js the counts. -->
	<section class="thematiq-panel thematiq-log-panel" data-thematiq-panel="log" aria-labelledby="thematiq-page-title" hidden>
		<section class="thematiq-card thematiq-panel__card nldesign-audit-log" id="nldesign-audit-log" aria-labelledby="thematiq-log-title">
			<div class="thematiq-css-panel__head">
				<h2 id="thematiq-log-title" class="thematiq-panel__card-title thematiq-css-panel__title"><?php p($l->t('Changes')); ?></h2>
				<span class="thematiq-panel__fact-hint thematiq-css-panel__lead"><?php p($l->t('Who changed what, from what to what, and when. Usable as evidence in an accessibility audit.')); ?></span>
			</div>
			<div class="nldesign-audit-scroll" tabindex="0" role="region" aria-label="<?php p($l->t('Changes')); ?>">
				<table class="nldesign-audit-table" id="nldesign-audit-table">
					<thead>
						<tr>
							<th scope="col"><?php p($l->t('Time')); ?></th>
							<th scope="col"><?php p($l->t('Who')); ?></th>
							<th scope="col"><?php p($l->t('Action')); ?></th>
							<th scope="col"><?php p($l->t('From')); ?></th>
							<th scope="col"><?php p($l->t('To')); ?></th>
							<th scope="col"><?php p($l->t('Changed')); ?></th>
							<th scope="col"><?php p($l->t('Version')); ?></th>
						</tr>
					</thead>
					<tbody id="nldesign-audit-table-body">
						<tr><td colspan="7" class="settings-hint"><?php p($l->t('Loading audit log…')); ?></td></tr>
					</tbody>
				</table>
			</div>
			<span class="thematiq-log-panel__count"></span>
			<button type="button" id="nldesign-audit-download-btn" hidden><?php p($l->t('Download full log')); ?></button>
		</section>
		<div class="thematiq-log-panel__cards">
			<section class="thematiq-card thematiq-panel__card thematiq-css-panel__aside-card" aria-labelledby="thematiq-log-restore-title">
				<h2 id="thematiq-log-restore-title" class="thematiq-panel__card-title"><?php p($l->t('Restore')); ?></h2>
				<p class="thematiq-panel__fact-hint"><?php p($l->t('Every change keeps the configuration that resulted from it, up to the last 50 changes or 20 MB. Restore first shows what changes before anything is written.')); ?></p>
				<div class="thematiq-css-panel__rules">
					<div><span><?php p($l->t('Saved versions')); ?></span><strong class="thematiq-log-panel__versions"></strong></div>
				</div>
			</section>
			<section class="thematiq-card thematiq-panel__card thematiq-css-panel__aside-card" aria-labelledby="thematiq-log-occ-title">
				<h2 id="thematiq-log-occ-title" class="thematiq-panel__card-title"><?php p($l->t('Also via occ')); ?></h2>
				<code class="thematiq-log-panel__code">occ thematiq:config:versions</code>
				<code class="thematiq-log-panel__code">occ thematiq:config:restore &lt;id&gt; --dry-run</code>
			</section>
			<section class="thematiq-card thematiq-panel__card thematiq-css-panel__aside-card" aria-labelledby="thematiq-log-kinds-title">
				<h2 id="thematiq-log-kinds-title" class="thematiq-panel__card-title"><?php p($l->t('What goes in the log')); ?></h2>
				<p class="thematiq-panel__fact-hint"><?php p($l->t('Token set switched, tokens changed or imported, custom token sets, theme per app, group theme, preview published, configuration imported, scheduled switches, custom tokens and deprecated tokens.')); ?></p>
			</section>
		</div>
	</section>

	<!-- Fonts (TqLettertypen), a panel of this page at #fonts (design.md): self-hosted
	     WOFF2 uploads and the approved library. js/admin.js uploads, lists and assigns. -->
	<section class="thematiq-panel thematiq-fonts-panel nldesign-custom-fonts" id="nldesign-custom-fonts" data-thematiq-panel="fonts" aria-labelledby="thematiq-page-title" hidden>
		<div class="thematiq-panel__columns">
		<div class="thematiq-panel__main">
			<section class="thematiq-card thematiq-panel__card thematiq-fonts-panel__card" aria-labelledby="thematiq-fonts-upload-title">
				<div class="thematiq-css-panel__head">
					<h2 id="thematiq-fonts-upload-title" class="thematiq-panel__card-title thematiq-css-panel__title"><?php p($l->t('Upload font')); ?></h2>
					<span class="thematiq-panel__fact-hint thematiq-css-panel__lead"><?php p($l->t('A WOFF2 file for the headings or the body text. Thematiq hosts it itself: no requests go outside.')); ?></span>
				</div>
				<div class="thematiq-fonts-panel__notice nldesign-license-notice"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12,2L1,21H23M12,6L19.53,19H4.47M11,10V14H13V10M11,16V18H13V16"/></svg><span><?php p($l->t('Upload only fonts your organisation is licensed to host itself. Whoever uploads is responsible for the licence.')); ?></span></div>
				<div class="thematiq-admin-dialog__grid thematiq-fonts-panel__grid">
					<div class="thematiq-fonts-panel__field">
						<label for="nldesign-font-name"><?php p($l->t('Display name')); ?></label>
						<input type="text" id="nldesign-font-name" class="nldesign-font-name" placeholder="<?php p($l->t('for example Rijks Sans')); ?>" maxlength="64">
					</div>
					<div class="thematiq-fonts-panel__field">
						<label for="nldesign-font-role"><?php p($l->t('Role')); ?></label>
						<select id="nldesign-font-role" name="nldesign-font-role">
							<option value="heading"><?php p($l->t('Headings')); ?></option>
							<option value="body"><?php p($l->t('Body text')); ?></option>
						</select>
					</div>
				</div>
				<div class="thematiq-fonts-panel__field">
					<span class="thematiq-fonts-panel__label"><?php p($l->t('File')); ?></span>
					<div class="thematiq-admin-dialog__file-row">
						<input type="file" id="nldesign-font-input" accept=".woff2" aria-label="<?php p($l->t('Font file to upload (WOFF2)')); ?>" hidden>
						<button type="button" id="nldesign-font-upload-btn" class="thematiq-font-row__button"><?php p($l->t('Choose and upload font')); ?></button>
						<span class="thematiq-panel__fact-hint"><?php p($l->t('WOFF2 only, at most 2 MB')); ?></span>
					</div>
				</div>
				<div id="nldesign-font-upload-result" class="nldesign-import-result" role="status" aria-live="polite" style="display:none"></div>
			</section>
			<section class="thematiq-card thematiq-panel__card thematiq-fonts-panel__card" aria-labelledby="nldesign-font-library-heading">
				<div class="thematiq-css-panel__head">
					<h2 id="nldesign-font-library-heading" class="thematiq-panel__card-title"><?php p($l->t('Library of approved fonts')); ?></h2>
					<span class="thematiq-panel__fact-hint"><?php p($l->t('The fonts thematiq bundles, each with its licence. Pick one for the headings or the body text: no upload needed, the font goes into the token with its fallback chain.')); ?></span>
				</div>
				<div id="nldesign-font-library" class="thematiq-font-list" role="list" aria-labelledby="nldesign-font-library-heading">
					<p class="settings-hint"><?php p($l->t('Loading font library…')); ?></p>
				</div>
			</section>
			<section class="thematiq-card thematiq-panel__card thematiq-fonts-panel__card" aria-labelledby="thematiq-fonts-uploaded-title">
				<div class="thematiq-css-panel__head">
					<h2 id="thematiq-fonts-uploaded-title" class="thematiq-panel__card-title"><?php p($l->t('Uploaded fonts')); ?></h2>
					<span class="thematiq-panel__fact-hint"><?php p($l->t('At most 20. After an upload, reload the page to see the font.')); ?></span>
				</div>
				<div id="nldesign-font-list" class="thematiq-font-list" role="list" aria-labelledby="thematiq-fonts-uploaded-title">
					<p class="settings-hint"><?php p($l->t('Loading fonts…')); ?></p>
				</div>
			</section>
		</div>
		<aside class="thematiq-panel__aside" aria-label="<?php p($l->t('Explanation')); ?>">
			<section class="thematiq-card thematiq-panel__card thematiq-css-panel__aside-card" aria-labelledby="thematiq-fonts-now-title">
				<h2 id="thematiq-fonts-now-title" class="thematiq-panel__card-title"><?php p($l->t('Now in use')); ?></h2>
				<div class="thematiq-fonts-panel__now"></div>
				<p class="thematiq-panel__fact-hint"><?php p($l->t('Without an upload the font follows the active token set.')); ?></p>
			</section>
			<section class="thematiq-card thematiq-panel__card thematiq-css-panel__aside-card" aria-labelledby="thematiq-fonts-rules-title">
				<h2 id="thematiq-fonts-rules-title" class="thematiq-panel__card-title"><?php p($l->t('Rules')); ?></h2>
				<div class="thematiq-css-panel__rules">
					<div><span><?php p($l->t('Format')); ?></span><strong>WOFF2</strong></div>
					<div><span><?php p($l->t('Per file')); ?></span><strong><?php p($l->t('at most 2 MB')); ?></strong></div>
					<div><span><?php p($l->t('Number')); ?></span><strong><?php p($l->t('at most 20')); ?></strong></div>
					<div><span><?php p($l->t('Roles')); ?></span><strong><?php p($l->t('headings, body text')); ?></strong></div>
					<div><span><?php p($l->t('External requests')); ?></span><strong><?php p($l->t('none')); ?></strong></div>
				</div>
			</section>
			<section class="thematiq-card thematiq-panel__card thematiq-css-panel__aside-card" aria-labelledby="thematiq-fonts-set-title">
				<h2 id="thematiq-fonts-set-title" class="thematiq-panel__card-title"><?php p($l->t('Fonts in the token set')); ?></h2>
				<p class="thematiq-panel__fact-hint"><?php p($l->t('A token set can ask for a font that still needs uploading or a licence confirmation. That is then shown with the token set.')); ?></p>
			</section>
			<div data-thematiq-slot="nldesign-marianne-gate"></div>
		</aside>
		</div>
	</section>

	<!-- Contrast report (TqContrastrapport), a panel of this page at #contrast (design.md):
	     the colour contrast of the active theme's tokens, as evidence for an accessibility
	     statement. js/admin-contrast-panel.js reads the report; js/admin.js downloads it. -->
	<section class="thematiq-panel thematiq-contrast-panel nldesign-compliance-report" id="nldesign-compliance-report" data-thematiq-panel="contrast" aria-labelledby="thematiq-page-title" hidden>
		<div class="thematiq-contrast-panel__tiles"></div>
		<section class="thematiq-card thematiq-panel__card thematiq-contrast-panel__card" aria-labelledby="thematiq-contrast-title">
			<div class="thematiq-css-panel__head">
				<h2 id="thematiq-contrast-title" class="thematiq-panel__card-title thematiq-css-panel__title"><?php p($l->t('Colour pairs of the active token set')); ?></h2>
				<span class="thematiq-panel__fact-hint thematiq-css-panel__lead"><?php p($l->t('Text must reach at least 4.5:1, parts such as borders and icons at least 3:1 (WCAG 2.1 AA, 1.4.3 and 1.4.11).')); ?></span>
			</div>
			<div class="nldesign-audit-scroll" tabindex="0" role="region" aria-label="<?php p($l->t('Colour pairs of the active token set')); ?>">
				<table class="nldesign-audit-table thematiq-contrast-panel__table">
					<thead>
						<tr>
							<th scope="col"><?php p($l->t('Foreground')); ?></th>
							<th scope="col"><?php p($l->t('Background')); ?></th>
							<th scope="col"><?php p($l->t('Example')); ?></th>
							<th scope="col"><?php p($l->t('Ratio')); ?></th>
							<th scope="col"><?php p($l->t('Standard')); ?></th>
							<th scope="col"><?php p($l->t('Verdict')); ?></th>
						</tr>
					</thead>
					<tbody class="thematiq-contrast-panel__rows">
						<tr><td colspan="6" class="settings-hint"><?php p($l->t('Loading the report…')); ?></td></tr>
					</tbody>
				</table>
			</div>
			<a id="nldesign-compliance-report-json" download hidden><?php p($l->t('Download as JSON')); ?></a>
			<a id="nldesign-compliance-report-markdown" download hidden><?php p($l->t('Download as Markdown')); ?></a>
		</section>
		<div class="thematiq-log-panel__cards">
			<section class="thematiq-card thematiq-panel__card thematiq-css-panel__aside-card" aria-labelledby="thematiq-contrast-covers-title">
				<h2 id="thematiq-contrast-covers-title" class="thematiq-panel__card-title"><?php p($l->t('What the report covers')); ?></h2>
				<p class="thematiq-panel__fact-hint"><?php p($l->t("Only the colour contrast of the theme's tokens. It is not a full WCAG audit: font size, focus, keyboard and app content fall outside it.")); ?></p>
			</section>
			<section class="thematiq-card thematiq-panel__card thematiq-css-panel__aside-card" aria-labelledby="thematiq-contrast-about-title">
				<h2 id="thematiq-contrast-about-title" class="thematiq-panel__card-title"><?php p($l->t('About this report')); ?></h2>
				<div class="thematiq-css-panel__rules thematiq-contrast-panel__about"></div>
			</section>
			<section class="thematiq-card thematiq-panel__card thematiq-css-panel__aside-card" aria-labelledby="thematiq-contrast-occ-title">
				<h2 id="thematiq-contrast-occ-title" class="thematiq-panel__card-title"><?php p($l->t('Also via occ')); ?></h2>
				<code class="thematiq-log-panel__code">occ thematiq:compliance-report</code>
				<p class="thematiq-panel__fact-hint"><?php p($l->t('Gives the same report, for example for an accessibility statement you update with every release.')); ?></p>
			</section>
		</div>
	</section>

	<!-- Configuration bundle (TqBundel), a panel of this page at #bundle (design.md): the
	     complete configuration as one JSON file, for DTAP promotion, and theme as code
	     (openspec/specs/config-portability, openspec/specs/theme-as-code). js/admin.js
	     downloads and uploads; js/admin-bundle-panel.js lists what is in it. -->
	<section class="thematiq-panel thematiq-bundle-panel nldesign-config-bundle" id="nldesign-config-bundle" data-thematiq-panel="bundle" aria-labelledby="thematiq-page-title" hidden>
		<div class="thematiq-panel__columns">
			<div class="thematiq-panel__main">
				<section class="thematiq-card thematiq-panel__card thematiq-fonts-panel__card" aria-labelledby="thematiq-bundle-title">
					<div class="thematiq-css-panel__head">
						<h2 id="thematiq-bundle-title" class="thematiq-panel__card-title thematiq-css-panel__title"><?php p($l->t('Configuration bundle')); ?></h2>
						<span class="thematiq-panel__fact-hint thematiq-css-panel__lead"><?php p($l->t('For DTAP: the same house style on every environment')); ?></span>
					</div>
					<p class="thematiq-bundle-panel__text"><?php p($l->t('Download or upload the complete thematiq configuration as a JSON file. That is how you carry the same house style from development to test, acceptance and production. This differs from downloading only the token overrides.')); ?></p>
					<div class="thematiq-bundle-panel__contents">
						<h3 class="thematiq-bundle-panel__subtitle"><?php p($l->t('What is in it')); ?></h3>
						<div class="thematiq-css-panel__rules thematiq-bundle-panel__rows"></div>
					</div>
					<button type="button" id="nldesign-config-bundle-download-btn" hidden><?php p($l->t('Download configuration')); ?></button>
					<input type="file" id="nldesign-config-bundle-input" accept=".json" aria-label="<?php p($l->t('Configuration bundle file to upload (JSON)')); ?>" hidden>
					<button type="button" id="nldesign-config-bundle-upload-btn" hidden><?php p($l->t('Upload configuration')); ?></button>
					<div id="nldesign-config-bundle-result" class="nldesign-import-result" role="status" aria-live="polite" style="display:none"></div>
				</section>
				<section class="thematiq-card thematiq-panel__card thematiq-fonts-panel__card" aria-labelledby="thematiq-bundle-code-title">
					<div class="thematiq-css-panel__head">
						<h2 id="thematiq-bundle-code-title" class="thematiq-panel__card-title thematiq-css-panel__title"><?php p($l->t('Theme as code')); ?></h2>
						<span class="thematiq-panel__fact-hint"><?php p($l->t('Set thematiq.config_source in config.php and the house style of this environment comes from the deployment configuration.')); ?></span>
					</div>
					<!-- Filled by js/admin-config-source.js when thematiq.config_source is set
					     (openspec/specs/theme-as-code/spec.md). -->
					<div id="nldesign-config-source" class="nldesign-config-source" role="status" aria-live="polite" hidden></div>
				</section>
			</div>
			<aside class="thematiq-panel__aside" aria-label="<?php p($l->t('Explanation')); ?>">
				<section class="thematiq-card thematiq-panel__card thematiq-css-panel__aside-card" aria-labelledby="thematiq-bundle-occ-title">
					<h2 id="thematiq-bundle-occ-title" class="thematiq-panel__card-title"><?php p($l->t('Also via occ')); ?></h2>
					<code class="thematiq-log-panel__code">occ thematiq:config:export &lt;file&gt;</code>
					<code class="thematiq-log-panel__code">occ thematiq:config:import &lt;file&gt;</code>
					<code class="thematiq-log-panel__code">occ thematiq:config:apply</code>
					<p class="thematiq-panel__fact-hint"><?php p($l->t('Handy in a deployment script. An import checks everything first and then applies everything at once, or nothing.')); ?></p>
				</section>
				<section class="thematiq-card thematiq-panel__card thematiq-css-panel__aside-card" aria-labelledby="thematiq-bundle-log-title">
					<h2 id="thematiq-bundle-log-title" class="thematiq-panel__card-title"><?php p($l->t('In the log')); ?></h2>
					<p class="thematiq-panel__fact-hint"><?php p($l->t('An import or applied package appears in the log as Configuration imported, with the revision.')); ?></p>
					<a href="#log" class="thematiq-bundle-panel__link"><?php p($l->t('Open log')); ?></a>
				</section>
			</aside>
		</div>
	</section>

	<!-- AI assistant (TqAiAssistent), a panel of this page at #assistant (design.md): the
	     approved mark (openspec/specs/assistant-approved-mark/spec.md). Filled and saved by
	     js/admin-assistant-mark.js; js/admin-assistant-panel.js shows it in a sample panel. -->
	<section class="thematiq-panel thematiq-assistant-panel nldesign-assistant-mark" id="nldesign-assistant-mark" data-thematiq-panel="assistant" aria-labelledby="thematiq-page-title" hidden>
		<div class="thematiq-panel__columns">
			<div class="thematiq-panel__main">
				<section class="thematiq-card thematiq-panel__card thematiq-fonts-panel__card" aria-labelledby="thematiq-assistant-title">
					<h2 id="thematiq-assistant-title" class="thematiq-panel__card-title thematiq-css-panel__title"><?php p($l->t('AI assistant')); ?></h2>
					<p class="thematiq-bundle-panel__text"><?php p($l->t('Show users which AI assistant your organisation has approved. The mark sits at the bottom of the assistant panel in Conduction apps. The mark informs honest users. It is not a security measure.')); ?></p>
					<div class="thematiq-css-panel__check">
						<input type="checkbox" id="nldesign-assistant-mark-enabled" class="checkbox">
						<label for="nldesign-assistant-mark-enabled"><?php p($l->t('Show the approval mark')); ?></label>
					</div>
					<div class="thematiq-fonts-panel__field">
						<label for="nldesign-assistant-mark-organisation"><?php p($l->t('Name of the organisation')); ?></label>
						<input type="text" id="nldesign-assistant-mark-organisation" maxlength="120" aria-describedby="thematiq-assistant-org-hint">
						<span id="thematiq-assistant-org-hint" class="thematiq-panel__fact-hint"><?php p($l->t('Leave empty: the name from the email footer.')); ?></span>
					</div>
					<div class="thematiq-fonts-panel__field">
						<label for="nldesign-assistant-mark-logo"><?php p($l->t('Address of the logo')); ?></label>
						<input type="text" id="nldesign-assistant-mark-logo" maxlength="500" aria-describedby="thematiq-assistant-logo-hint">
						<span id="thematiq-assistant-logo-hint" class="thematiq-panel__fact-hint"><?php p($l->t('An https address or a path on this server. Leave empty: the logo of the active house style.')); ?></span>
					</div>
					<div class="thematiq-assistant-panel__preview">
						<span class="thematiq-panel__fact-hint"><?php p($l->t('Preview')); ?></span>
						<div class="nldesign-assistant-mark-preview" id="nldesign-assistant-mark-preview" aria-live="polite"></div>
					</div>
					<div class="thematiq-css-panel__actions">
						<span id="nldesign-assistant-mark-feedback" class="thematiq-css-panel__feedback" role="status" aria-live="polite"></span>
						<span class="thematiq-preview__spacer"></span>
						<button type="button" id="nldesign-assistant-mark-save" class="thematiq-css-panel__save"><?php p($l->t('Save AI assistant settings')); ?></button>
					</div>
				</section>
				<section class="thematiq-card thematiq-panel__card thematiq-fonts-panel__card" aria-labelledby="thematiq-assistant-sample-title">
					<div class="thematiq-css-panel__head">
						<h2 id="thematiq-assistant-sample-title" class="thematiq-panel__card-title"><?php p($l->t('How an employee sees it')); ?></h2>
						<span class="thematiq-panel__fact-hint"><?php p($l->t('The assistant panel of a Conduction app, with the mark at the bottom.')); ?></span>
					</div>
					<div class="thematiq-assistant-panel__sample-row">
						<div class="thematiq-assistant-panel__sample" role="img" aria-label="<?php p($l->t('Preview of the assistant panel')); ?>">
							<div class="thematiq-assistant-panel__sample-head"><?php p($l->t('Assistant')); ?></div>
							<div class="thematiq-assistant-panel__sample-chat">
								<div class="thematiq-assistant-panel__bubble thematiq-assistant-panel__bubble--user"><span><?php p($l->t('You')); ?></span><?php p($l->t('Summarise the advice on the lighting of the cycle path.')); ?></div>
								<div class="thematiq-assistant-panel__bubble"><span><?php p($l->t('Assistant')); ?></span><?php p($l->t('The advice recommends 42 energy-saving street lights along the whole cycle path. The work takes about six weeks.')); ?></div>
							</div>
							<div class="thematiq-assistant-panel__sample-ask"><?php p($l->t('Ask a question')); ?></div>
							<div class="thematiq-assistant-panel__sample-foot"></div>
						</div>
						<div class="thematiq-assistant-panel__sample-text">
							<p><?php p($l->t('The mark sits in the footer of the assistant panel, in every Conduction app. With the logo and the name an employee knows which assistant the organisation has approved.')); ?></p>
							<p><?php p($l->t('If the mark is off, or thematiq is not installed, nothing is shown there.')); ?></p>
						</div>
					</div>
				</section>
			</div>
			<aside class="thematiq-panel__aside" aria-label="<?php p($l->t('Explanation')); ?>">
				<section class="thematiq-card thematiq-panel__card thematiq-css-panel__aside-card" aria-labelledby="thematiq-assistant-rules-title">
					<h2 id="thematiq-assistant-rules-title" class="thematiq-panel__card-title"><?php p($l->t('Defaults and rules')); ?></h2>
					<div class="thematiq-css-panel__rules">
						<div><span><?php p($l->t('Default')); ?></span><strong><?php p($l->t('off')); ?></strong></div>
						<div><span><?php p($l->t('Name empty')); ?></span><strong><?php p($l->t('name from the email footer')); ?></strong></div>
						<div><span><?php p($l->t('Logo empty')); ?></span><strong><?php p($l->t('logo of the house style')); ?></strong></div>
						<div><span><?php p($l->t('Logo address')); ?></span><strong><?php p($l->t('https or path on this server')); ?></strong></div>
						<div><span><?php p($l->t('Turn on without name')); ?></span><strong><?php p($l->t('not possible')); ?></strong></div>
					</div>
					<p class="thematiq-panel__fact-hint"><?php p($l->t('Without a name of your own and without a name in the email footer, the mark stays off. You then see why.')); ?></p>
				</section>
				<section class="thematiq-card thematiq-panel__card thematiq-css-panel__aside-card" aria-labelledby="thematiq-assistant-bundle-title">
					<h2 id="thematiq-assistant-bundle-title" class="thematiq-panel__card-title"><?php p($l->t('In the configuration bundle')); ?></h2>
					<p class="thematiq-panel__fact-hint"><?php p($l->t('On or off, the name and the logo travel in the configuration bundle, under assistantMark. So the mark is the same in test and production.')); ?></p>
					<a href="#bundle" class="thematiq-bundle-panel__link"><?php p($l->t('Go to the configuration bundle')); ?></a>
				</section>
			</aside>
		</div>
	</section>
	<?php // phpcs:enable Squiz.NamingConventions.ValidVariableName.NotCamelCaps,Generic.Files.LineLength ?>

	<!-- The pages that have no card of their own on the overview, as links to their panels (design.md). -->
	<nav class="thematiq-card thematiq-tools" aria-labelledby="thematiq-tools-title">
		<h2 id="thematiq-tools-title" class="thematiq-card__title"><?php p($l->t('More settings')); ?></h2>
		<ul class="thematiq-tools__list">
			<li><a href="#css"><?php p($l->t('Custom CSS')); ?></a></li>
			<li><a href="#fonts"><?php p($l->t('Typefaces')); ?></a></li>
			<li><a href="#contrast"><?php p($l->t('Contrast report')); ?></a></li>
			<li><a href="#assistant"><?php p($l->t('AI assistant')); ?></a></li>
			<li><a href="#bundle"><?php p($l->t('Configuration bundle')); ?></a></li>
			<li><a href="#log"><?php p($l->t('Log')); ?></a></li>
		</ul>
	</nav>



	<!-- House styles whose licence limits their use to their owner: the warning,
	     the licence text and the confirmation per set
	     (openspec/changes/archive/2026-11-22-token-set-licence-gate/specs/token-set-licences/spec.md). Filled by js/admin-set-licence.js. -->
	<div class="nldesign-set-licences" id="nldesign-set-licences" hidden>
		<h3><?php p($l->t('House styles of other organisations')); ?></h3>
		<div class="nldesign-set-licences-awaiting" id="nldesign-set-licences-awaiting" role="alert" hidden></div>
		<p class="settings-hint"><?php p($l->t('These house styles belong to the organisation they are named after. You can apply one after you confirm that you may use it.')); ?></p>
		<div id="nldesign-set-licences-list"></div>
	</div>

	<!-- Planned token set switches (openspec/specs/scheduled-switch).
	     admin.js fills the status, the list and the time zone hint. -->
	<div class="nldesign-scheduled-switches" id="nldesign-scheduled-switches">
		<h3><?php p($l->t('Planned switches')); ?></h3>
		<p class="settings-hint"><?php p($l->t('Plan a switch to another token set, for a campaign or a holiday look. With an end time the previous token set comes back by itself.')); ?></p>
		<p class="settings-hint" id="nldesign-scheduled-status" aria-live="polite"></p>
		<p class="nldesign-scheduled-cron-warning" id="nldesign-scheduled-cron-warning" role="status" hidden>
			<?php p($l->t('Background jobs run in AJAX mode, so a planned switch may start late. Choose Cron under Administration settings, Basic settings, Background jobs.')); ?>
		</p>
		<form id="nldesign-scheduled-form" class="nldesign-scheduled-form">
			<div class="nldesign-fields">
				<div class="nldesign-field">
					<label for="nldesign-scheduled-set"><?php p($l->t('Token set')); ?></label>
					<select id="nldesign-scheduled-set" name="tokenSet">
						<?php foreach ($_['tokenSets'] as $tokenSet): ?>
							<option value="<?php p($tokenSet['id']); ?>"><?php p($tokenSet['name']); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="nldesign-field">
					<label for="nldesign-scheduled-start"><?php p($l->t('Start')); ?></label>
					<input type="datetime-local" id="nldesign-scheduled-start" name="startAt" required>
				</div>
				<div class="nldesign-field">
					<label for="nldesign-scheduled-end"><?php p($l->t('End (optional)')); ?></label>
					<input type="datetime-local" id="nldesign-scheduled-end" name="endAt">
				</div>
			</div>
			<p class="settings-hint nldesign-field-hint" id="nldesign-scheduled-zone"></p>
			<!-- No option: a switch applies the set as the apply dialog does, the
			     Nextcloud logo and colours included. Without them a switch changed
			     the token set and nothing anyone could see. -->
			<p class="settings-hint nldesign-field-hint" id="nldesign-scheduled-applies">
				<?php p($l->t('The switch applies the token set as you would by hand, including the Nextcloud logo and colors it carries. At the end, the token set, logo and colors from before the switch come back.')); ?>
			</p>
			<div class="nldesign-form-actions">
				<button type="submit" class="button primary" id="nldesign-scheduled-submit"><?php p($l->t('Plan switch')); ?></button>
			</div>
		</form>
		<ul class="nldesign-scheduled-list" id="nldesign-scheduled-list"></ul>
	</div>

	<!-- Active icon pack — read-only indicator (theme-switchable iconography,
	     openspec/specs/icon-packs/spec.md). Reflects the currently PERSISTED
	     token set (not an unpublished dropdown selection); no write control
	     for the appconfig `icon_pack` override in this change. -->
	<div class="nldesign-icon-pack-indicator" id="nldesign-icon-pack-indicator">
		<h3><?php p($l->t('Active icon pack')); ?></h3>
		<p class="nldesign-icon-pack-value" id="nldesign-icon-pack-value">
			<?php if (empty($_['activeIconPacks'])): ?>
				<?php p($l->t('Nextcloud stock icons (no custom pack)')); ?>
			<?php else: ?>
				<?php p(implode(', ', $_['activeIconPacks'])); ?>
			<?php endif; ?>
		</p>
		<p class="settings-hint" id="nldesign-icon-pack-source">
			<?php if ($_['iconPackSource'] === 'override'): ?>
				<?php p($l->t('Source: admin override (occ config:app:set nldesign icon_pack)')); ?>
			<?php else: ?>
				<?php p($l->t('Source: the active design system')); ?>
			<?php endif; ?>
		</p>
		<p class="settings-hint">
			<?php p($l->t('This only switches the icon assets nldesign itself serves through imagePath. It does not replace Nextcloud\'s built-in core icons beyond what the active theme\'s CSS already restyles.')); ?>
		</p>
	</div>

	<!-- Marianne (French State typeface) — restricted, gated, off by default.
	     Only meaningful for the lasuite design system: server-rendered
	     hidden otherwise for the initial paint, and toggled live by
	     admin.js on token-set selection (same data-design-system option
	     attribute updateDesignSystemBadge() already reads). Notice text is
	     unmissable at this point-of-selection per
	     openspec/specs/marianne-font/spec.md. -->
	<div class="nldesign-marianne-gate" id="nldesign-marianne-gate"
		 style="<?php echo ($_['currentDesignSystem'] !== 'lasuite') ? 'display:none' : ''; ?>">
		<p class="nldesign-license-notice" id="nldesign-marianne-notice">
			<?php p($l->t('Marianne is the official typeface of the French State and is reserved for French State administrations. Enable it only if your organisation is a French State agency. Otherwise Inter is used.')); ?>
		</p>
		<div class="nldesign-option">
			<input type="checkbox"
				   name="nldesign-marianne-enabled"
				   id="nldesign-marianne-enabled"
				   class="checkbox"
				   <?php if ($_['marianneEnabled']): ?>checked<?php endif; ?>>
			<label for="nldesign-marianne-enabled">
				<?php p($l->t('Our organisation is a French State agency (administration de l\'État)')); ?>
			</label>
		</div>
	</div>

	<!-- Active theme preview ("proefdraaien") — only rendered for the
	     requesting admin's own active preview; publishing runs the existing
	     apply + theming-sync dialogs before calling the publish endpoint. -->
	<div class="nldesign-active-preview" id="nldesign-active-preview"
		 style="<?php echo ($_['activePreview'] === null) ? 'display:none' : ''; ?>">
		<p class="settings-hint" role="status">
			<?php p($l->t('Previewing "{name}" in your session only.', ['name' => ($_['activePreview']['name'] ?? '')])); ?>
		</p>
		<button type="button" id="nldesign-preview-publish-btn" class="button">
			<?php p($l->t('Publish')); ?>
		</button>
		<button type="button" id="nldesign-preview-discard-btn" class="button">
			<?php p($l->t('Discard')); ?>
		</button>
	</div>

	<!-- Custom token set upload (eigen huisstijl) -->
	<div class="nldesign-custom-token-sets" id="nldesign-custom-token-sets" style="margin-top:2em">
		<h3><?php p($l->t('Custom token sets')); ?></h3>
		<p class="settings-hint">
			<?php p($l->t('Add your own house style in one of two ways. Both end up in the list below and in the Design token set dropdown above.')); ?>
		</p>
		<!-- Two ways in, as tabs: one form is on screen at a time, so the two
		     "Token set name" fields can no longer be read as one form, and the
		     list below belongs to neither. admin.js switches the panels; the
		     second starts `hidden`, so the first paint shows the upload form. -->
		<div class="nldesign-create-tabs" role="tablist" aria-label="<?php p($l->t('Add a custom token set')); ?>">
			<button type="button" class="nldesign-create-tab active" role="tab" id="nldesign-create-tab-upload"
					aria-selected="true" aria-controls="nldesign-create-panel-upload">
				<?php p($l->t('Upload a file')); ?>
			</button>
			<button type="button" class="nldesign-create-tab" role="tab" id="nldesign-create-tab-colours"
					aria-selected="false" aria-controls="nldesign-brand-form" tabindex="-1">
				<?php p($l->t('Start from your colors')); ?>
			</button>
		</div>
		<div class="nldesign-create-panels">
			<div class="nldesign-create-panel" id="nldesign-create-panel-upload" role="tabpanel"
				 aria-labelledby="nldesign-create-tab-upload">
				<p class="settings-hint">
					<?php p($l->t('An NL Design CSS file (--nldesign-* variables) or a W3C Design Tokens JSON file.')); ?>
				</p>
				<div class="nldesign-fields">
					<div class="nldesign-field">
						<label for="nldesign-upload-name"><?php p($l->t('Token set name')); ?></label>
						<input type="text" id="nldesign-upload-name" class="nldesign-upload-name"
							   placeholder="<?php p($l->t('e.g. Gemeente Voorbeeld')); ?>"
							   maxlength="64">
					</div>
				</div>
				<!-- Named, even though it is hidden. The visible <button> below is
				     the trigger; this input only ever opens the file dialog via
				     .click(). A `display:none` control is out of the
				     accessibility tree WHILE hidden — but unlike an
				     `aria-hidden` one it can be exposed again by a single style
				     change from script or a user stylesheet, and at that moment
				     an unnamed file input is a real WCAG 4.1.2 failure. The name
				     costs nothing while hidden and is correct the instant it is
				     not. (ConductionNL/.github#273 declines to exempt this shape
				     for exactly that reason.) -->
				<input type="file" id="nldesign-upload-input" accept=".css,.json,.tokens.json"
					   aria-label="<?php p($l->t('Token set file to upload (NL Design CSS or W3C Design Tokens JSON)')); ?>"
					   style="display:none">
				<p class="nldesign-hint" id="nldesign-upload-figma-hint">
					<?php p($l->t('Upload from Figma: choose a Tokens Studio file or a design tokens file. The result lists what was imported and what was left out.')); ?>
				</p>
				<div class="nldesign-form-actions">
					<button type="button" id="nldesign-upload-btn" class="button primary">
						<?php p($l->t('Choose file and upload')); ?>
					</button>
				</div>
				<!-- A theme mostly arrives as text in a clipboard, not as a file
				     (nlds-theme-converter, openspec/changes/nlds-theme-converter).
				     The server converts it exactly as it converts an uploaded
				     file; admin.js sends it as `content`. -->
				<div class="nldesign-field nldesign-paste-field">
					<label for="nldesign-upload-content"><?php p($l->t('Or paste the contents of a theme file')); ?></label>
					<textarea id="nldesign-upload-content" rows="8" spellcheck="false"
							  aria-describedby="nldesign-upload-content-hint"
							  placeholder=".gemeente-theme { --gemeente-color-primary: #154273; }"></textarea>
					<p class="settings-hint" id="nldesign-upload-content-hint">
						<?php p($l->t('A design-tokens.css, a W3C Design Tokens JSON or a Style Dictionary tokens.json. You see what was used and what was left out.')); ?>
					</p>
				</div>
				<div class="nldesign-form-actions">
					<button type="button" id="nldesign-convert-btn" class="button">
						<?php p($l->t('Convert')); ?>
					</button>
				</div>
				<div id="nldesign-upload-result" class="nldesign-import-result" role="status" aria-live="polite" style="display:none"></div>
			</div>
			<!-- The simple brand form (openspec/specs/simple-brand-form/spec.md): a complete set from two colours and a logo. -->
			<div class="nldesign-create-panel nldesign-brand-form" id="nldesign-brand-form" role="tabpanel"
				 aria-labelledby="nldesign-create-tab-colours" hidden>
				<p class="settings-hint">
					<?php p($l->t('Enter a name, your primary color and your background color. Thematiq makes a complete token set from them. You can refine it later in the token editor.')); ?>
				</p>
				<div class="nldesign-fields">
					<div class="nldesign-field">
						<label for="nldesign-brand-name"><?php p($l->t('Token set name')); ?></label>
						<input type="text" id="nldesign-brand-name" maxlength="64"
							   placeholder="<?php p($l->t('e.g. Gemeente Voorbeeld')); ?>">
					</div>
					<div class="nldesign-field">
						<label for="nldesign-brand-primary"><?php p($l->t('Primary color')); ?></label>
						<input type="color" id="nldesign-brand-primary" class="nldesign-brand-colour" value="#154273">
					</div>
					<div class="nldesign-field">
						<label for="nldesign-brand-background"><?php p($l->t('Background color')); ?></label>
						<input type="color" id="nldesign-brand-background" class="nldesign-brand-colour" value="#ffffff">
					</div>
					<!-- The main button's own colour (openspec/specs/simple-brand-form). Off by
					     default: without it the main button wears the primary, as every set did. -->
					<div class="nldesign-field">
						<input type="checkbox" id="nldesign-brand-action-enabled" class="checkbox">
						<label for="nldesign-brand-action-enabled"><?php p($l->t('Give the main button its own color')); ?></label>
						<input type="color" id="nldesign-brand-action" class="nldesign-brand-colour" value="#1f883d" disabled
							   aria-label="<?php p($l->t('Main button color')); ?>">
					</div>
					<div class="nldesign-field">
						<!-- No `for`: a label for the button would replace its name with
						     "Logo (optional)", so "Choose logo" would not be in it (WCAG
						     2.5.3). The button names itself from both instead. -->
						<label id="nldesign-brand-logo-label"><?php p($l->t('Logo (optional)')); ?></label>
						<!-- Nextcloud's own button instead of the browser's file control,
						     which draws in the browser's language and style. The input is
						     named for the same reason as the upload's above. -->
						<div class="nldesign-file-pick">
							<input type="file" id="nldesign-brand-logo" accept=".svg,.png,.jpg,.gif,.webp" hidden
								   aria-label="<?php p($l->t('Logo file (SVG, PNG, JPG, GIF or WebP)')); ?>">
							<button type="button" class="button" id="nldesign-brand-logo-btn"
									aria-labelledby="nldesign-brand-logo-label nldesign-brand-logo-btn"><?php p($l->t('Choose logo')); ?></button>
							<span class="nldesign-file-pick__name" id="nldesign-brand-logo-name"><?php p($l->t('No file chosen')); ?></span>
						</div>
					</div>
				</div>
				<div class="nldesign-brand-preview" id="nldesign-brand-preview" aria-hidden="true">
					<span class="nldesign-brand-sample" id="nldesign-brand-sample"><?php p($l->t('Primary button')); ?></span>
					<span class="nldesign-brand-sample" id="nldesign-brand-sample-hover"><?php p($l->t('Hover')); ?></span>
				</div>
				<p class="nldesign-brand-contrast" id="nldesign-brand-contrast" role="status" aria-live="polite"></p>
				<div class="nldesign-form-actions">
					<button type="button" id="nldesign-brand-save" class="button primary">
						<?php p($l->t('Create house style')); ?>
					</button>
				</div>
				<div id="nldesign-brand-result" class="nldesign-import-result" role="status" aria-live="polite" style="display:none"></div>
			</div>
		</div>
		<h4 class="nldesign-custom-set-heading"><?php p($l->t('Your custom token sets')); ?></h4>
		<div id="nldesign-custom-set-list" class="nldesign-custom-set-list" role="group"
			 aria-label="<?php p($l->t('Custom token sets')); ?>">
			<p class="settings-hint"><?php p($l->t('Loading custom token sets…')); ?></p>
		</div>
	</div>

	<div class="nldesign-preview" id="nldesign-preview">
		<div class="nldesign-preview-head">
			<h3><?php p($l->t('Preview')); ?></h3>
			<div class="nldesign-preview-switch" role="tablist" aria-label="<?php p($l->t('Preview view')); ?>">
				<button type="button" class="nldesign-preview-switch-btn active" data-view="app" role="tab" id="nldesign-preview-tab-app" aria-selected="true"><?php p($l->t('App')); ?></button>
				<button type="button" class="nldesign-preview-switch-btn" data-view="login" role="tab" id="nldesign-preview-tab-login" aria-selected="false" tabindex="-1"><?php p($l->t('Login')); ?></button>
			</div>
		</div>

		<!-- App-shell preview: it mirrors the REAL Nextcloud shell, because that is
		     what an admin compares it against.
		       - the header bar spans the full width and sits on the page
		         background, with the app menu on the left and the account
		         glyphs on the right;
		       - the content CONTAINER is inset from the page background and
		         clips the navigation and the app content into one rounded
		         rectangle (Nextcloud's `#content`, `--body-container-radius`);
		       - navigation entries are full-width pills carrying an icon and a
		         label, grouped under captions, and the selected one is FILLED
		         with the primary colour and labelled in its paired text colour
		         (`--border-radius-pill`, `--color-primary-element`);
		       - the app sidebar is a real panel with a heading and a close
		         control, not a second strip of grey lines.
		     Captions and body text are drawn as bars rather than words on
		     purpose: the shell is a scale model, and only the roles that carry a
		     token need to be legible. -->
		<div class="nldesign-preview-stage" data-view="app">
			<div class="nl-mini">
				<div class="nl-mini__navbar">
					<span class="nl-mini__logo"></span>
					<span class="nl-mini__navitem nl-mini__navitem--active"></span>
					<span class="nl-mini__navitem"></span>
					<span class="nl-mini__navitem"></span>
					<span class="nl-mini__navspacer"></span>
					<span class="nl-mini__navglyph"></span>
					<span class="nl-mini__navglyph"></span>
					<span class="nl-mini__avatar"><i class="nl-mini__avatar-status"></i></span>
				</div>
				<div class="nl-mini__body">
					<nav class="nl-mini__menu">
						<span class="nl-mini__caption"></span>
						<span class="nl-mini__menuitem nl-mini__menuitem--active"><i class="nl-mini__menuicon"></i><?php p($l->t('Dashboard')); ?></span>
						<span class="nl-mini__menuitem"><i class="nl-mini__menuicon"></i><?php p($l->t('Orders')); ?></span>
						<span class="nl-mini__menuitem"><i class="nl-mini__menuicon"></i><?php p($l->t('Reports')); ?></span>
						<span class="nl-mini__caption"></span>
						<span class="nl-mini__menuitem"><i class="nl-mini__menuicon"></i><?php p($l->t('Settings')); ?></span>
					</nav>
					<main class="nl-mini__content">
						<!-- Breadcrumbs: a link crumb, a hovered one, and the
						     current one, split by separators. -->
						<div class="nl-mini__crumbs">
							<span class="nl-mini__crumb"><?php p($l->t('Home')); ?></span>
							<span class="nl-mini__crumb-sep">›</span>
							<span class="nl-mini__crumb nl-mini__crumb--hover"><?php p($l->t('Orders')); ?></span>
							<span class="nl-mini__crumb-sep">›</span>
							<span class="nl-mini__crumb nl-mini__crumb--current">2026</span>
						</div>
						<div class="nl-mini__widget">
							<div class="nl-mini__widget-head"><?php p($l->t('Orders')); ?><span class="nl-mini__star" aria-hidden="true">★</span></div>
							<!-- A table with its header labels, row rules, a zebra
							     row and a hovered row. -->
							<table class="nl-mini__table">
								<thead><tr><th scope="col"><?php p($l->t('Name')); ?></th><th scope="col"><?php p($l->t('Date')); ?></th><th scope="col"><?php p($l->t('Status')); ?></th></tr></thead>
								<tbody>
									<tr><td></td><td></td><td><span class="nl-mini__pill nl-mini__pill--primary"></span></td></tr>
									<tr class="is-zebra"><td></td><td></td><td><span class="nl-mini__pill nl-mini__pill--warning"></span></td></tr>
									<tr class="is-hover"><td></td><td></td><td><span class="nl-mini__pill nl-mini__pill--info"></span></td></tr>
								</tbody>
							</table>
							<!-- A form row: a text field, a checked checkbox and a
							     progress bar. -->
							<div class="nl-mini__form">
								<span class="nl-mini__input"><span class="nl-mini__input-text"></span></span>
								<span class="nl-mini__check"></span>
								<span class="nl-mini__progress"><i class="nl-mini__progress-fill"></i></span>
							</div>
						</div>
					</main>
					<aside class="nl-mini__sidebar">
						<div class="nl-mini__sidebar-head"><?php p($l->t('Details')); ?><span class="nl-mini__sidebar-close"></span></div>
						<!-- The tab strip: its rule is the sidebar's divider, the
						     open tab's underline its active colour. -->
						<span class="nl-mini__sidebar-tabs"><span class="nl-mini__sidebar-tab nl-mini__sidebar-tab--active"></span><span class="nl-mini__sidebar-tab"></span></span>
						<span class="nl-mini__line"></span>
						<span class="nl-mini__line nl-mini__line--short"></span>
						<span class="nl-mini__line"></span>
					</aside>
				</div>
				<div class="nl-mini__modal-overlay">
					<div class="nl-mini__modal">
						<div class="nl-mini__modal-head"><?php p($l->t('Dialog')); ?><span class="nl-mini__counter">12</span></div>
						<div class="nl-mini__modal-body">
							<!-- Typography: a heading, body text with a link, then the
							     muted and status texts, each painted from its own token
							     (css/admin.css). Plain elements on purpose: a real h3 or
							     p takes the design system's full-page font sizes. -->
							<div class="nl-mini__type">
								<div class="nl-mini__type-heading"><?php p($l->t('Heading')); ?></div>
								<div class="nl-mini__type-paragraph"><?php p($l->t('Body text of a page, with')); ?> <a href="#" class="nl-mini__type-link"><?php p($l->t('a link')); ?></a>.</div>
								<div class="nl-mini__type-row">
									<span class="nl-mini__type-muted"><?php p($l->t('Muted')); ?></span>
									<span class="nl-mini__type-light"><?php p($l->t('Light')); ?></span>
									<span class="nl-mini__type-lighter"><?php p($l->t('Lighter')); ?></span>
								</div>
								<div class="nl-mini__type-row">
									<span class="nl-mini__type-error"><?php p($l->t('Error')); ?></span>
									<span class="nl-mini__type-warning"><?php p($l->t('Warning')); ?></span>
									<span class="nl-mini__type-success"><?php p($l->t('Success')); ?></span>
								</div>
							</div>
							<!-- One note card of each type, each painted from its own
							     note card tokens. -->
							<div class="nl-mini__notes">
								<span class="nl-mini__note nl-mini__note--info"><i class="nl-mini__note-icon"></i><span class="nl-mini__note-text"></span></span>
								<span class="nl-mini__note nl-mini__note--warning"><i class="nl-mini__note-icon"></i><span class="nl-mini__note-text"></span></span>
								<span class="nl-mini__note nl-mini__note--error"><i class="nl-mini__note-icon"></i><span class="nl-mini__note-text"></span></span>
								<span class="nl-mini__note nl-mini__note--success"><i class="nl-mini__note-icon"></i><span class="nl-mini__note-text"></span></span>
							</div>
						</div>
						<div class="nl-mini__modal-actions">
							<!-- The five NcButton variants, each painted from its own
							     component tokens (css/admin.css), so the dialog is a
							     small overview of every button an admin can theme. -->
							<button type="button" class="nl-btn nl-btn--primary"><?php p($l->t('Primary')); ?></button>
							<button type="button" class="nl-btn nl-btn--secondary"><?php p($l->t('Secondary')); ?></button>
							<button type="button" class="nl-btn nl-btn--tertiary"><?php p($l->t('Tertiary')); ?></button>
							<button type="button" class="nl-btn nl-btn--error"><?php p($l->t('Danger')); ?></button>
							<button type="button" class="nl-btn nl-btn--success"><?php p($l->t('Success')); ?></button>
						</div>
					</div>
				</div>
				<!-- Toasts, one of each type, above the dialog as Nextcloud
				     shows them. -->
				<div class="nl-mini__toasts" aria-hidden="true">
					<span class="nl-mini__toast nl-mini__toast--success"><span class="nl-mini__toast-text"></span></span>
					<span class="nl-mini__toast nl-mini__toast--error"><span class="nl-mini__toast-text"></span></span>
					<span class="nl-mini__toast nl-mini__toast--warning"><span class="nl-mini__toast-text"></span></span>
					<span class="nl-mini__toast nl-mini__toast--info"><span class="nl-mini__toast-text"></span></span>
				</div>
			</div>
		</div>

		<!-- Login-page preview -->
		<div class="nldesign-preview-stage" data-view="login" hidden>
			<div class="nl-login">
				<div class="nl-login__logo"></div>
				<div class="nl-login__card">
					<span class="nl-login__field"><span class="nl-login__placeholder"></span></span>
					<span class="nl-login__field"><span class="nl-login__placeholder"></span></span>
					<button type="button" class="nl-btn nl-btn--primary nl-login__submit"><?php p($l->t('Log in')); ?></button>
				</div>
				<div class="nl-login__slogan"><?php p($l->t('A safe home for all your data')); ?></div>
			</div>
		</div>
	</div>

	<!-- Token Editor Panel — mounted by admin.js -->
	<div id="nldesign-token-editor" style="margin-top:2em">
		<p class="settings-hint"><?php p($l->t('Loading token editor…')); ?></p>
	</div>

	<!-- Your own tokens and the deprecations list (authoring-token-lifecycle), mounted by ownTokens.js. -->
	<div class="nldesign-own-tokens" id="nldesign-own-tokens" style="margin-top:2em">
		<h3><?php p($l->t('Your own tokens')); ?></h3>
		<p class="settings-hint">
			<?php p($l->t('Add a token of your own, such as a brand accent. Its name starts with --nldesign-org-. Custom CSS and apps can read it at once, for example var(--nldesign-org-brand-accent).')); ?>
		</p>
		<button type="button" id="nldesign-own-token-add" class="button"><?php p($l->t('Add a token')); ?></button>
		<ul class="nldesign-own-token-list" id="nldesign-own-token-list" aria-label="<?php p($l->t('Your own tokens')); ?>"></ul>
		<h4 id="nldesign-deprecations-heading"><?php p($l->t('Deprecated tokens')); ?></h4>
		<p class="settings-hint">
			<?php p($l->t('Tell the teams that use the house style that a token is going away: which token replaces it, and when it may be removed. A deprecation never changes a value. Apps read the list from /apps/thematiq/api/token-deprecations.')); ?>
		</p>
		<button type="button" id="nldesign-deprecation-add" class="button"><?php p($l->t('Deprecate a token')); ?></button>
		<ul class="nldesign-deprecations" id="nldesign-deprecation-list" aria-labelledby="nldesign-deprecations-heading"></ul>
		<p id="nldesign-own-tokens-status" role="status" aria-live="polite"></p>
	</div>

	<?php // phpcs:disable Generic.Files.LineLength -- the dialogs keep the board's markup on one line per element. ?>
	<!-- Add a token and Deprecate a token, drawn as the TqTokenToevoegen and
	     TqTokenUitfaseren boards in the admin dialog frame (css/admin-dialog.css).
	     js/ownTokens.js opens, fills and saves them. -->
	<dialog id="nldesign-own-token-dialog" class="nldesign-own-dialog thematiq-admin-dialog" aria-labelledby="nldesign-own-token-dialog-title" style="max-width: min(640px, calc(100vw - 32px))">
		<form method="dialog" class="thematiq-admin-dialog__form">
			<div class="thematiq-admin-dialog__head">
				<div class="thematiq-admin-dialog__heading">
					<span class="thematiq-admin-dialog__eyebrow"><?php p($l->t('Own tokens')); ?></span>
					<h2 id="nldesign-own-token-dialog-title" class="thematiq-admin-dialog__title nldesign-dialog-title"><?php p($l->t('Add a token')); ?></h2>
				</div>
				<button type="button" class="thematiq-admin-dialog__close nldesign-dialog-cancel" aria-label="<?php p($l->t('Close')); ?>"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z"/></svg></button>
			</div>
			<div class="thematiq-admin-dialog__body">
				<div class="thematiq-admin-dialog__field">
					<label for="nldesign-own-token-slug"><?php p($l->t('Name')); ?></label>
					<div class="thematiq-admin-dialog__affix">
						<span class="nldesign-own-token-prefix" aria-hidden="true">--nldesign-org-</span>
						<input type="text" id="nldesign-own-token-slug" name="slug" required maxlength="48" pattern="[a-z0-9]+(-[a-z0-9]+)*" aria-describedby="nldesign-own-token-slug-hint">
					</div>
					<span id="nldesign-own-token-slug-hint" class="thematiq-admin-dialog__hint"><?php p($l->t('Lowercase letters, digits and single dashes, at most 48 characters.')); ?></span>
				</div>
				<div class="thematiq-admin-dialog__grid">
					<div class="thematiq-admin-dialog__field">
						<label for="nldesign-own-token-label"><?php p($l->t('Label')); ?></label>
						<input type="text" id="nldesign-own-token-label" name="label" required maxlength="80">
					</div>
					<div class="thematiq-admin-dialog__field">
						<label for="nldesign-own-token-type"><?php p($l->t('Type')); ?></label>
						<select id="nldesign-own-token-type" name="type">
							<option value="color"><?php p($l->t('Colour')); ?></option>
							<option value="text"><?php p($l->t('Text')); ?></option>
							<option value="duration"><?php p($l->t('Duration')); ?></option>
							<option value="easing"><?php p($l->t('Easing')); ?></option>
						</select>
					</div>
				</div>
				<div class="thematiq-admin-dialog__grid">
					<div class="thematiq-admin-dialog__field">
						<label for="nldesign-own-token-value"><?php p($l->t('Value')); ?></label>
						<input type="text" id="nldesign-own-token-value" name="value" required aria-describedby="nldesign-own-token-value-hint">
						<span id="nldesign-own-token-value-hint" class="thematiq-admin-dialog__hint nldesign-own-token-colour-hint"><?php p($l->t('A colour, such as #EAF0F7 or rgb(234 240 247).')); ?></span>
					</div>
					<div class="thematiq-admin-dialog__field nldesign-own-token-dark">
						<label for="nldesign-own-token-dark"><?php p($l->t('Dark value')); ?> <span class="thematiq-admin-dialog__optional"><?php p($l->t('(optional)')); ?></span></label>
						<input type="text" id="nldesign-own-token-dark" name="darkValue" aria-describedby="nldesign-own-token-dark-hint">
						<span id="nldesign-own-token-dark-hint" class="thematiq-admin-dialog__hint"><?php p($l->t('The value for the dark theme.')); ?></span>
					</div>
				</div>
				<div class="thematiq-admin-dialog__field">
					<label for="nldesign-own-token-description"><?php p($l->t('Description')); ?> <span class="thematiq-admin-dialog__optional"><?php p($l->t('(optional)')); ?></span></label>
					<input type="text" id="nldesign-own-token-description" name="description" maxlength="500" aria-describedby="nldesign-own-token-description-hint">
					<span id="nldesign-own-token-description-hint" class="thematiq-admin-dialog__hint"><?php p($l->t('At most 500 characters. Say what the token is for.')); ?></span>
				</div>
				<div class="thematiq-admin-dialog__usage">
					<span><?php p($l->t('How to use it')); ?></span>
					<code class="nldesign-own-token-usage">background: var(--nldesign-org-…);</code>
				</div>
				<p class="nldesign-dialog-error thematiq-admin-dialog__error" role="alert"></p>
			</div>
			<div class="thematiq-admin-dialog__foot">
				<button type="button" class="thematiq-admin-dialog__button thematiq-admin-dialog__button--secondary nldesign-dialog-cancel"><?php p($l->t('Cancel')); ?></button>
				<button type="submit" class="thematiq-admin-dialog__button thematiq-admin-dialog__button--primary"><?php p($l->t('Save token')); ?></button>
			</div>
		</form>
	</dialog>

	<dialog id="nldesign-deprecation-dialog" class="nldesign-own-dialog thematiq-admin-dialog" aria-labelledby="nldesign-deprecation-dialog-title" style="max-width: min(640px, calc(100vw - 32px))">
		<form method="dialog" class="thematiq-admin-dialog__form">
			<div class="thematiq-admin-dialog__head">
				<div class="thematiq-admin-dialog__heading">
					<span class="thematiq-admin-dialog__eyebrow"><?php p($l->t('Deprecated tokens')); ?></span>
					<h2 id="nldesign-deprecation-dialog-title" class="thematiq-admin-dialog__title"><?php p($l->t('Deprecate a token')); ?></h2>
				</div>
				<button type="button" class="thematiq-admin-dialog__close nldesign-dialog-cancel" aria-label="<?php p($l->t('Close')); ?>"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z"/></svg></button>
			</div>
			<div class="thematiq-admin-dialog__body">
				<div class="thematiq-admin-dialog__field">
					<label for="nldesign-deprecation-token"><?php p($l->t('Token')); ?></label>
					<input type="text" id="nldesign-deprecation-token" name="token" required placeholder="--nldesign-" aria-describedby="nldesign-deprecation-token-hint">
					<span id="nldesign-deprecation-token-hint" class="thematiq-admin-dialog__hint"><?php p($l->t('The full name of the token that goes away.')); ?></span>
				</div>
				<div class="thematiq-admin-dialog__field">
					<label for="nldesign-deprecation-severity"><?php p($l->t('Severity')); ?></label>
					<select id="nldesign-deprecation-severity" name="severity">
						<option value="warning" selected><?php p($l->t('Warning')); ?></option>
						<option value="info"><?php p($l->t('Info')); ?></option>
						<option value="critical"><?php p($l->t('Critical')); ?></option>
					</select>
				</div>
				<div class="thematiq-admin-dialog__field">
					<label for="nldesign-deprecation-replacement"><?php p($l->t('Replacement token')); ?> <span class="thematiq-admin-dialog__optional"><?php p($l->t('(optional)')); ?></span></label>
					<input type="text" id="nldesign-deprecation-replacement" name="replacement">
				</div>
				<div class="thematiq-admin-dialog__field">
					<label for="nldesign-deprecation-date"><?php p($l->t('Gone from')); ?> <span class="thematiq-admin-dialog__optional"><?php p($l->t('(optional)')); ?></span></label>
					<input type="date" id="nldesign-deprecation-date" name="removalDate">
				</div>
				<div class="thematiq-admin-dialog__field">
					<label for="nldesign-deprecation-message"><?php p($l->t('Message')); ?> <span class="thematiq-admin-dialog__optional"><?php p($l->t('(optional)')); ?></span></label>
					<input type="text" id="nldesign-deprecation-message" name="message" maxlength="500" aria-describedby="nldesign-deprecation-message-hint">
					<span id="nldesign-deprecation-message-hint" class="thematiq-admin-dialog__hint"><?php p($l->t('At most 500 characters.')); ?></span>
				</div>
				<div class="thematiq-admin-dialog__notice thematiq-admin-dialog__notice--neutral">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M11,9H13V7H11M12,20C7.59,20 4,16.41 4,12C4,7.59 7.59,4 12,4C16.41,4 20,7.59 20,12C20,16.41 16.41,20 12,20M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M11,17H13V11H11V17Z"/></svg>
					<span><?php p($l->t('Deprecating never changes a value. Apps read the list via /apps/thematiq/api/token-deprecations and can warn their developers.')); ?></span>
				</div>
				<p class="nldesign-dialog-error thematiq-admin-dialog__error" role="alert"></p>
			</div>
			<div class="thematiq-admin-dialog__foot">
				<button type="button" class="thematiq-admin-dialog__button thematiq-admin-dialog__button--secondary nldesign-dialog-cancel"><?php p($l->t('Cancel')); ?></button>
				<button type="submit" class="thematiq-admin-dialog__button thematiq-admin-dialog__button--primary"><?php p($l->t('Save deprecation')); ?></button>
			</div>
		</form>
	</dialog>
	<?php // phpcs:enable Generic.Files.LineLength ?>

	<!-- Theming per app — exclude individual apps from nldesign theming -->
	<div class="nldesign-app-theming" id="nldesign-app-theming" style="margin-top:2em">
		<h3><?php p($l->t('Theming per app')); ?></h3>
		<p class="settings-hint">
			<?php p($l->t('Choose which apps the NL Design theme applies to. Unchecking an app makes its pages render with stock Nextcloud styling, including the header on those pages.')); ?>
		</p>
		<div id="nldesign-app-theming-list" class="nldesign-app-theming-list" role="group"
			 aria-label="<?php p($l->t('Theming per app')); ?>">
			<p class="settings-hint"><?php p($l->t('Loading apps…')); ?></p>
		</div>
		<button type="button" id="nldesign-app-theming-save" class="button">
			<?php p($l->t('Save app theming')); ?>
		</button>
		<span id="nldesign-app-theming-feedback" class="nldesign-app-theming-feedback" role="status" aria-live="polite"></span>
	</div>

	<!-- The workspace's mobile logo and touch icon (openspec/changes/mobile-logo-and-touch-icon).
	     Filled by js/admin-workspace-icons.js. -->
	<div class="nldesign-workspace-icons" id="nldesign-workspace-icons" style="margin-top:2em">
		<h3><?php p($l->t('Mobile logo and home screen icon')); ?></h3>
		<p class="settings-hint">
			<?php p($l->t('The header shows the mobile logo on screens narrower than 1024 pixels. A phone shows the home screen icon when someone adds the workspace to its home screen. Without them Nextcloud uses the logo and the favicon. The favicon in the browser tab does not change.')); ?>
		</p>
		<div id="nldesign-workspace-icons-list" class="nldesign-workspace-icons-list"></div>
		<span id="nldesign-workspace-icons-feedback" role="status" aria-live="polite"></span>
	</div>

	<!-- The external apps of the apps menu live in the menu settings
	     (apps-menu-create-pins-settings); this block only points there. -->
	<div class="nldesign-external-apps" id="nldesign-external-apps" style="margin-top:2em">
		<h3><?php p($l->t('External apps in the apps menu')); ?></h3>
		<p class="settings-hint">
			<?php p($l->t('Add, change or remove external apps in the apps menu: Menu settings, Menu items.')); ?>
		</p>
	</div>

	<!-- Brand per app: an app's own token set and logos
	     (openspec/specs/per-app-theming/spec.md). Filled by js/admin-app-brands.js. -->
	<div class="nldesign-app-brands" id="nldesign-app-brands" style="margin-top:2em">
		<h3><?php p($l->t('Brand per app')); ?></h3>
		<p class="settings-hint">
			<?php p($l->t('Give an app its own house style and logo, for example a knowledge base or a participation platform. On that app\'s pages the brand replaces the house style for everyone. Other pages stay as they are.')); ?>
			<?php p($l->t('The app\'s name stays as Nextcloud shows it: the app menu and page titles come from Nextcloud.')); ?>
		</p>
		<div id="nldesign-app-brands-list" class="nldesign-app-brands-list"></div>
		<p>
			<label for="nldesign-app-brands-app"><?php p($l->t('App')); ?></label>
			<select id="nldesign-app-brands-app"></select>
			<label for="nldesign-app-brands-set"><?php p($l->t('Token set')); ?></label>
			<select id="nldesign-app-brands-set"></select>
			<button type="button" class="button primary" id="nldesign-app-brands-add"><?php p($l->t('Save brand')); ?></button>
		</p>
		<span id="nldesign-app-brands-feedback" role="status" aria-live="polite"></span>
	</div>

	<!-- Group theming — map Nextcloud groups to token sets for shared-instance
	     multi-tenant huisstijl (openspec/specs/per-group-theming/spec.md).
	     Row order IS priority order; keyboard-operable move-up/move-down
	     buttons instead of drag-and-drop. -->
	<div class="nldesign-group-theming" id="nldesign-group-theming" style="margin-top:2em">
		<h3><?php p($l->t('Group theming')); ?></h3>
		<p class="settings-hint">
			<?php p($l->t('Map Nextcloud groups to token sets so different gemeenten sharing one instance each see their own house style. Row order is priority order: for a user in multiple mapped groups, the first matching row wins.')); ?>
		</p>
		<p class="settings-hint">
			<?php p($l->t('Logo, mail templates, and other Nextcloud core branding always follow the instance default token set above — they are not per-group. Only this token-set stylesheet layer differs per group.')); ?>
		</p>
		<p class="settings-hint">
			<?php p($l->t('Tick Subadmins choose to let the subadmins of a group pick its house style from the token sets you allow. They choose under Personal settings, Appearance and accessibility.')); ?>
		</p>
		<div id="nldesign-group-theming-list" class="nldesign-group-theming-list" role="group"
			 aria-label="<?php p($l->t('Group theming')); ?>">
			<p class="settings-hint"><?php p($l->t('Loading group mappings…')); ?></p>
		</div>
		<button type="button" id="nldesign-group-theming-add" class="button">
			<?php p($l->t('Add mapping')); ?>
		</button>
		<button type="button" id="nldesign-group-theming-save" class="button primary">
			<?php p($l->t('Save group theming')); ?>
		</button>
		<span id="nldesign-group-theming-feedback" class="nldesign-group-theming-feedback" role="status" aria-live="polite"></span>
	</div>

	<!-- Email template theming — mail_template_class toggle + compliance footer -->
	<div class="nldesign-email-theming" id="nldesign-email-theming" style="margin-top:2em"
		 data-state="<?php p($_['emailThemingState']['state']); ?>"
		 data-config-read-only="<?php p($_['emailThemingState']['configReadOnly'] ? '1' : '0'); ?>"
		 data-foreign-class="<?php p($_['emailThemingState']['foreignClass'] ?? ''); ?>">
		<h3><?php p($l->t('Email template')); ?></h3>
		<p class="settings-hint">
			<?php p($l->t('Brand password-reset, share-notification, and other system emails with the active token set\'s color and logo. If nldesign is later disabled, Nextcloud automatically falls back to the stock email template — mail is never blocked by this setting.')); ?>
		</p>

		<?php if ($_['emailThemingState']['state'] === 'foreign'): ?>
			<p class="nldesign-email-foreign-note" role="alert">
				<?php p($l->t('A different mail template class is already configured ({class}); nldesign will not overwrite it.', ['class' => $_['emailThemingState']['foreignClass']])); ?>
			</p>
		<?php endif; ?>

		<div class="nldesign-option">
			<input type="checkbox"
				   name="nldesign-email-theming-enabled"
				   id="nldesign-email-theming-enabled"
				   class="checkbox"
				   <?php if ($_['emailThemingState']['state'] === 'enabled'): ?>checked<?php endif; ?>
				   <?php if ($_['emailThemingState']['state'] === 'foreign'): ?>disabled<?php endif; ?>>
			<label for="nldesign-email-theming-enabled">
				<?php p($l->t('Use NL Design email template')); ?>
			</label>
		</div>

		<div class="nldesign-email-footer-fields">
			<!-- autocomplete="off" on all three: these are INSTANCE-WIDE
			     configuration values (the organisation shown in every
			     outgoing mail, and that organisation's public statement
			     URLs), not personal details of the admin filling the form.
			     WCAG 2.2 SC 1.3.5 asks for an autocomplete token when a
			     field collects information ABOUT THE USER; none of these
			     do, so declaring the purpose as "off" is the accurate
			     answer, and it stops a browser offering the admin's own
			     profile data as the value for a setting that applies to
			     everyone on the instance. -->
			<label for="nldesign-email-footer-org-name"><?php p($l->t('Organization name')); ?></label>
			<input type="text" id="nldesign-email-footer-org-name" class="nldesign-email-footer-input"
				   autocomplete="off"
				   value="<?php p($_['emailFooterConfig']['orgName']); ?>"
				   placeholder="<?php p($l->t('e.g. Gemeente Voorbeeld')); ?>" maxlength="2048">

			<label for="nldesign-email-footer-accessibility-url"><?php p($l->t('Accessibility statement URL')); ?></label>
			<input type="url" id="nldesign-email-footer-accessibility-url" class="nldesign-email-footer-input"
				   autocomplete="off"
				   value="<?php p($_['emailFooterConfig']['accessibilityUrl']); ?>"
				   placeholder="https://example.org/toegankelijkheidsverklaring" maxlength="2048">

			<label for="nldesign-email-footer-privacy-url"><?php p($l->t('Privacy statement URL')); ?></label>
			<input type="url" id="nldesign-email-footer-privacy-url" class="nldesign-email-footer-input"
				   autocomplete="off"
				   value="<?php p($_['emailFooterConfig']['privacyUrl']); ?>"
				   placeholder="https://example.org/privacy" maxlength="2048">
		</div>

		<button type="button" id="nldesign-email-theming-save" class="button">
			<?php p($l->t('Save email template settings')); ?>
		</button>
		<span id="nldesign-email-theming-feedback" class="nldesign-email-theming-feedback" role="status" aria-live="polite"></span>

		<div class="nldesign-email-occ-hint" id="nldesign-email-occ-hint" role="alert" style="display:none">
			<p><?php p($l->t('config.php is read-only. Run one of the following commands manually to enable or disable the email template:')); ?></p>
			<p><code id="nldesign-email-occ-enable"><?php p($_['occEnableCommand']); ?></code></p>
			<p><code id="nldesign-email-occ-disable"><?php p($_['occDisableCommand']); ?></code></p>
		</div>
	</div>

	<!-- Documents: the document house style profile that fleet apps read
	     for letters and PDF exports (openspec/specs/document-house-style/spec.md).
	     Filled and saved by js/admin-documents.js. -->
	<div class="nldesign-documents" id="nldesign-documents" style="margin-top:2em">
		<h3><?php p($l->t('Documents')); ?></h3>
		<p class="settings-hint">
			<?php p($l->t('Apps that generate letters and PDF exports use these values, so documents follow the house style. Without uploads they use the house style logo and the email footer.')); ?>
		</p>
		<!-- Nextcloud's own button instead of the browser's file control, which
		     draws in the browser's language and style; see the brand logo above. -->
		<div class="nldesign-documents-upload">
			<label id="nldesign-documents-logo-label" for="nldesign-documents-logo"><?php p($l->t('Document logo (PNG, JPEG, WebP or SVG, at most 2 MB)')); ?></label>
			<div class="nldesign-file-pick">
				<input type="file" id="nldesign-documents-logo" accept="image/png,image/jpeg,image/webp,image/svg+xml" hidden>
				<button type="button" class="button" id="nldesign-documents-logo-btn"
						aria-labelledby="nldesign-documents-logo-label nldesign-documents-logo-btn"><?php p($l->t('Choose image')); ?></button>
				<span class="nldesign-file-pick__name" id="nldesign-documents-logo-name"><?php p($l->t('No file chosen')); ?></span>
				<button type="button" class="button" id="nldesign-documents-logo-remove"><?php p($l->t('Remove document logo')); ?></button>
			</div>
		</div>
		<div class="nldesign-documents-upload">
			<label id="nldesign-documents-cover-label" for="nldesign-documents-cover"><?php p($l->t('Cover image (PNG, JPEG, WebP or SVG, at most 2 MB)')); ?></label>
			<div class="nldesign-file-pick">
				<input type="file" id="nldesign-documents-cover" accept="image/png,image/jpeg,image/webp,image/svg+xml" hidden>
				<button type="button" class="button" id="nldesign-documents-cover-btn"
						aria-labelledby="nldesign-documents-cover-label nldesign-documents-cover-btn"><?php p($l->t('Choose image')); ?></button>
				<span class="nldesign-file-pick__name" id="nldesign-documents-cover-name"><?php p($l->t('No file chosen')); ?></span>
				<button type="button" class="button" id="nldesign-documents-cover-remove"><?php p($l->t('Remove cover image')); ?></button>
			</div>
		</div>
		<p>
			<label for="nldesign-documents-footer-line"><?php p($l->t('Extra footer line')); ?></label><br>
			<input type="text" id="nldesign-documents-footer-line" maxlength="200">
			<button type="button" class="button primary" id="nldesign-documents-footer-save"><?php p($l->t('Save footer line')); ?></button>
		</p>
		<div class="nldesign-documents-preview" id="nldesign-documents-preview" aria-live="polite"></div>
		<span id="nldesign-documents-feedback" role="status" aria-live="polite"></span>
	</div>

	<!-- Upstream token updates — opt-in daily freshness check against
	     nl-design-system/themes (openspec/specs/upstream-freshness/spec.md).
	     Disabled by default; the toggle label discloses the contacted host.
	     No apply control anywhere in this block — informational only, the
	     update path remains the reviewed sync-workflow release. -->
	<div class="nldesign-upstream-freshness" id="nldesign-upstream-freshness" style="margin-top:2em">
		<h3><?php p($l->t('Upstream token updates')); ?></h3>
		<p class="settings-hint">
			<?php p($l->t('Check once a day whether the upstream NL Design System themes have new tokens. The check is off by default and contacts api.github.com. It never applies anything: you review and apply updates yourself.')); ?>
		</p>
		<div class="nldesign-option">
			<input type="checkbox"
				   id="nldesign-upstream-freshness-toggle"
				   class="checkbox">
			<label for="nldesign-upstream-freshness-toggle">
				<?php p($l->t('Check daily for upstream token updates (contacts api.github.com)')); ?>
			</label>
		</div>
		<p class="settings-hint" id="nldesign-upstream-freshness-lastchecked"></p>
		<div id="nldesign-upstream-freshness-notices"
			 class="nldesign-upstream-freshness-notices"
			 role="group"
			 aria-label="<?php p($l->t('Upstream token updates')); ?>"></div>
	</div>

	<!-- Theme gallery: an opt-in index of house styles others built
	     (openspec/specs/theme-gallery/spec.md). Off by default; admin.js
	     puts the index host in the toggle label and fills the list. -->
	<div class="nldesign-gallery" id="nldesign-gallery" style="margin-top:2em">
		<h3><?php p($l->t('Theme gallery')); ?></h3>
		<p class="settings-hint">
			<?php p($l->t('Install a house style that another organisation built. The gallery is off by default. When it is on, this page reads the gallery index while you have it open.')); ?>
		</p>
		<div class="nldesign-option">
			<input type="checkbox" id="nldesign-gallery-toggle" class="checkbox">
			<label for="nldesign-gallery-toggle" id="nldesign-gallery-toggle-label"><?php p($l->t('Show the theme gallery')); ?></label>
		</div>
		<p class="settings-hint" id="nldesign-gallery-status" role="status" aria-live="polite"></p>
		<ul class="nldesign-gallery-list" id="nldesign-gallery-list"
			aria-label="<?php p($l->t('Theme gallery')); ?>"></ul>
	</div>

	<p class="nldesign-info">
		<a href="https://nldesignsystem.nl/" target="_blank" rel="noopener noreferrer">
			<?php p($l->t('Learn more about NL Design System')); ?> ↗
		</a>
	</p>
</div>
