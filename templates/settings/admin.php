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
script('thematiq', 'admin');
script('thematiq', 'admin-assistant-mark');
script('thematiq', 'admin-config-source');
script('thematiq', 'admin-documents');
script('thematiq', 'admin-app-brands');
style('thematiq', 'admin');
// The component playground: the selector / stage / tokens instrument that
// admin.js's token editor is rebuilt into. Loaded AFTER admin.js because it
// attaches to the editor that script renders, and waits for it.
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
	<div class="nldesign-settings-header">
		<h2><?php p($l->t('NL Design System Theme')); ?></h2>
		<a href="https://nldesign.app" target="_blank" rel="noopener noreferrer" class="nldesign-doc-link">
			<span class="icon-link-external"></span>
			<?php p($l->t('Documentation')); ?>
		</a>
	</div>
	<p class="settings-hint">
		<?php p($l->t('Select a Dutch government design token set as a base, or customize individual Nextcloud CSS tokens below.')); ?>
	</p>

	<!-- The OTAP environment this server declares in config.php, read-only
	     (openspec/specs/environment-marker/spec.md). No control writes it. -->
	<div class="nldesign-environment" id="nldesign-environment">
		<?php if ($_['environment'] !== ''): ?>
			<p><?php p($l->t('Environment: {environment}', ['environment' => $_['environment']])); ?></p>
		<?php else: ?>
			<p class="settings-hint"><?php p($l->t('No environment is set, so no page is marked. Declare it in config.php with:')); ?></p>
			<code><?php p($_['environmentCommand']); ?></code>
		<?php endif; ?>
	</div>

	<div class="nldesign-token-set-selector">
		<label for="nldesign-token-set-select"><?php p($l->t('Design token set')); ?></label>
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
		<p class="settings-hint" id="nldesign-token-set-switch-note" role="status" hidden></p>
		<!-- What can be done with the selected set: the buttons act on it, the
		     links read it. A row of their own, so they no longer wrap into the
		     select's line at arbitrary points. -->
		<div class="nldesign-token-set-actions">
			<button type="button" id="nldesign-preview-btn" class="button">
				<?php p($l->t('Preview in my session')); ?>
			</button>
			<button type="button" id="nldesign-reset-theme-btn" class="button">
				<?php p($l->t('Reset theme to Nextcloud')); ?>
			</button>
			<!-- Token reference of the selected set (openspec/specs/token-reference/spec.md);
			     admin.js keeps both links on the selected set. -->
			<span class="nldesign-token-set-links">
				<a id="nldesign-token-reference-link" class="nldesign-token-reference-link" target="_blank" rel="noopener noreferrer"><?php p($l->t('Token reference')); ?></a>
				<a id="nldesign-token-reference-download" class="nldesign-token-reference-link"><?php p($l->t('Download token reference')); ?></a>
			</span>
		</div>
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
				<div class="nldesign-form-actions">
					<button type="button" id="nldesign-upload-btn" class="button primary">
						<?php p($l->t('Choose file and upload')); ?>
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
					<div class="nldesign-field">
						<label for="nldesign-brand-logo-btn"><?php p($l->t('Logo (optional)')); ?></label>
						<!-- Nextcloud's own button instead of the browser's file control,
						     which draws in the browser's language and style. The input is
						     named for the same reason as the upload's above. -->
						<div class="nldesign-file-pick">
							<input type="file" id="nldesign-brand-logo" accept=".svg,.png,.jpg,.gif,.webp" hidden
								   aria-label="<?php p($l->t('Logo file (SVG, PNG, JPG, GIF or WebP)')); ?>">
							<button type="button" class="button" id="nldesign-brand-logo-btn"><?php p($l->t('Choose logo')); ?></button>
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
				<button type="button" class="nldesign-preview-switch-btn active" data-view="app" aria-selected="true"><?php p($l->t('App')); ?></button>
				<button type="button" class="nldesign-preview-switch-btn" data-view="login" aria-selected="false"><?php p($l->t('Login')); ?></button>
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

	<!-- Freeform custom CSS — admin-authored arbitrary rules, sanitised
	     server-side and emitted after every other theming layer. -->
	<div class="nldesign-custom-css" id="nldesign-custom-css" style="margin-top:2em">
		<h3><?php p($l->t('Custom CSS')); ?></h3>
		<p class="settings-hint">
			<?php p($l->t('Freeform CSS applied after every other theming layer, so it always wins. Use it for tweaks the token editor cannot express.')); ?>
		</p>
		<p class="settings-hint">
			<?php p($l->t('For safety this is checked before it is saved: @import, external url() references, script-execution vectors and HTML tags are refused, as are the background variables Nextcloud needs for dark mode. Relative paths and data: URIs are allowed. Every save is written to the audit log.')); ?>
		</p>
		<p>
			<input type="checkbox" id="nldesign-custom-css-enabled" class="checkbox">
			<label for="nldesign-custom-css-enabled"><?php p($l->t('Enable custom CSS')); ?></label>
		</p>
		<label for="nldesign-custom-css-input" class="hidden-visually"><?php p($l->t('Custom CSS')); ?></label>
		<textarea id="nldesign-custom-css-input" rows="10" spellcheck="false"
				  style="width:100%;font-family:monospace;font-size:13px"
				  placeholder=".app-content { padding: 8px; }"></textarea>
		<button type="button" id="nldesign-custom-css-save" class="button primary">
			<?php p($l->t('Save custom CSS')); ?>
		</button>
		<span id="nldesign-custom-css-feedback" role="status" aria-live="polite"></span>
	</div>

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

	<!-- Custom font upload -->
	<div class="nldesign-custom-fonts" id="nldesign-custom-fonts" style="margin-top:2em">
		<h3><?php p($l->t('Custom fonts')); ?></h3>
		<p class="nldesign-license-notice">
			<?php p($l->t('Only upload fonts your organization holds a license to self-host. Licensing responsibility rests with the uploader.')); ?>
		</p>
		<p class="settings-hint">
			<?php p($l->t('Upload a WOFF2 font file (max 2 MB, 20 fonts max) and assign it to the body text or heading role. Uploaded fonts are self-hosted — no external requests.')); ?>
		</p>
		<div class="nldesign-upload-form">
			<label for="nldesign-font-name"><?php p($l->t('Font display name')); ?></label>
			<input type="text" id="nldesign-font-name" class="nldesign-font-name"
				   placeholder="<?php p($l->t('e.g. Rijks Sans')); ?>"
				   maxlength="64">
			<label for="nldesign-font-role"><?php p($l->t('Font role')); ?></label>
			<select id="nldesign-font-role" name="nldesign-font-role">
				<option value="body"><?php p($l->t('Body text')); ?></option>
				<option value="heading"><?php p($l->t('Heading')); ?></option>
			</select>
			<input type="file" id="nldesign-font-input" accept=".woff2"
				   aria-label="<?php p($l->t('Font file to upload (WOFF2)')); ?>"
				   style="display:none">
			<button type="button" id="nldesign-font-upload-btn" class="button">
				<?php p($l->t('Choose font and upload')); ?>
			</button>
		</div>
		<div id="nldesign-font-upload-result" class="nldesign-import-result" role="status" aria-live="polite" style="display:none"></div>
		<div id="nldesign-font-list" class="nldesign-custom-set-list" role="group"
			 aria-label="<?php p($l->t('Custom fonts')); ?>">
			<p class="settings-hint"><?php p($l->t('Loading fonts…')); ?></p>
		</div>
	</div>

	<!-- Hide Slogan/Payoff Option -->
	<div class="nldesign-option">
		<input type="checkbox"
			   name="nldesign-hide-slogan"
			   id="nldesign-hide-slogan"
			   class="checkbox"
			   <?php if ($_['hideSlogan']): ?>checked<?php endif; ?>>
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
			   <?php if ($_['showMenuLabels']): ?>checked<?php endif; ?>>
		<label for="nldesign-show-menu-labels">
			<?php p($l->t('Show text labels in app menu (hide icons)')); ?>
		</label>
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
			   <?php if ($_['primaryDrivesComponents']): ?>checked<?php endif; ?>>
		<label for="nldesign-primary-drives-components">
			<?php p($l->t('Let the primary color drive every component')); ?>
		</label>
		<p class="settings-hint">
			<?php p($l->t('While this is on, the brand primary overrules any color set on an individual component, and those controls are locked. Switching it off gives each component its own color back.')); ?>
		</p>
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
			   <?php if ($_['darkVariantsEnabled']): ?>checked<?php endif; ?>>
		<label for="nldesign-dark-variants">
			<?php p($l->t('Enable dark mode variants for the active token set')); ?>
		</label>
	</div>
	<p class="settings-hint">
		<?php p($l->t('When enabled, a generated dark-mode stylesheet follows whichever Nextcloud dark/light/system theme is already active — it never changes your Nextcloud theme choice itself.')); ?>
	</p>

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
		<p>
			<label for="nldesign-documents-logo"><?php p($l->t('Document logo (PNG, JPEG, WebP or SVG, at most 2 MB)')); ?></label><br>
			<input type="file" id="nldesign-documents-logo" accept="image/png,image/jpeg,image/webp,image/svg+xml">
			<button type="button" class="button" id="nldesign-documents-logo-remove"><?php p($l->t('Remove document logo')); ?></button>
		</p>
		<p>
			<label for="nldesign-documents-cover"><?php p($l->t('Cover image (PNG, JPEG, WebP or SVG, at most 2 MB)')); ?></label><br>
			<input type="file" id="nldesign-documents-cover" accept="image/png,image/jpeg,image/webp,image/svg+xml">
			<button type="button" class="button" id="nldesign-documents-cover-remove"><?php p($l->t('Remove cover image')); ?></button>
		</p>
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

	<!-- AI assistant: the approved mark (openspec/specs/assistant-approved-mark/spec.md).
	     Filled and saved by js/admin-assistant-mark.js. -->
	<div class="nldesign-assistant-mark" id="nldesign-assistant-mark" style="margin-top:2em">
		<h3><?php p($l->t('AI assistant')); ?></h3>
		<p class="settings-hint">
			<?php p($l->t('Show users which AI assistant your organisation approved. The mark appears in the footer of the assistant panel in Conduction apps.')); ?>
			<?php p($l->t('The mark informs honest users. It is not a security control.')); ?>
		</p>
		<div class="nldesign-option">
			<input type="checkbox" id="nldesign-assistant-mark-enabled" class="checkbox">
			<label for="nldesign-assistant-mark-enabled"><?php p($l->t('Show the approved mark')); ?></label>
		</div>
		<p>
			<label for="nldesign-assistant-mark-organisation"><?php p($l->t('Organisation name')); ?></label><br>
			<input type="text" id="nldesign-assistant-mark-organisation" maxlength="120">
		</p>
		<p>
			<label for="nldesign-assistant-mark-logo"><?php p($l->t('Logo address')); ?></label><br>
			<input type="text" id="nldesign-assistant-mark-logo" maxlength="500">
		</p>
		<div class="nldesign-assistant-mark-preview" id="nldesign-assistant-mark-preview" aria-live="polite"></div>
		<button type="button" id="nldesign-assistant-mark-save" class="button primary"><?php p($l->t('Save AI assistant settings')); ?></button>
		<span id="nldesign-assistant-mark-feedback" role="status" aria-live="polite"></span>
	</div>

	<!-- Theming audit log — who changed which theming setting, from what, to
	     what, and when. Evidence for accessibility/WCAG-EM audits. -->
	<div class="nldesign-audit-log" id="nldesign-audit-log" style="margin-top:2em">
		<h3><?php p($l->t('Theming audit log')); ?></h3>
		<p class="settings-hint">
			<?php p($l->t('A record of theming configuration changes: who changed what, from what, to what, and when. Useful evidence for accessibility audits.')); ?>
			<?php p($l->t('Each change keeps the configuration it produced, up to the last 50 changes or 20 MB. Restore shows what will change before anything is written.')); ?>
		</p>
		<div class="nldesign-audit-scroll" tabindex="0" role="region"
		     aria-label="<?php p($l->t('Theming audit log')); ?>">
		<table class="nldesign-audit-table" id="nldesign-audit-table">
			<thead>
				<tr>
					<th scope="col"><?php p($l->t('Timestamp')); ?></th>
					<th scope="col"><?php p($l->t('User')); ?></th>
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
		<button type="button" id="nldesign-audit-download-btn" class="button">
			<?php p($l->t('Download full log')); ?>
		</button>
	</div>

	<!-- Contrast evidence report: the export endpoint's two formats as
	     download links. js/admin.js initComplianceReport() fills the hrefs.
	     (openspec/specs/compliance-evidence/spec.md) -->
	<div class="nldesign-compliance-report" id="nldesign-compliance-report" style="margin-top:2em">
		<h3><?php p($l->t('Contrast evidence report')); ?></h3>
		<p class="settings-hint">
			<?php p($l->t('Download the color contrast of the active theme tokens as evidence for an accessibility statement. It covers color contrast of the theme only and is not a full WCAG audit.')); ?>
		</p>
		<div class="nldesign-upload-form">
			<a id="nldesign-compliance-report-json" class="button" download>
				<?php p($l->t('Download as JSON')); ?>
			</a>
			<a id="nldesign-compliance-report-markdown" class="button" download>
				<?php p($l->t('Download as Markdown')); ?>
			</a>
		</div>
	</div>

	<!-- Complete configuration bundle — OTAP (dev/test/acceptatie/productie)
	     promotion. Unlike the token-editor overrides download above, this
	     covers the COMPLETE nldesign configuration (config-portability spec):
	     token set, toggles, per-app exclusions, overrides, custom token sets,
	     email footer, custom-font metadata, upstream-freshness toggle. -->
	<div class="nldesign-config-bundle" id="nldesign-config-bundle" style="margin-top:2em">
		<h3><?php p($l->t('Configuration bundle (OTAP promotion)')); ?></h3>
		<p class="settings-hint">
			<?php p($l->t('Download or upload the complete NL Design configuration as one JSON file — the active token set, toggles, per-app exclusions, token overrides, custom token sets, email footer, and the upstream-update toggle. Use this to promote configuration between dev, test, acceptance, and production environments identically. This is different from the overrides-only download above.')); ?>
		</p>
		<div class="nldesign-upload-form">
			<button type="button" id="nldesign-config-bundle-download-btn" class="button">
				<?php p($l->t('Download configuration')); ?>
			</button>
			<input type="file" id="nldesign-config-bundle-input" accept=".json"
				   aria-label="<?php p($l->t('Configuration bundle file to upload (JSON)')); ?>"
				   style="display:none">
			<button type="button" id="nldesign-config-bundle-upload-btn" class="button">
				<?php p($l->t('Upload configuration')); ?>
			</button>
		</div>
		<div id="nldesign-config-bundle-result" class="nldesign-import-result" role="status" aria-live="polite" style="display:none"></div>
		<!-- Theme as code (openspec/specs/theme-as-code/spec.md): filled by
		     js/admin-config-source.js when thematiq.config_source is set. -->
		<div id="nldesign-config-source" class="nldesign-config-source" role="status" aria-live="polite" hidden></div>
	</div>

	<p class="nldesign-info">
		<a href="https://nldesignsystem.nl/" target="_blank" rel="noopener noreferrer">
			<?php p($l->t('Learn more about NL Design System')); ?> ↗
		</a>
	</p>
</div>
