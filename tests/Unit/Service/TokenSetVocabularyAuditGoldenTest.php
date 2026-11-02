<?php

/**
 * Golden test for TokenSetVocabularyAuditService::auditSet().
 *
 * @category Test
 * @package  OCA\Thematiq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://github.com/ConductionNL/thematiq
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/token-sets/spec.md#requirement-shipped-token-set-vocabulary-completeness
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\TokenSetVocabularyAuditService;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Audits every shipped token set with its own manifest entry, and with seeded entries: each
 * design system (an own-vocabulary one, an nldesign-reading one, one that reads neither, an
 * unknown one and a non-string), and declared primary colours that agree, disagree, use three
 * digits or capitals, or are not colours at all. Compares each verdict with the recording.
 * Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 *
 * @covers \OCA\Thematiq\Service\TokenSetVocabularyAuditService
 * @uses   \OCA\Thematiq\Service\CssParserService
 */
class TokenSetVocabularyAuditGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/golden/token-set-vocabulary-audit.golden.json';

	private const SYSTEMS = ['nldesign', 'none', 'summer-breeze', 'lasuite', 'high-contrast', 'unknown-system', 7, null];

	private string $directory = '';

	private const PRIMARIES = ['#154273', '#FFF', '#fff', ' #ffffff ', '#12345', 'red', 7, null, '#000'];

	/**
	 * Every case audits to the recorded verdict.
	 *
	 * @return void
	 */
	public function testAuditMatchesTheGoldenRecording(): void {
		$appPath = dirname(__DIR__, 3);
		$service = new TokenSetVocabularyAuditService(new CssParserService());
		$manifest = json_decode((string)file_get_contents($appPath . '/token-sets.json'), true);
		$entries = [];
		foreach ($manifest as $entry) {
			if (is_array($entry) === true && isset($entry['id']) === true) {
				$entries[(string)$entry['id']] = $entry;
			}
		}

		ksort($entries);
		$actual = [];
		foreach ($entries as $id => $entry) {
			$actual['set:' . $id] = (string)json_encode($service->auditSet(appPath: $appPath, id: $id, meta: $entry), JSON_UNESCAPED_SLASHES);
		}

		$ids = array_keys($entries);
		for ($seed = 1; $seed <= 120; $seed++) {
			$random = new Randomizer(new Mt19937($seed));
			$id = $ids[$random->getInt(0, (count($ids) - 1))];
			$meta = [];
			if ($random->getInt(0, 4) !== 0) {
				$meta['design_system'] = self::SYSTEMS[$random->getInt(0, (count(self::SYSTEMS) - 1))];
			}

			$primary = ($entries[$id]['theming']['primary_color'] ?? null);
			if ($random->getInt(0, 1) === 0) {
				$primary = self::PRIMARIES[$random->getInt(0, (count(self::PRIMARIES) - 1))];
			}

			if ($random->getInt(0, 5) !== 0) {
				$meta['theming'] = ['primary_color' => $primary];
			}

			$actual['seed:' . $seed] = (string)json_encode(
				['id' => $id, 'meta' => $meta, 'audit' => $service->auditSet(appPath: $appPath, id: $id, meta: $meta)],
				JSON_UNESCAPED_SLASHES
			);
		}//end for

		$synthetic = $this->syntheticApp(appPath: $appPath);
		$vocabulary = $service->declaredVocabulary(appPath: $appPath);
		for ($seed = 1; $seed <= 90; $seed++) {
			$random = new Randomizer(new Mt19937($seed));
			$css = ":root {\n";
			foreach ($random->pickArrayKeys(array_flip(array_merge($vocabulary, TokenSetVocabularyAuditService::REQUIRED_TOKENS)), $random->getInt(1, 40)) as $name) {
				$css .= "\t" . $name . ': #' . $random->getBytesFromString('0123456789abcdef', 6) . ";\n";
			}

			if ($random->getInt(0, 2) === 0) {
				foreach (TokenSetVocabularyAuditService::REQUIRED_TOKENS as $name) {
					$css .= "\t" . $name . ": #154273;\n";
				}
			}

			for ($i = $random->getInt(0, 3); $i > 0; $i--) {
				$css .= "\t--nldesign-golden-foreign-" . $i . ": 1px;\n";
			}

			$primary = (string)self::PRIMARIES[$random->getInt(0, 5)];
			if ($random->getInt(0, 3) !== 0) {
				$css .= "\t--nldesign-color-primary: " . $primary . ";\n";
			}

			file_put_contents($synthetic . '/css/tokens/golden-' . $seed . '.css', $css . "}\n");
			$meta = ['design_system' => self::SYSTEMS[$random->getInt(0, 3)], 'theming' => ['primary_color' => self::PRIMARIES[$random->getInt(0, (count(self::PRIMARIES) - 1))]]];
			$actual['synthetic:' . $seed] = (string)json_encode(
				['meta' => $meta, 'audit' => $service->auditSet(appPath: $synthetic, id: 'golden-' . $seed, meta: $meta)],
				JSON_UNESCAPED_SLASHES
			);
		}//end for

		if (getenv('THEMATIQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(array_keys($expected), array_keys($actual));
		foreach ($expected as $name => $json) {
			self::assertSame($json, $actual[$name], $name);
		}
	}//end testAuditMatchesTheGoldenRecording()

	/**
	 * Remove the synthetic app directory.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ($this->directory !== '') {
			foreach (glob($this->directory . '/css/tokens/*') as $file) {
				unlink($file);
			}

			rmdir($this->directory . '/css/tokens');
			foreach (glob($this->directory . '/css/*') as $link) {
				unlink($link);
			}

			rmdir($this->directory . '/css');
			unlink($this->directory . '/design-systems.json');
			rmdir($this->directory);
		}

		parent::tearDown();
	}//end tearDown()

	/**
	 * An app directory that links the real stylesheets and design systems, with its own token sets.
	 *
	 * @param string $appPath The real app root.
	 *
	 * @return string The synthetic app root.
	 */
	private function syntheticApp(string $appPath): string {
		$this->directory = sys_get_temp_dir() . '/thematiq-vocabulary-golden-' . getmypid();
		mkdir($this->directory . '/css/tokens', 0777, true);
		symlink($appPath . '/design-systems.json', $this->directory . '/design-systems.json');
		foreach (scandir($appPath . '/css') as $entry) {
			if ($entry !== '.' && $entry !== '..' && $entry !== 'tokens') {
				symlink($appPath . '/css/' . $entry, $this->directory . '/css/' . $entry);
			}
		}

		return $this->directory;
	}//end syntheticApp()
}//end class
