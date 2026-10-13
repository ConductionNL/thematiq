<?php

/**
 * A token set becomes eighteen shell roles, and no role is ever empty.
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
 *
 * @spec openspec/changes/shell-tokens-endpoint/specs/shell-tokens/spec.md#requirement-every-shell-role-always-has-a-value
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\ShellRoleMapper;
use OCA\Thematiq\Service\ShippedTokenSetAuditService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The mapper is pure, so most cases hand it a map and read the roles back.
 * Two cases read the token files this app ships, through the resolver the
 * endpoint uses, so the mapping is checked against real declarations and not
 * only against the names this test happens to spell.
 *
 * @spec openspec/changes/shell-tokens-endpoint/specs/shell-tokens/spec.md#requirement-every-shell-role-always-has-a-value
 */
class ShellRoleMapperTest extends TestCase {

	/**
	 * The mapper under test.
	 *
	 * @return ShellRoleMapper The mapper.
	 */
	private function mapper(): ShellRoleMapper {
		return new ShellRoleMapper(colours: new ContrastService());
	}//end mapper()

	/**
	 * The app directory this test runs in.
	 *
	 * @return string The path.
	 */
	private static function appPath(): string {
		return dirname(__DIR__, 3);
	}//end appPath()

	/**
	 * A shipped set's declarations, resolved the way the endpoint resolves them.
	 *
	 * @param string $id The token set id.
	 *
	 * @return array<string, string> The declarations.
	 */
	private function shipped(string $id): array {
		$resolver = new ShippedTokenSetAuditService(contrast: new ContrastService(), parser: new CssParserService());

		return $resolver->resolveDeclarations(appPath: self::appPath(), id: $id, theming: []);
	}//end shipped()

	/**
	 * Every shipped token set id.
	 *
	 * @return array<string, array{0: string}> The ids, by id.
	 */
	public static function shippedSetIds(): array {
		$ids = [];
		foreach ((array)glob(self::appPath() . '/css/tokens/*.css') as $file) {
			$id = basename((string)$file, '.css');
			$ids[$id] = [$id];
		}

		return $ids;
	}//end shippedSetIds()

	/**
	 * With nothing declared, every role takes its documented default.
	 *
	 * @return void
	 */
	public function testAnEmptySetGetsTheDocumentedDefaults(): void {
		$roles = $this->mapper()->map(tokens: []);

		$this->assertSame(ShellRoleMapper::ROLES, array_keys($roles));
		$this->assertSame(ShellRoleMapper::DEFAULTS, $roles);
		$this->assertCount(18, $roles);
	}//end testAnEmptySetGetsTheDocumentedDefaults()

	/**
	 * Gemeente Zuiddrecht, the set the shell roles were drawn for, maps onto its own colours.
	 *
	 * @return void
	 */
	public function testZuiddrechtMapsOntoItsOwnColours(): void {
		$roles = $this->mapper()->map(tokens: $this->shipped(id: 'zuiddrecht'));

		$font = $roles['font-family'];
		unset($roles['font-family']);

		$this->assertSame(
			[
				'primary' => '#3669A5',
				'primary-deep' => '#234A78',
				'primary-light' => '#EAF0F7',
				'action' => '#3669A5',
				'action-deep' => '#234A78',
				'link' => '#3669A5',
				'accent' => '#CC0000',
				'ink' => '#1B1C1D',
				'text' => '#3d3f43',
				'muted' => '#5E6168',
				'line' => '#E4E6EA',
				'soft' => '#EDEFF2',
				'ground' => '#F5F6F8',
				'chip' => '#F0F1F3',
				'field' => '#8A929E',
				'radius' => '8px',
				'radius-lg' => '12px',
			],
			$roles
		);
		$this->assertStringStartsWith("'Fira Sans', ", $font);
		$this->assertStringNotContainsString("\n", $font);
	}//end testZuiddrechtMapsOntoItsOwnColours()

	/**
	 * Every set this app ships gets all eighteen roles, each a usable value.
	 *
	 * @param string $id The token set id.
	 *
	 * @return void
	 */
	#[DataProvider('shippedSetIds')]
	public function testEveryShippedSetGetsEveryRole(string $id): void {
		$roles = $this->mapper()->map(tokens: $this->shipped(id: $id));

		$this->assertSame(ShellRoleMapper::ROLES, array_keys($roles), $id);
		foreach ($roles as $role => $value) {
			$this->assertNotSame('', $value, $id . ' ' . $role);
			$this->assertStringNotContainsString('var(', $value, $id . ' ' . $role);
			$this->assertDoesNotMatchRegularExpression('/[;{}]/', $value, $id . ' ' . $role);
		}

		foreach (['radius', 'radius-lg'] as $role) {
			$this->assertMatchesRegularExpression('/^[\d.]+(px|rem|em|%)$/', $roles[$role], $id . ' ' . $role);
		}
	}//end testEveryShippedSetGetsEveryRole()

