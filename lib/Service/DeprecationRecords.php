<?php

/**
 * Thematiq deprecation record storage.
 *
 * Reads and writes appconfig `token_deprecations`, and turns each record into the
 * comment written above a deprecated own token. Shared by
 * {@see TokenDeprecationService} (which checks and changes records) and
 * {@see OwnTokenService} (which renders the comments), so neither depends on
 * the other's rules.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Service
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/token-deprecations/spec.md#requirement-the-served-stylesheet-names-each-deprecated-own-token
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\IConfig;

/**
 * The stored deprecation records.
 *
 * @spec openspec/specs/token-deprecations/spec.md#requirement-the-served-stylesheet-names-each-deprecated-own-token
 */
class DeprecationRecords {

	/**
	 * The appconfig key holding the records as a JSON object keyed by token name.
	 *
	 * @var string
	 */
	public const CONFIG_KEY = 'token_deprecations';

	/**
	 * Constructor.
	 *
	 * @param IConfig $config The app config store.
	 */
	public function __construct(
		private readonly IConfig $config,
	) {
	}//end __construct()

	/**
	 * The stored records.
	 *
	 * @return array<string, array<string, mixed>> Token name => record.
	 *
	 * @spec openspec/specs/token-deprecations/spec.md#requirement-an-administrator-deprecates-a-token
	 */
	public function all(): array {
		$decoded = json_decode($this->config->getAppValue(Application::APP_ID, self::CONFIG_KEY, '{}'), true);
		if (is_array($decoded) === false) {
			return [];
		}

		return array_filter($decoded, 'is_array');
	}//end all()

	/**
	 * Store the records.
	 *
	 * @param array<string, array<string, mixed>> $records Token name => record.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/token-deprecations/spec.md#requirement-an-administrator-deprecates-a-token
	 */
	public function save(array $records): void {
		ksort($records);
		$this->config->setAppValue(Application::APP_ID, self::CONFIG_KEY, (string)json_encode($records, JSON_UNESCAPED_SLASHES));
	}//end save()

	/**
	 * The notice written above each deprecated token, as in
	 * `deprecated (warning): use --nldesign-org-brand-accent, removal 2027-03-01`.
	 *
	 * @return array<string, string> Token name => comment text.
	 *
	 * @spec openspec/specs/token-deprecations/spec.md#requirement-the-served-stylesheet-names-each-deprecated-own-token
	 */
	public function comments(): array {
		$comments = [];
		foreach ($this->all() as $token => $record) {
			$parts = [];
			if (($record['replacement'] ?? '') !== '') {
				$parts[] = 'use ' . $record['replacement'];
			}

			if (($record['removalDate'] ?? '') !== '') {
				$parts[] = 'removal ' . $record['removalDate'];
			}

			$comments[$token] = rtrim('deprecated (' . (string)($record['severity'] ?? 'warning') . '): ' . implode(', ', $parts), ': ');
		}

		return $comments;
	}//end comments()
}//end class
