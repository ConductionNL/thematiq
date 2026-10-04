#!/usr/bin/env node

/**
 * Generate NL Design Token CSS Files
 *
 * Reads JSON token files from the nl-design-system/themes repository
 * and generates CSS token files with --nldesign-* prefixed variables.
 *
 * Usage:
 *   node scripts/generate-tokens.mjs /path/to/themes [themesCommitSha]
 *
 * The themes repo should be cloned from:
 *   https://github.com/nl-design-system/themes
 *
 * themesCommitSha (2nd arg, falls back to THEMES_COMMIT_SHA env var) is the
 * commit SHA of the themes-repo checkout being processed. When provided, it
 * is recorded as `upstreamRef` on every entry this run (re)generates — the
 * comparison baseline the deployed instances' upstream-freshness background
 * job (openspec/specs/upstream-freshness/spec.md) checks against. Each
 * entry also records `upstreamVersion` when the org's package declares one
 * (package.json "version", falling back to config.json "version"). Both
 * fields are optional: omitted entirely when not resolvable, and never
 * written for hand-authored entries this script does not (re)generate.
 */

import { readFileSync, writeFileSync, existsSync, mkdirSync, readdirSync, statSync } from 'fs';
import { join, dirname, resolve } from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const TOKENS_OUTPUT_DIR = join(__dirname, '..', 'css', 'tokens');
const TOKEN_SETS_PATH = join(__dirname, '..', 'token-sets.json');

/**
 * Recursively find all *.tokens.json files in a directory.
 */
function findTokenFiles(dir) {
	const results = [];
	if (!existsSync(dir) || !statSync(dir).isDirectory()) return results;

	for (const entry of readdirSync(dir)) {
		const fullPath = join(dir, entry);
		const stat = statSync(fullPath);
		if (stat.isDirectory()) {
			results.push(...findTokenFiles(fullPath));
		} else if (entry.endsWith('.tokens.json')) {
			results.push(fullPath);
		}
	}
	return results;
}

/**
 * Flatten a nested token object into flat key-value pairs.
 * Leaf nodes have a "value" or "$value" property.
 * Skips tokens with empty values or reference values like "{org.token.path}".
 */
function flattenTokens(obj, prefix = '') {
	const result = [];

	for (const [key, value] of Object.entries(obj)) {
		// Skip metadata keys
		if (key.startsWith('$') || key === 'comment') continue;

		const currentPath = prefix ? `${prefix}-${key}` : key;

		if (value && typeof value === 'object' && !Array.isArray(value)) {
			if ('value' in value || '$value' in value) {
				const tokenValue = value.value ?? value.$value;
				// Skip empty, null, or reference values (e.g., "{leiden.color.primary}")
				if (tokenValue && typeof tokenValue === 'string' && tokenValue.trim() !== '') {
					if (tokenValue.startsWith('{') && tokenValue.endsWith('}')) {
						// This is a reference — skip it since we can't resolve it in plain CSS
						continue;
					}
					result.push({ path: currentPath, value: tokenValue });
				}
			} else {
				// Nested object, recurse
				result.push(...flattenTokens(value, currentPath));
			}
		}
	}

	return result;
}

/**
 * Determine the CSS variable name for a token.
 * Strips org/framework prefixes and applies --nldesign-* or --nldesign-component-*.
 */
