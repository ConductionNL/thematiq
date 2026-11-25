<?php

/**
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * House style of my groups: a group subadmin picks the set of a delegated
 * group (openspec/specs/per-group-theming/spec.md). Filled by
 * js/admin-group-house-style.js. In the personal Appearance section
 * (`personal`) it is drawn as the TqPersoonlijk board's block.
 *
 * @var \OCP\IL10N $l
 * @var array $_
 */

script('thematiq', 'admin-group-house-style');
style('thematiq', 'group-house-style');
// phpcs:ignore Squiz.NamingConventions.ValidVariableName.NotCamelCaps -- Nextcloud hands a template its parameters as $_.
$personal = (($_['personal'] ?? false) === true);
$blockClass = 'section';
if ($personal === true) {
	$blockClass .= ' thematiq-my-groups--personal';
}
?>
<div id="thematiq-my-groups" class="<?php p($blockClass); ?>">
	<div class="thematiq-my-groups__head">
		<h2><?php p($l->t('House style of my groups')); ?></h2>
		<?php if ($personal === true): ?>
			<span class="thematiq-my-groups__chip">thematiq</span>
		<?php endif; ?>
	</div>
	<p class="settings-hint">
		<?php p($l->t('You manage these groups. Choose the house style their members see. An administrator decides which house styles you can choose from.')); ?>
	</p>
	<div id="thematiq-my-groups-list">
		<p class="settings-hint"><?php p($l->t('Loading groups…')); ?></p>
	</div>
	<p id="thematiq-my-groups-feedback" role="status" aria-live="polite"></p>
	<?php if ($personal === true): ?>
		<p class="settings-hint thematiq-my-groups__why">
			<?php p($l->t('You see this block only because you are group admin of a group for which the administrator has released the house style.')); ?>
		</p>
	<?php endif; ?>
</div>
