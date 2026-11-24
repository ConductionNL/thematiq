<?php

/**
 * Thematiq Migrate App Config Keys Repair Step
 *
 * Repair step that carries this app's stored `IAppConfig` values across the
 * `nldesign` -> `thematiq` APP-ID rename.
 *
 * THE APP ID MOVED; THE DESIGN SYSTEM DID NOT. `nldesign` here means the old
 * Nextcloud app id and nothing else. The NL Design System's own names — the
 * `nldesign` CSS custom properties, class prefixes, the `nldesign` design-system
 * id in design-systems.json — did not change and are not touched by this step.
 *
 * Nextcloud namespaces `IAppConfig` by app id at the storage layer
 * (`oc_appconfig.appid`), so renaming `<id>` does not rename the rows — it makes
 * every previously stored value unreachable, because the app now asks for them
 * under a different app id. There is no in-place app-id upgrade in Nextcloud:
 * the new id is simply a different app. This step therefore copies each value
 * from the old namespace to the new one.
 *
 * WHAT IS ACTUALLY AT STAKE. Every reader here supplies a default, so a lost
 * value does not error — it reverts, silently, with no log line to notice. For
 * a THEMING app that means the instance visibly changes appearance and nothing
 * anywhere says why:
 *   - `token_set` is the active token set id — the Rijkshuisstijl, Utrecht,
 *     Amsterdam, Den Haag or Rotterdam brand the instance is themed with. Its
 *     readers default to `'nextcloud'` ({@see ComplianceReportService},
 *     {@see EmailThemingService}), so losing it silently reverts a themed
 *     government instance to stock Nextcloud blue.
 *   - `custom_token_sets` holds admin-authored brands that exist nowhere else;
 *     `custom_fonts` / `custom_fonts_rev` the uploaded font manifest;
 *     `primary_color` / `background_color` the manual overrides.
 *   - `disabled_apps` ({@see AppThemingService}) and `group_token_sets` /
 *     `group_token_sets_generation` ({@see GroupThemingService}) are the
 *     per-app and per-group exclusions. Lose them and theming silently starts
 *     applying to apps and groups an admin deliberately excluded — a change in
 *     behaviour, not just in configuration.
 *   - `email_footer_org_name`, `email_footer_accessibility_url` and
 *     `email_footer_privacy_url` are the statutory footer fields; the
 *     accessibility-statement URL is a legal obligation for Dutch public bodies.
 *   - `custom_css_enabled`, `hide_slogan`, `show_menu_labels`, `icon_pack`,
 *     `marianne_enabled` and `dark_variants` are the remaining admin toggles.
 *
 * WHY EVERY KEY RATHER THAN A FIXED LIST. The admin settings are not the whole
 * stored set — {@see ThemingAuditService} keeps the `audit_entries_total`
 * counter, the theming sync keeps `theming_syncs_total`, and past releases have
 * written keys this app no longer reads. Enumerating `IAppConfig::getKeys()` is
 * exhaustive by construction and cannot drift out of date the way a hardcoded
 * list does.
 *
 * THE KEY NAMES ARE COPIED VERBATIM. No app config key in this app embeds the
 * app id — they are all bare names like `token_set`, `icon_pack`,
 * `custom_fonts` — so there is no key-name prefix to rewrite. (Had there been
 * one, rewriting the NAME as well as moving the row would be mandatory: a value
 * copied under a name nothing reads is the same silent loss this step exists to
 * prevent.)
 *
 * ORDERING. Registered FIRST in both blocks of `appinfo/info.xml`, ahead of
 * `GenerateDarkVariantsRepairStep`, and that order is load-bearing as a
 * standing invariant: any step that writes configuration under the new app id
 * before this one runs would leave those keys already present under `thematiq`,
 * and this step would skip them as "already present", stranding the old values
 * under `nldesign` where nothing reads them.
 *
 * NOTHING HERE TOUCHES `mail_template_class`. That value is a Nextcloud SYSTEM
 * config entry in `config.php`, not an app-config row, and it holds a class name
 * rather than a setting — {@see MigrateStoredClassNames} owns it. This step
 * moves the app's own configuration namespace only.
 *
 * SAFETY. Idempotent and non-destructive:
 *   - a key is copied only when the old value is non-empty AND the new
 *     namespace does not already hold a value, so an admin edit made after the
 *     rename is never clobbered and a second run is a no-op;
 *   - the old `nldesign` rows are never deleted, so a rollback to the previous
 *     app id still finds its configuration intact;
 *   - values round-trip as raw strings. `IAppConfig` stores every value as a
 *     string and the typed accessors only coerce on read, so a string
 *     round-trip cannot lose or corrupt a value written by a typed setter;
 *   - BOTH the reads and the write sit inside the try, not just the write. A
 *     throwing read would otherwise escape `run()`, and because this step is
 *     registered under `<install>` — the only hook that fires on the fresh
 *     install the rename actually performs — an escaping throw aborts the
 *     install and the app never enables at all. Every route in the app dies
 *     with it. One unreadable config value is not worth that.
 *
 * @category Repair
 * @package  OCA\Thematiq\Repair
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec exclude No canonical spec covers the nldesign -> thematiq app-id
 *  rename; pointing this at an existing spec would report conformance to a
 *  requirement that says nothing about it. The settings it preserves are
 *  specified where they are read.
 */

declare(strict_types=1);

namespace OCA\Thematiq\Repair;