function toCSSVar(tokenPath, orgPrefixes) {
	let cleanPath = tokenPath;

	// Remove known org/framework prefixes from the beginning
	const allPrefixes = [...orgPrefixes, 'utrecht', 'denhaag', 'den-haag', 'amsterdam', 'ams'];
	for (const prefix of allPrefixes) {
		if (cleanPath.toLowerCase().startsWith(prefix.toLowerCase() + '-')) {
			cleanPath = cleanPath.substring(prefix.length + 1);
			break;
		}
	}

	// Determine if this is a component token or a brand token
	// Component tokens are things like button-*, heading-*, textbox-*, etc.
	const componentPrefixes = [
		'button', 'textbox', 'textarea', 'form-field', 'form-select', 'form-fieldset',
		'form-input', 'heading', 'paragraph', 'link', 'table', 'badge', 'separator',
		'ordered-list', 'unordered-list', 'alert', 'breadcrumb', 'breadcrumb-nav',
		'checkbox', 'radio-button', 'select', 'data-list', 'document',
		'page-header', 'page-footer', 'page', 'navigation', 'skip-link',
		'status-badge', 'number-badge', 'badge-counter', 'icon', 'img', 'figure',
		'blockquote', 'code', 'pre-formatted', 'mark-up', 'list',
		'accordion', 'modal-dialog', 'avatar', 'description-list',
		'password-input', 'file-input', 'card',
	];

	const isComponent = componentPrefixes.some(
		(cp) => cleanPath.startsWith(cp + '-') || cleanPath === cp
	);

	if (isComponent) {
		return `--nldesign-component-${cleanPath}`;
	}

	return `--nldesign-${cleanPath}`;
}

/**
 * Extract org ID from directory name (e.g., "amsterdam-design-tokens" → "amsterdam").
 */
function extractOrgId(dirName) {
	return dirName
		.replace(/-design-tokens$/, '')
		.toLowerCase()
		.replace(/\s+/g, '-');
}

/**
 * Read config.json for an organization to get display name.
 */
function readOrgConfig(orgDir) {
	const configPath = join(orgDir, 'src', 'config.json');
	if (existsSync(configPath)) {
		try {
			return JSON.parse(readFileSync(configPath, 'utf-8'));
		} catch {
			return null;
		}
	}
	return null;
}

/**
 * Resolve the upstream theme package's version for provenance, when the
 * upstream package declares one. Tries the org's own package.json first
 * (npm-package convention), then falls back to a "version" key on
 * config.json. Returns null when neither is present or parseable — the
 * upstreamVersion field is then simply omitted (optional per the
 * token-sets manifest schema).
 */
function readOrgVersion(orgDir, config) {
	const pkgPath = join(orgDir, 'package.json');
	if (existsSync(pkgPath)) {
		try {
			const pkg = JSON.parse(readFileSync(pkgPath, 'utf-8'));
			if (typeof pkg.version === 'string' && pkg.version.trim() !== '') {
				return pkg.version.trim();
			}
		} catch {
			// Fall through to the config.json fallback below.
		}
	}

	if (config && typeof config.version === 'string' && config.version.trim() !== '') {
		return config.version.trim();
	}

	return null;
}

/**
 * Generate a CSS file for a single organization.
 */
function generateOrgCSS(orgId, displayName, tokens, orgPrefixes) {
	const lines = [
		`/**`,
		` * ${displayName} Design Tokens`,
		` *`,
		` * Auto-generated from nl-design-system/themes repository.`,
		` * Do not edit manually — changes will be overwritten by the sync workflow.`,
		` *`,
		` * Source: https://github.com/nl-design-system/themes`,
		` */`,
		``,
		`:root {`,
	];

	// Categorize tokens
	const nldesignTokens = [];
	const orgPaletteTokens = [];

	for (const token of tokens) {
		const cssVar = toCSSVar(token.path, orgPrefixes);
		const value = typeof token.value === 'string' ? token.value : String(token.value);

		nldesignTokens.push({ name: cssVar, value });

		// Also preserve as org-specific palette token if it starts with org prefix
		for (const prefix of orgPrefixes) {
			if (token.path.toLowerCase().startsWith(prefix.toLowerCase() + '-')) {
				const orgPath = token.path;
				orgPaletteTokens.push({ name: `--${orgPath}`, value });
				break;
			}
		}
	}

	// Deduplicate by CSS variable name (later entries win)
	const seen = new Map();
	for (const t of nldesignTokens) {
		seen.set(t.name, t);
	}
	const uniqueTokens = [...seen.values()];

	if (uniqueTokens.length > 0) {
		lines.push(`\t/* NL Design tokens */`);
		for (const t of uniqueTokens) {
			lines.push(`\t${t.name}: ${t.value};`);
		}
	}

	// Org palette (deduplicated)
	const seenOrg = new Map();
	for (const t of orgPaletteTokens) {
		seenOrg.set(t.name, t);
	}
	const uniqueOrgTokens = [...seenOrg.values()];

	if (uniqueOrgTokens.length > 0) {
		lines.push(``);
		lines.push(`\t/* ${displayName} palette */`);
		for (const t of uniqueOrgTokens) {
			lines.push(`\t${t.name}: ${t.value};`);
		}
	}

	lines.push(`}`);
	lines.push(``);

	return lines.join('\n');
}

