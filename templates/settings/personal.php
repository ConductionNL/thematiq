<?php

/**
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * House style of my groups: a group subadmin picks the set of a delegated
 * group (openspec/specs/per-group-theming/spec.md). Filled by
 * js/personal-group-house-style.js.
 *
 * @var \OCP\IL10N $l
 */

script('thematiq', 'personal-group-house-style');
?>
<div id="thematiq-my-groups" class="section">
	<h2><?php p($l->t('House style of my groups')); ?></h2>
	<p class="settings-hint">
		<?php p($l->t('You manage these groups. Choose the house style their members see. An administrator decides which house styles you can choose from.')); ?>
	</p>
	<div id="thematiq-my-groups-list">
		<p class="settings-hint"><?php p($l->t('Loading groups…')); ?></p>
	</div>
	<p id="thematiq-my-groups-feedback" role="status" aria-live="polite"></p>
</div>
