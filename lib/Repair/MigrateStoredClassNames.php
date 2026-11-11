<?php

/**
 * Thematiq Migrate Stored Class Names Repair Step
 *
 * Repair step that rewrites this app's own fully-qualified class names where
 * Nextcloud has STORED them, across the `OCA\NLDesign\` -> `OCA\Thematiq\`
 * namespace rename that accompanied the `nldesign` -> `thematiq` app-id rename.
 *
 * A stored class name is a different problem from a stored setting. The other
 * two rename steps ({@see MigrateAppConfigKeys}, {@see MigrateUserPreferences})
 * move rows between two app-id namespaces and never look at the VALUES. This
 * step is the opposite: one fixed slot, whose value is a class name that no
 * longer resolves.
 *
 * WHAT IS ACTUALLY STORED. Exactly one thing, and this was established by
 * inventory rather than assumed — every other candidate was checked and ruled
 * out (see "WHY ONLY ONE" below). Nextcloud's `mail_template_class` SYSTEM
 * config entry, in `config.php`, names the class the server instantiates for
 * every outbound email. When an admin enables email theming,
 * {@see \OCA\Thematiq\Service\EmailThemingService::enable()} writes this app's
 * template class into it. On an instance themed before 2026-08-22 that value
 * still reads `OCA\NLDesign\Mail\NLDesignEMailTemplate`, and that class does not
 * exist any more.
 *
 * THE TWO FAILURES THIS REPAIRS, BOTH SILENT.
 *
 *   1. EVERY BRANDED EMAIL SILENTLY REVERTS TO STOCK. Nextcloud's
 *      `OC\Mail\Mailer::createEMailTemplate()` guards the stored value with
 *      `class_exists($class) && is_a($class, EMailTemplate::class, true)` and
 *      falls back to the plain `EMailTemplate` when it fails. So the stale
 *      value does not raise — the government branding, logo and statutory
 *      footer just stop appearing on password resets, share notifications and
 *      every other mail, with nothing in any log.
 *
 *   2. THE ADMIN IS LOCKED OUT OF FIXING IT, AND MISINFORMED ABOUT WHY.
 *      `EmailThemingService::getState()` classifies the stored value as
 *      `enabled` when it equals the CURRENT template class, `disabled` when it
 *      is empty, and `foreign` otherwise. The app's own pre-rename value is
 *      neither of the first two, so it is reported as `foreign` — and
 *      `enable()` refuses a foreign class with `ForeignMailTemplateClassException`
 *      (HTTP 409). The admin panel therefore shows email theming as OFF,
 *      refuses to switch it back on, and blames a third-party template that
 *      does not exist. This step is the only thing that clears that state
 *      short of hand-editing config.php.
 *
 * WHY ONLY ONE. The other places a class name could be persisted were checked
 * and each is either self-healing or not this app's to touch:
 *   - `oc_jobs` stores the background job FQCN, and the pre-rename row does say
 *     `OCA\NLDesign\BackgroundJob\UpstreamFreshnessJob`. It needs no migration:
 *     `OC\BackgroundJob\JobList::getNext()` removes a row whose class no longer
 *     exists ("Remove job from disabled app or old version of an app") and
 *     installing `thematiq` re-registers the job from `<background-jobs>`.
 *     Writing a step for it would add a race against the server's own cleanup
 *     for no gain.
 *   - This app has no `lib/Settings/*register*.json`, no `register.d/`, and no
 *     seeded OpenRegister object descriptors — so there are no stored schema or
 *     object FQCNs.
 *   - No `IAppConfig` or `IConfig` value in this app holds a class name;
 *     `setSystemValue()` is called in exactly one place, the one above.
 *   - `lib/Controller/HealthController.php` holds two FQCN string constants,
 *     but they name OPENREGISTER classes
 *     (`OCA\OpenRegister\AppHost\Observability\*`). Those are cross-app runtime
 *     lookups: they may only move when OpenRegister's own classes move, and
 *     rewriting them here would silently no-op the integration.
 *
 * NOT A COPY BUT A REWRITE, AND WHY THAT IS STILL SAFE. The other two steps
 * copy and leave the source intact so a rollback finds its data. There is no
 * equivalent here: `mail_template_class` is a single slot, and leaving the
 * stale value in place IS the defect. A rollback to the old app id would find
 * the new class name and fall back to the stock template — the same degraded
 * behaviour it has today, not a worse one, and it never loses data because the
 * previous value is written to the log and to the repair output before it is
 * replaced.
 *
 * SAFETY. Idempotent, conservative, and non-throwing:
 *   - it rewrites ONLY the exact old FQCN. An empty value, the already-migrated
 *     value, and a genuinely foreign third-party template are all left exactly
 *     as they are — this step never enables email theming on an instance that
 *     had not enabled it, and never clobbers another vendor's template;
 *   - a second run finds the new value and is a no-op;
 *   - `config.php` can be read-only (`config_is_read_only`, or a filesystem
 *     chmod that only surfaces as a throw from the write itself). Both are
 *     caught and reported with the exact `occ` command to run by hand, because
 *     an admin who cannot be told this has no way to discover it;
 *   - it NEVER throws. Registered under `<install>` — the only hook that fires
 *     on the fresh install a rename actually performs — an escaping exception
 *     aborts the install and the app never enables at all. A stale mail
 *     template is not worth the whole app.
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
 * @spec exclude No canonical spec covers the OCA\NLDesign -> OCA\Thematiq
 *  namespace rename. The email-theming behaviour this restores is specified in
 *  openspec/specs/email-theming/spec.md, but that spec describes the toggle,
 *  not the rename, so tagging this step with it would claim conformance to a
 *  requirement that says nothing about migrating a stored class name.
 */