/**
 * Main entry point.
 */
/**
 * Merge freshly generated entries into the existing manifest.
 *
 * Every existing entry is kept, in its original order: sets that live only in
 * this repository (the stock nextcloud set, Conduction's own, the examples)
 * have no upstream directory and are never regenerated. On a regenerated
 * entry only the upstream provenance is refreshed; the name and description
 * are taken from the run only when the entry had none, and every other field
 * (design_system, theming, logos) is kept, because the dark-variant generator
 * reads the backgrounds from it. Organisations new upstream are appended.
 *
 * @param {Array<object>} existing The current token-sets.json entries.
 * @param {Array<object>} regenerated The entries this run generated.
 * @return {Array<object>} The manifest to write.
 */
export function mergeManifest(existing, regenerated) {
	const fresh = new Map(regenerated.map((entry) => [entry.id, entry]))
	const out = existing.map((entry) => {
		const update = fresh.get(entry.id)
		if (update === undefined) {
			return entry
		}
		fresh.delete(entry.id)
		const merged = { ...entry, name: entry.name || update.name, description: entry.description || update.description }
		for (const key of ['upstreamRef', 'upstreamVersion']) {
			if (update[key] !== undefined) {
				merged[key] = update[key]
			}
		}
		return merged
	})
	return out.concat([...fresh.values()])
}

