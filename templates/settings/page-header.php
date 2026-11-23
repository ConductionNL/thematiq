<?php

/**
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * The page header of the Theming page as TqTheming draws it
 * (openspec/changes/admin-pages-as-the-tq-boards). The actions keep their ids:
 * js/admin.js binds them.
 *
 * @var \OCP\IL10N $l
 * @var array{documentationUrl: string} $_
 */

style('thematiq', 'admin-page');
?>
<section id="thematiq-page-header" class="thematiq-page-header" aria-labelledby="thematiq-page-title">
	<div class="thematiq-page-header__row">
		<h1 id="thematiq-page-title" class="thematiq-page-header__title"><?php p($l->t('Themes and appearance')); ?></h1>
		<div class="thematiq-page-header__actions">
			<button type="button" id="nldesign-preview-btn" class="thematiq-page-header__action">
				<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M12,9A3,3 0 0,0 9,12A3,3 0 0,0 12,15A3,3 0 0,0 15,12A3,3 0 0,0 12,9M12,17A5,5 0 0,1 7,12A5,5 0 0,1 12,7A5,5 0 0,1 17,12A5,5 0 0,1 12,17M12,4.5C7,4.5 2.73,7.61 1,12C2.73,16.39 7,19.5 12,19.5C17,19.5 21.27,16.39 23,12C21.27,7.61 17,4.5 12,4.5Z"/></svg>
				<?php p($l->t('Preview in my session')); ?>
			</button>
			<a href="<?php p($_['documentationUrl']); ?>" id="nldesign-doc-link" class="thematiq-page-header__action" target="_blank" rel="noopener noreferrer">
				<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M14,3V5H17.59L7.76,14.83L9.17,16.24L19,6.41V10H21V3M19,19H5V5H12V3H5C3.89,3 3,3.9 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V12H19V19Z"/></svg>
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
	<p class="thematiq-page-header__intro"><?php p($l->t('Nextcloud sets the name, the colour and the logo. On top of that, thematiq lays your organisation\'s house style over the whole workplace: tokens, layout, website and the dark variant.')); ?></p>
</section>