	/**
	 * An administrator's own value of a Nextcloud variable wins over the set's token.
	 *
	 * @return void
	 */
	public function testAnAdministratorsOwnValueWins(): void {
		$roles = $this->mapper()->map(
			tokens: [
				'--nldesign-color-primary' => '#3669a5',
				'--nldesign-color-text' => '#1b1c1d',
				'--nldesign-border-radius' => '8px',
				'--nldesign-font-family' => "'Fira Sans', sans-serif",
			],
			overrides: [
				'--color-primary' => '#aa0000',
				'--color-primary-element' => '#00aa00',
				'--nldesign-component-button-primary-action-background-color' => '#aa00aa',
				'--color-main-text' => '#000000',
				'--border-radius' => '2px',
				'--font-face' => 'Inter, sans-serif',
			]
		);

		$this->assertSame('#aa0000', $roles['primary']);
		$this->assertSame('#aa00aa', $roles['action'], 'the main button follows its own token, not the element variable');
		$this->assertSame('#000000', $roles['ink']);
		$this->assertSame('2px', $roles['radius']);
		$this->assertSame('Inter, sans-serif', $roles['font-family']);
	}//end testAnAdministratorsOwnValueWins()

	/**
	 * The deep roles are the dark step: a hover colour lighter than its base is not taken.
	 *
	 * @return void
	 */
	public function testALighterHoverIsNotTakenAsTheDeepColour(): void {
		$roles = $this->mapper()->map(
			tokens: [
				'--nldesign-color-primary' => '#154273',
				'--nldesign-color-primary-hover' => '#1d5499',
			]
		);

		$this->assertSame('#0f2e51', $roles['primary-deep']);
		$this->assertSame('#0f2e51', $roles['action-deep']);
	}//end testALighterHoverIsNotTakenAsTheDeepColour()

	/**
	 * Roles without a token of their own are derived from the roles next to them.
	 *
	 * @return void
	 */
	public function testMissingRolesAreDerived(): void {
		$roles = $this->mapper()->map(
			tokens: [
				'--nldesign-color-primary' => '#3669a5',
				'--nldesign-color-text' => '#000000',
				'--nldesign-color-text-muted' => '#808080',
				'--nldesign-color-border' => '#cccccc',
				'--nldesign-border-radius' => '4px',
			]
		);

		$this->assertSame('#3669a5', $roles['action'], 'the action colour is the primary colour');
		$this->assertSame('#3669a5', $roles['link'], 'a link without its own colour is primary');
		$this->assertSame('#ebf0f6', $roles['primary-light'], 'primary on white, one part in ten');
		$this->assertSame('#404040', $roles['text'], 'halfway between ink and muted');
		$this->assertSame('#cccccc', $roles['field'], 'a field without its own border wears the line colour');
		$this->assertSame('4px', $roles['radius-lg'], 'the large radius falls back to the radius');
	}//end testMissingRolesAreDerived()

	/**
	 * A value a consumer cannot write into a stylesheet is passed over.
	 *
	 * @return void
	 */
	public function testAnUnusableValueIsPassedOver(): void {
		$roles = $this->mapper()->map(
			tokens: [
				'--nldesign-color-primary' => 'var(--brand-that-nothing-declares)',
				'--nldesign-color-link' => 'red; } body { display: none',
				'--nldesign-color-border' => 'url(https://example.org/x.png)',
				'--nldesign-color-text' => '   ',
				'--nldesign-font-family' => "'Fira Sans',\n\t\tArial,\n\t\tsans-serif",
				'--nldesign-border-radius' => '0',
			]
		);

		$this->assertSame(ShellRoleMapper::DEFAULTS['primary'], $roles['primary']);
		$this->assertSame(ShellRoleMapper::DEFAULTS['primary'], $roles['link']);
		$this->assertSame(ShellRoleMapper::DEFAULTS['line'], $roles['line']);
		$this->assertSame(ShellRoleMapper::DEFAULTS['ink'], $roles['ink']);
		$this->assertSame("'Fira Sans', Arial, sans-serif", $roles['font-family']);
		$this->assertSame('0px', $roles['radius'], 'a bare 0 is given its unit');
	}//end testAnUnusableValueIsPassedOver()

	/**
	 * A colour the mapper cannot do sums on is passed on as it is.
	 *
	 * The deep and light steps are mixed from the primary. A primary written
	 * in a notation the mapper does not parse has no mix, so the deep steps
	 * stay on the colour itself and the light step takes the default.
	 *
	 * @return void
	 */
	public function testAColourThatCannotBeMixedIsNotDerivedFrom(): void {
		$roles = $this->mapper()->map(tokens: ['--nldesign-color-primary' => 'oklch(0.5 0.15 250)']);

		$this->assertSame('oklch(0.5 0.15 250)', $roles['primary']);
		$this->assertSame('oklch(0.5 0.15 250)', $roles['primary-deep']);
		$this->assertSame('oklch(0.5 0.15 250)', $roles['action']);
		$this->assertSame('oklch(0.5 0.15 250)', $roles['action-deep']);
		$this->assertSame(ShellRoleMapper::DEFAULTS['primary-light'], $roles['primary-light']);
	}//end testAColourThatCannotBeMixedIsNotDerivedFrom()