use OCA\Thematiq\AppInfo\Application;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Copy every stored IAppConfig value from the nldesign namespace to thematiq.
 *
 * @spec exclude One-off nldesign -> thematiq app-id rename plumbing.
 */
class MigrateAppConfigKeys implements IRepairStep {

	/**
	 * The app-config namespace this app used before the rename.
	 *
	 * Deliberately the OLD app id. This constant is one of the few places in
	 * the app that is supposed to still say `nldesign`, and it refers to the
	 * former APP ID — not to the NL Design System, whose own names never moved.
	 *
	 * @var string
	 */
	private const OLD_APP_ID = 'nldesign';

	/**
	 * Config keys Nextcloud owns for every app. These MUST NOT be copied.
	 *
	 * `AppManager::enableApp()` writes `enabled` through the deprecated
	 * `IAppConfig::setValue()`, which stores type MIXED. Copying it here with
	 * `setValueString()` stores type STRING, and the next `app:enable` then
	 * fails with an `AppConfigTypeConflictException` — permanently, because the
	 * conflict is hit before the app can run anything that would repair it.
	 * `installed_version` and `types` are Nextcloud's own bookkeeping for the
	 * app, and copying the old app's values would misreport the new one —
	 * `installed_version` in particular would make Nextcloud believe `thematiq`
	 * is already at the old app's version and skip its migrations.
	 *
	 * @var string[]
	 */
	private const RESERVED_KEYS = [
		'enabled',
		'installed_version',
		'types',
	];

	/**
	 * Constructor for MigrateAppConfigKeys.
	 *
	 * @param IAppConfig $appConfig The app config store to read and write.
	 * @param LoggerInterface $logger Logger for keys that fail to copy.
	 */
	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly LoggerInterface $logger,
	) {

	}//end __construct()

	/**
	 * The repair step name.
	 *
	 * @return string
	 *
	 * @spec exclude One-off nldesign -> thematiq app-id rename plumbing; no
	 *  capability spec describes the rename, and pointing this at an existing
	 *  one would claim conformance to a requirement that says nothing about it.
	 */
	public function getName(): string {
		return 'Copy Thematiq app configuration from the nldesign namespace to thematiq';
	}//end getName()

	/**
	 * Run the repair step to migrate the stored app configuration.
	 *
	 * @param IOutput $output The output interface for progress reporting.
	 *
	 * @return void
	 *
	 * @spec exclude One-off nldesign -> thematiq app-id rename plumbing: it
	 *  moves oc_appconfig rows between namespaces and adds no behaviour of its
	 *  own. The settings it preserves are specified where they are read.
	 */
	public function run(IOutput $output): void {
		$keys = $this->oldKeys();
		if ($keys === []) {
			$output->info(
				'MigrateAppConfigKeys: no stored nldesign configuration on this install; nothing to do.'
			);
			return;
		}

		$tally = ['migrated' => 0, 'present' => 0, 'empty' => 0, 'reserved' => 0, 'failed' => 0];
		foreach ($keys as $key) {
			$tally[$this->migrateKey(key: $key)]++;
		}

		$output->info(
			'MigrateAppConfigKeys: ' . $tally['migrated'] . ' key(s) migrated, ' . $tally['present']
			. ' already present, ' . $tally['empty'] . ' had no value to migrate, '
			. $tally['reserved'] . ' skipped as Nextcloud-reserved, ' . $tally['failed'] . ' failed.'
		);
	}//end run()

	/**
	 * Migrate one key, and say what became of it.
	 *
	 * @param string $key The key under the old namespace.
	 *
	 * @return string One of `reserved`, `empty`, `present`, `migrated` or `failed`.
	 */
	private function migrateKey(string $key): string {
		if (in_array($key, self::RESERVED_KEYS, strict: true) === true) {
			return 'reserved';
		}

		// The READS live inside the try alongside the write, deliberately.
		// A throwing getValueString() outside it would escape run() and
		// abort the install — see the class docblock.
		try {
			return $this->copyValue(key: $key);
		} catch (Throwable $e) {
			$this->logger->warning(
				'Thematiq: could not migrate one app config key; leaving it under the old namespace',
				['key' => $key, 'exception' => $e->getMessage()]
			);

			return 'failed';
		}
	}//end migrateKey()

	/**
	 * Copy one key's value to the new namespace, unless it has none or the new namespace already has one.
	 *
	 * @param string $key The key.
	 *
	 * @return string One of `empty`, `present` or `migrated`.
	 */
	private function copyValue(string $key): string {
		$old = $this->appConfig->getValueString(self::OLD_APP_ID, $key, '');
		if ($old === '') {
			return 'empty';
		}

		$existing = $this->appConfig->getValueString(Application::APP_ID, $key, '');
		if ($existing !== '') {
			return 'present';
		}

		$this->appConfig->setValueString(Application::APP_ID, $key, $old);

		return 'migrated';
	}//end copyValue()

	/**
	 * Every key currently stored under the old app-config namespace.
	 *
	 * @return array<int, string> The stored key names, empty when unreadable.
	 */
	private function oldKeys(): array {
		try {
			return $this->appConfig->getKeys(self::OLD_APP_ID);
		} catch (Throwable $e) {
			$this->logger->warning(
				'Thematiq: could not enumerate nldesign app config keys; skipping the migration',
				['exception' => $e->getMessage()]
			);
			return [];
		}//end try

	}//end oldKeys()

}//end class