declare(strict_types=1);

namespace OCA\Thematiq\Repair;

use OCA\Thematiq\Mail\NLDesignEMailTemplate;
use OCA\Thematiq\Service\EmailThemingService;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Rewrite stored OCA\NLDesign class names to their OCA\Thematiq equivalents.
 *
 * @spec exclude One-off OCA\NLDesign -> OCA\Thematiq namespace rename plumbing.
 */
class MigrateStoredClassNames implements IRepairStep {

	/**
	 * The mail template FQCN this app registered before the namespace rename.
	 *
	 * Deliberately the OLD namespace, and deliberately a literal rather than a
	 * `::class` reference — the class it names no longer exists, so there is
	 * nothing to reference. This is the exact string an affected `config.php`
	 * still contains.
	 *
	 * @var string
	 */
	private const OLD_MAIL_TEMPLATE_CLASS = 'OCA\\NLDesign\\Mail\\NLDesignEMailTemplate';

	/**
	 * Constructor for MigrateStoredClassNames.
	 *
	 * @param IConfig $config The system config store to read and write.
	 * @param LoggerInterface $logger Logger for a rewrite that cannot be made.
	 */
	public function __construct(
		private readonly IConfig $config,
		private readonly LoggerInterface $logger,
	) {

	}//end __construct()

	/**
	 * The repair step name.
	 *
	 * @return string
	 *
	 * @spec exclude One-off OCA\NLDesign -> OCA\Thematiq namespace rename
	 *  plumbing; no capability spec describes the rename.
	 */
	public function getName(): string {
		return 'Rewrite stored OCA\\NLDesign class names to OCA\\Thematiq';
	}//end getName()

	/**
	 * Rewrite the stored mail template class name when it names the old class.
	 *
	 * @param IOutput $output The output interface for progress reporting.
	 *
	 * @return void
	 *
	 * @spec exclude One-off OCA\NLDesign -> OCA\Thematiq namespace rename
	 *  plumbing: it rewrites one stored FQCN and adds no behaviour of its own.
	 *  The email theming it restores is specified where it is read.
	 */
	public function run(IOutput $output): void {
		$configured = $this->configuredClass(output: $output);
		if ($configured === null) {
			return;
		}

		$nothingToDo = $this->nothingToDo(configured: $configured);
		if ($nothingToDo !== null) {
			$output->info($nothingToDo);
			return;
		}

		$this->rewriteMailTemplateClass(output: $output);

	}//end run()

	/**
	 * The stored mail template class, or null when it cannot be read.
	 *
	 * @param IOutput $output The output interface for progress reporting.
	 *
	 * @return string|null The class name, or null after reporting the read failure.
	 */
	private function configuredClass(IOutput $output): ?string {
		try {
			return (string)$this->config->getSystemValue(
				EmailThemingService::MAIL_TEMPLATE_CLASS_KEY,
				''
			);
		} catch (Throwable $e) {
			// A read that throws must not escape run() — see the class
			// docblock: this step is registered under <install>.
			$this->logger->warning(
				'Thematiq: could not read mail_template_class; the stored class name was not migrated',
				['exception' => $e->getMessage()]
			);
			$output->warning(
				'MigrateStoredClassNames: mail_template_class is unreadable; stored class name left as-is.'
			);
			return null;
		}//end try
	}//end configuredClass()