	/**
	 * The accent is the set's accent, and the error colour for a set without one.
	 *
	 * @return void
	 */
	public function testTheAccentFallsBackToTheErrorColour(): void {
		$this->assertSame('#f5b82e', $this->mapper()->map(tokens: ['--nldesign-color-accent' => '#f5b82e', '--nldesign-color-error' => '#cc0000'])['accent']);
		$this->assertSame('#cc0000', $this->mapper()->map(tokens: ['--nldesign-color-error' => '#cc0000'])['accent']);
	}//end testTheAccentFallsBackToTheErrorColour()

	/**
	 * A set's action colour is the action role, apart from its primary.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/action-colour-token/specs/action-colour/spec.md#requirement-the-shell-tokens-answer-the-action-colour
	 */
	public function testASetsActionColourIsTheActionRole(): void {
		$set = [
			'--nldesign-color-primary' => '#1a67a3',
			'--nldesign-color-primary-hover' => '#124872',
			'--nldesign-color-link' => '#175a8e',
		];

		$without = $this->mapper()->map(tokens: $set);
		$this->assertSame('#1a67a3', $without['action'], 'no action colour: the action is the primary');
		$this->assertSame('#124872', $without['action-deep']);

		$full = $this->mapper()->map(tokens: ($set + ['--nldesign-color-action' => '#1f883d', '--nldesign-color-action-hover' => '#197032']));
		$this->assertSame('#1f883d', $full['action']);
		$this->assertSame('#197032', $full['action-deep']);
		$this->assertSame('#1a67a3', $full['primary'], 'the primary does not move with the action');
		$this->assertSame('#124872', $full['primary-deep']);
		$this->assertSame('#175a8e', $full['link'], 'links stay with the set');

		// Without its own hover step the action darkens by 15%, as the page
		// draws it, and never borrows the primary's blue hover.
		$alone = $this->mapper()->map(tokens: ($set + ['--nldesign-color-action' => '#1f883d']));
		$this->assertSame('#1a7434', $alone['action-deep']);

		// An administrator's value for Nextcloud's element variable moves checked
		// boxes and switches, not the main button, so the action role ignores it.
		$element = $this->mapper()->map(tokens: ($set + ['--nldesign-color-action' => '#1f883d']), overrides: ['--color-primary-element' => '#aa0000']);
		$this->assertSame('#1f883d', $element['action']);

		// An administrator's value for the main button's own token wins.
		$button = $this->mapper()->map(
			tokens: ($set + ['--nldesign-color-action' => '#1f883d']),
			overrides: ['--nldesign-component-button-primary-action-background-color' => '#aa0000']
		);
		$this->assertSame('#aa0000', $button['action']);
		$this->assertSame('#910000', $button['action-deep'], 'and hovers a little darker');
	}//end testASetsActionColourIsTheActionRole()

	/**
	 * On a dark page the text on the selected item's tint must read: a darker step that does not is passed over.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/shell-catalogue-dark-roles/specs/shell-catalogue/spec.md#requirement-the-dark-roles-read-on-each-other
	 */
	public function testOnADarkPageTheSelectedItemsTextReads(): void {
		$dark = [
			'--nldesign-color-primary' => '#84aad7',
			'--nldesign-color-primary-hover' => '#234a78',
			'--nldesign-color-primary-light' => '#13202e',
			'--nldesign-color-text' => '#e2e3e4',
			'--nldesign-color-background' => '#171a20',
		];

		$this->assertSame('#234a78', $this->mapper()->map(tokens: $dark)['primary-deep'], 'the plain mapping keeps the darker step');

		$roles = $this->mapper()->mapDark(tokens: $dark);
		$this->assertSame('#84aad7', $roles['primary-deep'], 'the dark mapping writes the primary on the tint, as Nextcloud does');
		$this->assertSame('#84aad7', $roles['primary']);
		$this->assertSame(ShellRoleMapper::ROLES, array_keys($roles));

		// A step that already reads is left alone.
		$fine = $this->mapper()->mapDark(tokens: (['--nldesign-color-primary-hover' => '#7397c4'] + $dark));
		$this->assertSame('#7397c4', $fine['primary-deep']);
	}//end testOnADarkPageTheSelectedItemsTextReads()

	/**
	 * Summer Breeze keeps its own vocabulary, and is read by it.
	 *
	 * @return void
	 */
	public function testSummerBreezeIsReadByItsOwnNames(): void {
		$roles = $this->mapper()->map(tokens: $this->shipped(id: 'summer-breeze'));

		$this->assertSame('#21468b', $roles['primary']);
		$this->assertSame('#1b3a75', $roles['primary-deep']);
		$this->assertSame('#21468b', $roles['link']);
		$this->assertSame('#02162e', $roles['ink']);
		$this->assertSame('#e3e9f0', $roles['line']);
		$this->assertSame('#7a8b9f', $roles['field']);
		$this->assertSame('#f7f7f7', $roles['ground']);
		$this->assertSame('12px', $roles['radius']);
		$this->assertStringStartsWith("'Inter', ", $roles['font-family']);
	}//end testSummerBreezeIsReadByItsOwnNames()
}//end class