function main() {
	const themesPath = process.argv[2];
	const upstreamRef = process.argv[3] || process.env.THEMES_COMMIT_SHA || null;

	if (!themesPath) {
		console.error('Usage: node scripts/generate-tokens.mjs /path/to/themes [themesCommitSha]');
		console.error('');
		console.error('Clone the themes repo first:');
		console.error('  git clone https://github.com/nl-design-system/themes.git');
		process.exit(1);
	}

	if (!upstreamRef) {
		console.warn(
			'Warning: no themes-repo commit SHA provided (2nd arg or THEMES_COMMIT_SHA env var); ' +
			'generated entries will have no upstreamRef and will be excluded from the upstream-freshness check.'
		);
	}

	const resolvedPath = resolve(themesPath);
	const proprietaryDir = join(resolvedPath, 'proprietary');

	if (!existsSync(proprietaryDir)) {
		console.error(`Error: proprietary/ directory not found at ${proprietaryDir}`);
		console.error('Make sure the path points to the root of the nl-design-system/themes repo.');
		process.exit(1);
	}

	// Ensure output directory exists
	if (!existsSync(TOKENS_OUTPUT_DIR)) {
		mkdirSync(TOKENS_OUTPUT_DIR, { recursive: true });
	}

	// Load existing token-sets.json for metadata preservation
	let existingManifest = {};
	if (existsSync(TOKEN_SETS_PATH)) {
		try {
			const raw = JSON.parse(readFileSync(TOKEN_SETS_PATH, 'utf-8'));
			if (Array.isArray(raw)) {
				for (const entry of raw) {
					existingManifest[entry.id] = entry;
				}
			}
		} catch {
			console.warn('Warning: Could not parse existing token-sets.json, starting fresh.');
		}
	}

	// Scan proprietary/ for organization directories
	const orgDirs = readdirSync(proprietaryDir).filter((name) => {
		const dirPath = join(proprietaryDir, name);
		return statSync(dirPath).isDirectory() && !name.startsWith('.');
	});

	console.log(`Found ${orgDirs.length} organizations in ${proprietaryDir}\n`);

	const regenerated = [];
	let generated = 0;
	let skipped = 0;
	let warnings = 0;

	for (const orgDirName of orgDirs.sort()) {
		const orgDir = join(proprietaryDir, orgDirName);
		const orgId = extractOrgId(orgDirName);
		const config = readOrgConfig(orgDir);
		const displayName = config?.fullName || config?.name || orgId.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

		// Build list of known prefixes for this org (used to strip from token paths)
		const orgPrefixes = [orgId, orgId.replace(/-/g, '')];
		if (config?.prefix) orgPrefixes.push(config.prefix);
		if (config?.name) orgPrefixes.push(config.name.toLowerCase());

		// Find all *.tokens.json files recursively under src/
		const srcDir = join(orgDir, 'src');
		const tokenFiles = findTokenFiles(srcDir);

		if (tokenFiles.length === 0) {
			console.log(`  SKIP ${orgDirName}: no *.tokens.json files found`);
			skipped++;
			continue;
		}

		// Parse all token files
		let allTokens = [];
		let errorCount = 0;

		for (const tokenFile of tokenFiles) {
			try {
				const raw = readFileSync(tokenFile, 'utf-8');
				const json = JSON.parse(raw);
				const tokens = flattenTokens(json);
				allTokens.push(...tokens);
			} catch (e) {
				const relPath = tokenFile.replace(orgDir + '/', '');
				console.warn(`  WARN ${orgDirName}: malformed JSON in ${relPath} — ${e.message}`);
				errorCount++;
				warnings++;
			}
		}

		if (errorCount > 0 && allTokens.length === 0) {
			console.warn(`  SKIP ${orgDirName}: all token files malformed`);
			skipped++;
			// Preserve the existing manifest entry (name, description, and
			// provenance) unchanged: a transient malformed upstream file must
			// not silently delete this organization from token-sets.json, and
			// its stale upstreamRef/upstreamVersion must be left exactly as
			// recorded so the upstream-freshness check keeps reporting it as
			// outdated rather than losing track of it entirely.
			// (mergeManifest keeps the existing entry: nothing is regenerated here.)
			continue;
		}

		if (allTokens.length === 0) {
			console.log(`  SKIP ${orgDirName}: no concrete token values extracted (all may be references)`);
			skipped++;
			continue;
		}

		// Generate CSS
		const css = generateOrgCSS(orgId, displayName, allTokens, orgPrefixes);
		const outputFile = join(TOKENS_OUTPUT_DIR, `${orgId}.css`);
		writeFileSync(outputFile, css);
		generated++;

		console.log(`  OK   ${orgDirName} → ${orgId}.css (${allTokens.length} tokens from ${tokenFiles.length} files)`);

		// The fields this run owns; mergeManifest keeps everything else.
		const entry = {
			id: orgId,
			name: displayName,
			description: `Design tokens for ${displayName}`,
		};

		// Upstream provenance (upstream-freshness spec): only recorded for
		// entries this run actually (re)generated, and only when resolvable —
		// both fields are optional and simply omitted otherwise.
		if (upstreamRef) {
			entry.upstreamRef = upstreamRef;
		}

		const upstreamVersion = readOrgVersion(orgDir, config);
		if (upstreamVersion) {
			entry.upstreamVersion = upstreamVersion;
		}

		regenerated.push(entry);
	}

	// Write token-sets.json: every existing entry stays, in its order and with
	// the fields this script does not own (design_system, theming, logos).
	const manifest = mergeManifest(Object.values(existingManifest), regenerated);
	writeFileSync(TOKEN_SETS_PATH, JSON.stringify(manifest, null, '\t') + '\n');

	console.log(`\nDone: ${generated} generated, ${skipped} skipped, ${warnings} warnings`);
	console.log(`Token sets manifest: ${TOKEN_SETS_PATH}`);
}

// Run only when executed directly, so tests can import mergeManifest.
if (process.argv[1] !== undefined && resolve(process.argv[1]) === __filename) {
	main();
}
