/**
 * Thematiq landing page.
 *
 * Composes the brand <DetailHero> + <WidgetShelf> from
 * @conduction/docusaurus-preset/components, mirroring the connext page
 * at sites/www/src/pages/apps/thematiq.mdx.
 *
 * Written as .js (not .mdx) because the docs site has the docs plugin
 * pointed at `path: './'`, and an MDX file in src/pages/ trips the
 * MDX-ESM parser even with the docs plugin's `src/**` exclude — likely
 * a quirk of how mdx-loader's micromark stack reuses parser state
 * across files in this Docusaurus 3.10 + this preset combination.
 * Authoring the page in JSX keeps the same component composition.
 */

import React from 'react';
import Layout from '@theme/Layout';
import {
  DetailHero,
  WidgetShelf,
  AppMock,
} from '@conduction/docusaurus-preset/components';

const THEMATIQ_ICON = (
  <svg viewBox="0 0 24 24">
    <path d="M4 4h16v6H4z" />
    <path d="M4 14h7v6H4z" />
    <path d="M14 14h6v6h-6z" />
  </svg>
);

const TAGLINE = (
  <>
    The NL Design System (NLDS) as a drop-in theme for your{' '}
    <span className="next-blue">Nextcloud</span>. One install and your
    interface follows the Dutch government house style, with Conduction
    component variants on top.
  </>
);

function TypeScalePanel() {
  const rows = [
    { size: 24, weight: 700, label: 'heading-xl', family: 'headline' },
    { size: 18, weight: 700, label: 'heading-lg', family: 'headline' },
    { size: 13, weight: 400, label: 'body', family: 'body' },
    { size: 11, weight: 400, label: 'caption', family: 'body' },
  ];
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
      {rows.map((row, i) => (
        <div
          key={i}
          style={{ display: 'flex', alignItems: 'baseline', gap: 8 }}
        >
          <div
            style={{
              fontFamily: `var(--conduction-typography-font-family-${row.family})`,
              fontSize: row.size,
              fontWeight: row.weight,
              color:
                row.family === 'body' && row.size === 11
                  ? 'var(--c-cobalt-500)'
                  : 'var(--c-cobalt-700)',
            }}
          >
            Aa
          </div>
          <div
            style={{
              fontFamily: 'var(--conduction-typography-font-family-code)',
              fontSize: 9,
              letterSpacing: '0.05em',
              textTransform: 'uppercase',
              color: 'var(--c-cobalt-400)',
            }}
          >
            {row.label}
          </div>
        </div>
      ))}
    </div>
  );
}

function ColourPalettePanel() {
  const swatches = [
    { name: 'rijksblauw', tone: 'var(--c-cobalt-700)' },
    { name: 'logoblauw', tone: 'var(--c-cobalt-300)' },
    { name: 'mint', tone: 'var(--c-mint-500)' },
    { name: 'oranje', tone: 'var(--c-orange-knvb)' },
    { name: 'lavender', tone: 'var(--c-lavender-300)' },
  ];
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
      {swatches.map((row, i) => (
        <div
          key={i}
          style={{ display: 'flex', alignItems: 'center', gap: 8 }}
        >
          <span
            style={{
              width: 14,
              height: 16,
              clipPath: 'var(--hex-pointy-top)',
              background: row.tone,
              flexShrink: 0,
            }}
          />
          <div
            style={{
              fontFamily: 'var(--conduction-typography-font-family-body)',
              fontSize: 11,
              color: 'var(--c-cobalt-700)',
              flex: 1,
            }}
          >
            {row.name}
          </div>
          <div
            style={{
              fontFamily: 'var(--conduction-typography-font-family-code)',
              fontSize: 9,
              color: 'var(--c-cobalt-400)',
            }}
          >
            --c-{row.name}
          </div>
        </div>
      ))}
    </div>
  );
}

