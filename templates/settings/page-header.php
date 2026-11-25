<?php

/**
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * The page header of the Theming page as TqTheming draws it
 * (openspec/changes/admin-pages-as-the-tq-boards). The actions keep their ids:
 * js/admin.js binds them and points Documentation at the docs of the chosen
 * set's design system.
 *
 * @var \OCP\IL10N $l
 */

style('thematiq', 'admin-page');
?>
<section id="thematiq-page-header" class="thematiq-page-header" aria-labelledby="thematiq-page-title">
	<div class="thematiq-page-header__row">
		<h1 id="thematiq-page-title" class="thematiq-page-header__title">
			<?php p($l->t('Themes and appearance')); ?>
		</h1>
		<div class="thematiq-page-header__actions">
			<button type="button" id="nldesign-preview-btn" class="thematiq-page-header__action">
				<span class="thematiq-page-header__icon thematiq-page-header__icon--preview" aria-hidden="true"></span>
				<?php p($l->t('Preview in my session')); ?>
			</button>
			<a href="<?php p(\OCA\Thematiq\Settings\Admin::DEFAULT_DOCUMENTATION_URL); ?>" id="nldesign-doc-link"
				class="thematiq-page-header__action button" target="_blank" rel="noopener noreferrer">
				<span class="thematiq-page-header__icon thematiq-page-header__icon--external" aria-hidden="true"></span>
				<?php p($l->t('Documentation')); ?>
				<span class="hidden-visually"><?php p($l->t('(opens in a new tab)')); ?></span>
			</a>
		</div>
	</div>
	<nav class="thematiq-page-header__crumbs" aria-label="<?php p($l->t('Breadcrumb')); ?>">
		<span><?php p($l->t('Administration settings')); ?></span>
		<span aria-hidden="true">/</span>
		<span aria-current="page"><?php p($l->t('Themes and appearance')); ?></span>
	</nav>
	<p class="thematiq-page-header__intro">
		<?php
		// One literal, so the translation tools find the sentence whole.
		// phpcs:ignore Generic.Files.LineLength.MaxExceeded
		p($l->t('Nextcloud sets the name, the colour and the logo. On top of that, thematiq lays your organisation\'s house style over the whole workplace: tokens, layout, website and the dark variant.'));
		?>
	</p>
</section>
