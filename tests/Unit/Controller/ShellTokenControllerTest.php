<?php

/**
 * GET /api/shell-tokens: public, rate limited, and answered with 304 when unchanged.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Test
 * @package   OCA\Thematiq\Tests\Unit\Controller
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/changes/shell-tokens-endpoint/specs/shell-tokens/spec.md#requirement-another-product-reads-the-active-shell-tokens
 * @spec openspec/changes/shell-tokens-endpoint/specs/shell-tokens/spec.md#requirement-an-unchanged-document-is-answered-with-304
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\ShellTokenController;
use OCA\Thematiq\Service\ShellRoleMapper;
use OCA\Thematiq\Service\ShellTokenService;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Three concerns: the auth posture the route declares, the document it
 * answers with, and the conditional request.
 *
 * @spec openspec/changes/shell-tokens-endpoint/specs/shell-tokens/spec.md#requirement-another-product-reads-the-active-shell-tokens
 */
class ShellTokenControllerTest extends TestCase {

	/**
	 * The document the service answers with in these tests.
	 *
	 * @return array<string, mixed> The document.
	 */
	private function document(): array {
		return [
			'version' => 1,
			'id' => 'zuiddrecht',
			'name' => 'Gemeente Zuiddrecht',
			'revision' => '0123456789abcdef',
			'tokens' => ShellRoleMapper::DEFAULTS,
			'logo' => ['url' => 'https://cloud.example.org/custom_apps/thematiq/img/logos/zuiddrecht.svg', 'inverse_url' => null],
		];
	}//end document()

	/**
	 * Build the controller for a request with this `If-None-Match` header.
	 *
	 * @param string $ifNoneMatch The header value, empty when it is not sent.
	 *
	 * @return ShellTokenController The controller.
	 */
	private function controller(string $ifNoneMatch = ''): ShellTokenController {
		$request = $this->createMock(IRequest::class);
		$request->method('getHeader')->willReturnCallback(
			static function (string $name) use ($ifNoneMatch): string {
				if ($name === 'If-None-Match') {
					return $ifNoneMatch;
				}

				return '';
			}
		);

		$service = $this->createMock(ShellTokenService::class);
		$service->method('document')->willReturn($this->document());

		return new ShellTokenController(request: $request, shellTokens: $service);
	}//end controller()

	/**
	 * The headers a response was given, without asking the server container for the defaults.
	 *
	 * @param Response $response The response.
	 *
	 * @return array<string, string> The headers.
	 */
	private function headers(Response $response): array {
		return (new ReflectionProperty(Response::class, 'headers'))->getValue($response);
	}//end headers()

	/**
	 * `If-None-Match` values that name the current revision.
	 *
	 * @return array<string, array{0: string}> The header values.
	 */
	public static function matchingValidators(): array {
		return [
			'quoted' => ['"0123456789abcdef"'],
			'weak' => ['W/"0123456789abcdef"'],
			'in a list' => ['"an-older-one", "0123456789abcdef"'],
			'any' => ['*'],
			'unquoted' => ['0123456789abcdef'],
		];
	}//end matchingValidators()

	/**
	 * `If-None-Match` values that do not name the current revision.
	 *
	 * @return array<string, array{0: string}> The header values.
	 */
	public static function staleValidators(): array {
		return [
			'not sent' => [''],
			'an older revision' => ['"fedcba9876543210"'],
			'a prefix' => ['"0123456789abcde"'],
			'a list of older ones' => ['"one", W/"two"'],
		];
	}//end staleValidators()

	/**
	 * The route is registered and reaches the controller method.
	 *
	 * @return void
	 */
	public function testTheRouteIsRegistered(): void {
		$routes = require __DIR__ . '/../../../appinfo/routes.php';

		$this->assertContains(['name' => 'shellToken#show', 'url' => '/api/shell-tokens', 'verb' => 'GET'], $routes['routes']);
		$this->assertTrue(method_exists(ShellTokenController::class, 'show'));
	}//end testTheRouteIsRegistered()

	/**
	 * A server without a Nextcloud session can read it: public, no CSRF token, and a ceiling.
	 *
	 * @return void
	 */
	public function testTheRouteIsPublicAndRateLimited(): void {
		$method = new ReflectionMethod(ShellTokenController::class, 'show');

		$this->assertCount(1, $method->getAttributes(PublicPage::class), 'a reader without a session must not be refused');
		$this->assertCount(1, $method->getAttributes(NoCSRFRequired::class), 'a server sends no CSRF token');
		$this->assertCount(0, $method->getAttributes(NoAdminRequired::class), 'one posture, not two');

		$limits = $method->getAttributes(AnonRateLimit::class);
		$this->assertCount(1, $limits, 'a public route carries a ceiling');
		$this->assertSame(['limit' => 120, 'period' => 60], $limits[0]->getArguments());
	}//end testTheRouteIsPublicAndRateLimited()

	/**
	 * The answer is the service's document, unchanged, with its revision as the ETag.
	 *
	 * @return void
	 */
	public function testTheDocumentIsAnsweredWithItsRevisionAsETag(): void {
		$response = $this->controller()->show();

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(200, $response->getStatus());
		$this->assertSame($this->document(), $response->getData());
		$this->assertSame(['version', 'id', 'name', 'revision', 'tokens', 'logo'], array_keys($response->getData()));
		$this->assertSame(ShellRoleMapper::ROLES, array_keys($response->getData()['tokens']));
		$this->assertSame('0123456789abcdef', $response->getETag());
		$this->assertSame('no-cache', $this->headers(response: $response)['Cache-Control']);
	}//end testTheDocumentIsAnsweredWithItsRevisionAsETag()

	/**
	 * A reader that holds the current revision gets 304 and no body.
	 *
	 * @param string $ifNoneMatch The `If-None-Match` header.
	 *
	 * @return void
	 */
	#[DataProvider('matchingValidators')]
	public function testAnUnchangedDocumentIsAnsweredWith304(string $ifNoneMatch): void {
		$response = $this->controller(ifNoneMatch: $ifNoneMatch)->show();

		$this->assertSame(304, $response->getStatus());
		$this->assertNotInstanceOf(JSONResponse::class, $response);
		$this->assertSame('', (string)$response->render());
		$this->assertSame('0123456789abcdef', $response->getETag());
		$this->assertSame('no-cache', $this->headers(response: $response)['Cache-Control']);
	}//end testAnUnchangedDocumentIsAnsweredWith304()

	/**
	 * A reader that holds another revision, or none, gets the document.
	 *
	 * @param string $ifNoneMatch The `If-None-Match` header.
	 *
	 * @return void
	 */
	#[DataProvider('staleValidators')]
	public function testAChangedDocumentIsAnsweredInFull(string $ifNoneMatch): void {
		$response = $this->controller(ifNoneMatch: $ifNoneMatch)->show();

		$this->assertSame(200, $response->getStatus());
		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame('zuiddrecht', $response->getData()['id']);
	}//end testAChangedDocumentIsAnsweredInFull()
}//end class
