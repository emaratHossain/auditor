import React, { useEffect, useRef, useState } from 'react'
import { SEVERITY, band } from './theme'

/**
 * The drop column — the whole product as one object.
 *
 * The page runs down the left as a stack of bands, each one as tall as its real
 * share of the page. Beside it runs the retention ribbon: how many visitors are
 * still there at that depth. The ribbon narrows as they leave, so the shape of
 * the leak is the shape of the column, and the worst section is simply the
 * place where the ribbon pinches while the band is red.
 *
 * Every dimension here is measured, never styled:
 *   band height   ← where the section starts on the real page
 *   band colour   ← the model's score for that section
 *   ribbon width  ← the share of visitors who scrolled that far
 *
 * Where scroll depth was never supplied, the ribbon is absent rather than
 * invented — the same rule the rest of the report lives by. That absence is
 * also the most persuasive argument for filling the field in, so it says so.
 */
export default function DropColumn({ sections, activeName, onJump }) {
  const hasReach = sections.some((s) => s.reach_percent != null)

  // Each band gets the distance to the next section's start, so the column is
  // the page's own proportions rather than equal slices.
  const bands = sections.map((s, i) => {
    const start = s.position_percent
    const end = sections[i + 1]?.position_percent ?? 100

    return { ...s, start, span: Math.max(4, end - start) }
  })

  const here = bands.find((b) => b.name === activeName) ?? bands[0]

  return (
    <figure
      className="drop-column"
      aria-label="The page from top to bottom, and how many visitors are still there"
    >
      <figcaption className="mb-3 font-mono text-[10px] uppercase tracking-[0.18em] text-[var(--color-mist)]">
        The drop
      </figcaption>

      <div className="drop-body flex min-h-0 gap-[3px]">
        {/* The page itself */}
        <div className={`flex ${hasReach ? 'w-8' : 'w-full'} shrink-0 flex-col gap-[2px]`} role="list">
          {bands.map((b, i) => {
            const sev = SEVERITY[band(b.ai_score)]
            const active = b.name === activeName

            return (
              <button
                key={b.name}
                role="listitem"
                onClick={() => onJump?.(b.name)}
                title={`${b.name} — ${b.start}% down${b.ai_score != null ? `, scores ${b.ai_score}/100` : ''}`}
                aria-label={`Jump to ${b.name}`}
                aria-current={active ? 'true' : undefined}
                className="drop-band relative w-full rounded-[2px]"
                style={{
                  flexGrow: b.span,
                  minHeight: '8px',
                  background: active ? sev.bar : `color-mix(in srgb, ${sev.bar} 58%, var(--color-ink))`,
                  boxShadow: active ? `0 0 0 1px ${sev.bar}, 0 0 18px -2px ${sev.bar}` : 'none',
                  animationDelay: `${i * 70}ms`,
                }}
              />
            )
          })}
        </div>

        {/* Who is still reading, where anyone counted */}
        {hasReach && (
          <div className="relative flex flex-1 flex-col gap-[2px]">
            {bands.map((b) => (
              <div key={b.name} className="relative flex items-center" style={{ flexGrow: b.span, minHeight: '8px' }}>
                {b.reach_percent != null && (
                  <div
                    className="ribbon h-full rounded-r-[2px]"
                    style={{
                      width: `${Math.max(3, Math.min(100, b.reach_percent))}%`,
                      background: b.name === activeName
                        ? 'var(--color-measured)'
                        : 'color-mix(in srgb, var(--color-measured) 45%, var(--color-ink))',
                    }}
                  />
                )}
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Where you are standing, live. The column is a map; this is the pin. */}
      <div className="drop-readout">
        <p className="font-mono tnum text-[11px] text-[var(--color-paper)]">
          {here ? `${here.start}% down` : 'top'}
        </p>
        <p className="mt-0.5 truncate text-[11px] leading-snug text-[var(--color-mist)]" title={here?.name}>
          {here?.name ?? ''}
        </p>
        {here?.reach_percent != null && (
          <p className="mt-1 font-mono text-[11px] text-[var(--color-measured)]">
            {Math.round(here.reach_percent)}% still here
          </p>
        )}
        {!hasReach && (
          <p className="mt-2 text-[11px] leading-snug text-[var(--color-mist)]">
            Add how far people scroll and this column shows where they leave.
          </p>
        )}
      </div>
    </figure>
  )
}

/**
 * Which section the reader is actually looking at.
 *
 * Kept here rather than in the screen because it exists only to drive the
 * column: the column is the map, and a map that does not show where you are
 * standing is decoration.
 */
export function useActiveSection(names) {
  const [active, setActive] = useState(names[0])
  const ref = useRef(null)

  useEffect(() => {
    const nodes = Array.from(document.querySelectorAll('[data-section-name]'))
    if (!nodes.length) return

    const observer = new IntersectionObserver(
      (entries) => {
        const visible = entries
          .filter((e) => e.isIntersecting)
          .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0]

        if (visible) setActive(visible.target.dataset.sectionName)
      },
      { rootMargin: '-20% 0px -60% 0px', threshold: 0 },
    )

    nodes.forEach((n) => observer.observe(n))
    ref.current = observer

    return () => observer.disconnect()
  }, [names.join('|')])

  return active
}