function ComponentsPanel() {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
      <div style={{ display: 'flex', gap: 6 }}>
        <div
          style={{
            padding: '6px 12px',
            background: 'var(--c-cobalt-700)',
            color: 'white',
            borderRadius: 4,
            fontFamily: 'var(--conduction-typography-font-family-body)',
            fontSize: 10,
            fontWeight: 600,
          }}
        >
          Primary
        </div>
        <div
          style={{
            padding: '6px 12px',
            background: 'transparent',
            color: 'var(--c-cobalt-700)',
            border: '1px solid var(--c-cobalt-700)',
            borderRadius: 4,
            fontFamily: 'var(--conduction-typography-font-family-body)',
            fontSize: 10,
            fontWeight: 600,
          }}
        >
          Ghost
        </div>
      </div>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
        <div
          style={{
            fontFamily: 'var(--conduction-typography-font-family-code)',
            fontSize: 8,
            letterSpacing: '0.05em',
            textTransform: 'uppercase',
            color: 'var(--c-cobalt-400)',
          }}
        >
          label
        </div>
        <div
          style={{
            height: 22,
            background: 'white',
            border: '1px solid var(--c-cobalt-200)',
            borderRadius: 4,
            padding: '4px 8px',
            display: 'flex',
            alignItems: 'center',
          }}
        >
          <div
            style={{
              height: 4,
              width: '60%',
              background: 'var(--c-cobalt-200)',
              borderRadius: 1,
            }}
          />
        </div>
      </div>
      <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
        <div
          style={{
            width: 14,
            height: 14,
            borderRadius: 3,
            border: '2px solid var(--c-orange-knvb)',
            boxShadow: '0 0 0 2px rgba(247,119,21,0.25)',
          }}
        />
        <div
          style={{
            fontFamily: 'var(--conduction-typography-font-family-code)',
            fontSize: 9,
            letterSpacing: '0.05em',
            textTransform: 'uppercase',
            color: 'var(--c-cobalt-400)',
          }}
        >
          focus ring
        </div>
      </div>
    </div>
  );
}

const WIDGETS = [
  {
    title: 'Type scale',
    desc: 'NLDS type tokens for headings and body. Sentence case, government-readable, WCAG-AA contrast on the cobalt scale.',
    panel: <TypeScalePanel />,
  },
  {
    title: 'Colour palette',
    desc: 'The official NLDS swatches. Switch a theme variable and your whole Nextcloud follows.',
    panel: <ColourPalettePanel />,
  },
  {
    title: 'Component variants',
    desc: 'Buttons, inputs, focus rings, the patterns NLDS specifies. Drop-in replacements for the Nextcloud defaults.',
    panel: <ComponentsPanel />,
  },
];

export default function Home() {
  return (
    <Layout
      title="NL Design System, government theming for Nextcloud apps"
      description="NL Design System for Nextcloud. Government-grade theming with WCAG-AA tokens, type scale, and CSS variables for every Conduction app."
    >
      <main className="marketing-page">
        <DetailHero
          appId="nldesign"
          background="cobalt"
          status={{ label: 'Beta', color: 'var(--c-orange-knvb)' }}
          version="v0.1"
          locales="NL · EN"
          title="Thematiq"
          tagline={TAGLINE}
          primaryCta={{
            label: 'Install from app store',
            href: 'https://apps.nextcloud.com/apps/thematiq',
            tone: 'orange',
          }}
          secondaryCta={{ label: 'Read the docs', href: '/docs/' }}
          tertiaryCta={{
            label: 'View on GitHub',
            href: 'https://github.com/ConductionNL/thematiq',
          }}
          iconColor="var(--c-orange-knvb)"
          icon={THEMATIQ_ICON}
          illustration={<AppMock app="nldesign" />}
        />

        <WidgetShelf
          eyebrow="Tokens we ship"
          title="Type, colour, components, all from design.nl."
          lede="Install Thematiq and the published NLDS tokens land in your Nextcloud. Type scale, colour palette, component variants, the version from design.nl, not a Conduction interpretation."
          widgets={WIDGETS}
        />
      </main>
    </Layout>
  );
}
