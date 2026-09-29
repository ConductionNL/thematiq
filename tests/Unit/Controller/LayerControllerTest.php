<?php

/**
 * Unit tests for LayerController: the stylesheet layer manifest the admin
 * panel applies a token set with, without reloading the page.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/apply-without-reload/specs/css-architecture/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\LayerController;
use OCA\Thematiq\Service\CssInjectionService;
use OCA\Thematiq\Service\TokenSetService;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

/**
 * The controller is deliberately thin — validate the id, then hand back the
 * cascade owner's own layer list — and both halves of that are worth pinning.
 *
 * The id check has to come FIRST: `getStylesheetManifest()` resolves a design
 * system and builds hrefs for whatever string it is handed, so an unvalidated
 * id would be answered with a plausible-looking manifest of links to files
 * that do not exist, which the client would then insert into the page. The
 * 404 is what stops that, and a test that only checked the happy path would
 * not notice if the two lines were ever reordered.
 *
 * The manifest itself is passed through VERBATIM, which is the point of the
 * endpoint existing at all: `CssInjectionService` is the single owner of the
 * cascade, and the client asks it rather than keeping a second copy of the
 * layer order in JavaScript. So the assertion is identity, not shape.
 *
 * @spec openspec/changes/apply-without-reload/specs/css-architecture/spec.md
 */
class LayerControllerTest extends TestCase {

	/**
	 * The cascade owner mock.
	 *
	 * @var CssInjectionService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $injection;

	/**
	 * The token set catalogue mock.
	 *
	 * @var TokenSetService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $tokenSets;

	/**
	 * The controller under test.
	 *
	 * @var LayerController
	 */
	private LayerController $controller;

	/**
	 * Build the controller over two mocked services.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->injection = $this->createMock(CssInjectionService::class);
		$this->tokenSets = $this->createMock(TokenSetService::class);

		$this->controller = new LayerController(
			'thematiq',
			$this->createMock(IRequest::class),
			$this->injection,
			$this->tokenSets
		);
	}//end setUp()

	/**
	 * A known id is answered with the cascade owner's manifest, unchanged.
	 *
	 * @spec openspec/changes/apply-without-reload/specs/css-architecture/spec.md
	 */
	public function testKnownTokenSetReturnsTheManifestVerbatim(): void {
		$manifest = [
			'tokenSet' => 'rijkshuisstijl',
			'designSystem' => 'nldesign',
			'layers' => [
				['layer' => 'system', 'kind' => 'file', 'href' => '/apps/thematiq/css/systems/nldesign/defaults.css?v=3.4.0'],
				['layer' => 'tokens', 'kind' => 'file', 'href' => '/apps/thematiq/css/tokens/rijkshuisstijl.css?v=3.4.0'],
				['layer' => 'logo-url', 'kind' => 'inline', 'id' => 'thematiq-logo-url', 'css' => ':root{--nldesign-logo-url:url(x)}'],
			],
		];

		$this->tokenSets->method('isValidTokenSet')->willReturn(true);
		$this->injection->expects($this->once())
			->method('getStylesheetManifest')
			->with('rijkshuisstijl')
			->willReturn($manifest);

		$response = $this->controller->getStylesheets(tokenSetId: 'rijkshuisstijl');

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(200, $response->getStatus());
		$this->assertSame($manifest, $response->getData());
	}//end testKnownTokenSetReturnsTheManifestVerbatim()

	/**
	 * An unknown id is a 404 — and, more importantly, the manifest is never
	 * built for it. A manifest of links to files that do not exist is exactly
	 * what the client would insert into the live page.
	 *
	 * @spec openspec/changes/apply-without-reload/specs/css-architecture/spec.md
	 */
	public function testUnknownTokenSetIs404AndBuildsNoManifest(): void {
		$this->tokenSets->method('isValidTokenSet')->willReturn(false);
		$this->injection->expects($this->never())->method('getStylesheetManifest');

		$response = $this->controller->getStylesheets(tokenSetId: 'no-such-set');

		$this->assertSame(404, $response->getStatus());
		$this->assertSame(['error' => 'Token set not found'], $response->getData());
	}//end testUnknownTokenSetIs404AndBuildsNoManifest()

	/**
	 * The id the client asked for is the id that is validated — not a
	 * normalised, defaulted or active-set substitute. Reading the active set
	 * here would make the endpoint answer about a different theme than the one
	 * the admin is previewing.
	 *
	 * @spec openspec/changes/apply-without-reload/specs/css-architecture/spec.md
	 */
	public function testTheRequestedIdIsTheOneValidated(): void {
		$this->tokenSets->expects($this->once())
			->method('isValidTokenSet')
			->with('custom-gemeente-voorbeeld')
			->willReturn(true);

		$this->injection->method('getStylesheetManifest')->willReturn(
			['tokenSet' => 'custom-gemeente-voorbeeld', 'designSystem' => 'nldesign', 'layers' => []]
		);

		$this->controller->getStylesheets(tokenSetId: 'custom-gemeente-voorbeeld');
	}//end testTheRequestedIdIsTheOneValidated()
}//end class
