<?php

/**
 * The token editor's lists live in one place.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Test
 * @package   OCA\Thematiq\Tests\Unit\Service
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\TokenRegistry;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * StatusTokens, LoginTokens, ContentTokens and TypographyTokens each held a
 * copy of a TokenRegistry list that nothing read (#665). Two had drifted
 * already: an edit there changed nothing on screen. This fails when any other
 * file in lib/ defines an editor row for a brand token again.
 *
 * @spec openspec/specs/token-editor-ui/spec.md
 */
class TokenRegistrySingleSourceTest extends TestCase {

	/**
	 * No file but TokenRegistry.php defines a brand token's editor row.
	 *
	 * @return void
	 */
	public function testNoOtherFileDefinesABrandTokenRow(): void {
		$lib = dirname(__DIR__, 3) . '/lib';
		$tokens = array_keys(TokenRegistry::getBrandTokens());
		$this->assertContains('--color-error', $tokens);

		$copies = [];
		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($lib, RecursiveDirectoryIterator::SKIP_DOTS));
		foreach ($files as $file) {
			if ($file->getExtension() !== 'php' || $file->getFilename() === 'TokenRegistry.php') {
				continue;
			}

			$source = (string)file_get_contents($file->getPathname());
			foreach ($tokens as $token) {
				if (preg_match('/[\'"]' . preg_quote($token, '/') . '[\'"]\s*=>\s*\[\s*[\'"]tab[\'"]/', $source) === 1) {
					$copies[] = substr($file->getPathname(), strlen($lib) + 1) . ': ' . $token;
				}
			}
		}

		$this->assertSame([], $copies, 'Token editor rows belong in TokenRegistry alone.');
	}//end testNoOtherFileDefinesABrandTokenRow()
}//end class