	/**
	 * Why the stored class needs no rewrite, or null when it names the old class.
	 *
	 * @param string $configured The stored mail template class.
	 *
	 * @return string|null The message to report, or null to rewrite.
	 */
	private function nothingToDo(string $configured): ?string {
		if ($configured === '') {
			return 'MigrateStoredClassNames: no mail template configured on this install; nothing to do.';
		}

		if ($configured === NLDesignEMailTemplate::class) {
			return 'MigrateStoredClassNames: mail_template_class already names the thematiq template; nothing to do.';
		}

		// Anything that is neither empty nor either of this app's own class
		// names belongs to somebody else. Never clobber it: an enterprise mail
		// template configured by the admin is not this migration's business.
		if ($configured !== self::OLD_MAIL_TEMPLATE_CLASS) {
			return 'MigrateStoredClassNames: mail_template_class names a foreign template ('
				. $configured . '); left untouched.';
		}

		return null;
	}//end nothingToDo()

	/**
	 * Write the new mail template FQCN, reporting any reason it cannot be done.
	 *
	 * @param IOutput $output The output interface for progress reporting.
	 *
	 * @return void
	 */
	private function rewriteMailTemplateClass(IOutput $output): void {
		if ($this->configIsReadOnly() === true) {
			$this->reportUnwritable(output: $output, reason: 'config.php is read-only');
			return;
		}

		try {
			$this->config->setSystemValue(
				EmailThemingService::MAIL_TEMPLATE_CLASS_KEY,
				NLDesignEMailTemplate::class
			);
		} catch (Throwable $e) {
			$this->reportFailedWrite(output: $output, error: $e);
			return;
		}//end try

		$output->info(
			'MigrateStoredClassNames: mail_template_class rewritten from '
			. self::OLD_MAIL_TEMPLATE_CLASS . ' to ' . NLDesignEMailTemplate::class . '.'
		);

	}//end rewriteMailTemplateClass()

	/**
	 * Log and report a write of the new class name that threw.
	 *
	 * The config_is_read_only flag can miss a filesystem-level read-only
	 * config.php (e.g. chmod 444) — the write itself throws in that case.
	 * Mirrors EmailThemingService::enable().
	 *
	 * @param IOutput $output The output interface for progress reporting.
	 * @param Throwable $error What the write threw.
	 *
	 * @return void
	 */
	private function reportFailedWrite(IOutput $output, Throwable $error): void {
		$this->logger->warning(
			'Thematiq: could not rewrite mail_template_class; branded emails stay disabled until it is set by hand',
			[
				'from' => self::OLD_MAIL_TEMPLATE_CLASS,
				'to' => NLDesignEMailTemplate::class,
				'exception' => $error->getMessage(),
			]
		);
		$this->reportUnwritable(output: $output, reason: 'the write failed: ' . $error->getMessage());
	}//end reportFailedWrite()

	/**
	 * Whether Nextcloud reports config.php as read-only.
	 *
	 * @return bool True when the instance declares config_is_read_only.
	 */
	private function configIsReadOnly(): bool {
		try {
			return $this->config->getSystemValueBool('config_is_read_only', false);
		} catch (Throwable $e) {
			// Unknown rather than false: let the write attempt decide.
			return false;
		}//end try

	}//end configIsReadOnly()

	/**
	 * Tell the admin what to run by hand when the rewrite cannot be persisted.
	 *
	 * The previous value is named explicitly. An admin whose branded email has
	 * silently stopped working has no other way to discover why.
	 *
	 * @param IOutput $output The output interface for progress reporting.
	 * @param string $reason Why the value could not be written.
	 *
	 * @return void
	 */
	private function reportUnwritable(IOutput $output, string $reason): void {
		$output->warning(
			'MigrateStoredClassNames: mail_template_class still names the removed class '
			. self::OLD_MAIL_TEMPLATE_CLASS . ', so branded emails fall back to the stock '
			. 'Nextcloud template and the admin panel reports the template as foreign. It '
			. 'could not be rewritten because ' . $reason . '. Run this by hand: '
			. EmailThemingService::OCC_ENABLE_COMMAND
		);

	}//end reportUnwritable()

}//end class
