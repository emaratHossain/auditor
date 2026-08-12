import React from 'react'
import { T } from './theme'

/*
| The genie.
|
| Every piece here is inline SVG and CSS. No image files, no icon font, no web
| font — the same reason the type stack is system-only: this demo runs in a
| venue whose network cannot be trusted, and a mascot that fails to load is a
| product with a hole where its character was.
|
| The rule these obey is rule 3 in resources/css/app.css: violet is the genie,
| and the genie never touches the data. Nothing in this file is ever handed a
| score, a severity or a metric to colour.
*/

/**
 * The lamp, at any size.
 *
 * Drawn rather than illustrated: a spout, a bowl, a handle, a foot. It reads
 * at 18px in the header and at 64px on the summoning screen, which an ornate
 * lamp would not — detail that dissolves at small sizes is detail that costs
 * more than it returns.
 */
export function Lamp({ size = 24, smoking = false, className = '' }) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 32 32"
      fill="none"
      aria-hidden="true"
      className={className}
    >
      {/* The glow under everything, so the lamp looks lit from inside rather
          than outlined. Kept tight to the bowl — any wider and it washes the
          silhouette out at header size. */}
      <ellipse
        cx="16.5" cy="20" rx="8.5" ry="4"
        fill="var(--color-genie-deep)"
        className={smoking ? 'lamp-glow' : ''}
        opacity="0.4"
      />

      {smoking && <Smoke />}

      {/* Spout first, so the bowl's fill overlaps its base and the two read as
          one vessel rather than a bowl with a triangle stuck to it. */}
      <path
        d="M10 16.2 2.2 12.4l1.5 5 6 2.2Z"
        fill="var(--color-genie-dim)"
        stroke="var(--color-genie)"
        strokeWidth="1.4"
        strokeLinejoin="round"
      />

      {/* Handle — a full loop on the right, which is the half of the
          silhouette that says "lamp" rather than "bowl". */}
      <path
        d="M23.5 16.4c4 0 5.4 2.4 4.6 4.8-.5 1.4-1.8 2-2.8 1.6"
        stroke="var(--color-genie)"
        strokeWidth="1.4"
        strokeLinecap="round"
        fill="none"
      />

      {/* Bowl */}
      <path
        d="M9 19c0-3.3 3.2-5.4 7.5-5.4S24 15.7 24 19c0 2.4-3.2 4-7.5 4S9 21.4 9 19Z"
        fill="var(--color-genie-dim)"
        stroke="var(--color-genie)"
        strokeWidth="1.4"
        strokeLinejoin="round"
      />

      {/* Lid dome and knob, so the bowl has a top and a place to be rubbed */}
      <path
        d="M13.4 14.2c.8-1.6 5-1.6 5.8 0"
        stroke="var(--color-genie)"
        strokeWidth="1.4"
        strokeLinecap="round"
        fill="none"
      />
      <circle cx="16.3" cy="11.6" r="1.6" fill="var(--color-genie)" />

      {/* Foot, so it sits rather than floats */}
      <path
        d="M13 23h7l-1 2.4h-5Z"
        fill="var(--color-genie)"
        opacity="0.6"
      />
    </svg>
  )
}

/** Three wisps off the spout, at three speeds. */
function Smoke() {
  return (
    <g stroke="var(--color-genie)" strokeWidth="1.5" strokeLinecap="round" fill="none">
      <path className="wisp"        d="M3.4 10.4c-1.8-2 .6-3.4-.8-5.4" opacity="0.85" />
      <path className="wisp wisp-2" d="M6.6 9.6c1.6-2.4-1-3.8.6-6" opacity="0.6" />
      <path className="wisp wisp-3" d="M1 10.8c-1.2-1.6.4-2.6-.4-4.2" opacity="0.5" />
    </g>
  )
}

/**
 * The wordmark: lamp plus name, used in the header and nowhere else.
 *
 * The old mark was a red dot, which under rule 1 read as "high severity" every
 * time the page loaded — a permanent alarm about nothing.
 */
export function Wordmark() {
  return (
    <span className="flex items-center gap-2.5">
      <Lamp size={22} smoking />
      <span className="text-lg font-semibold tracking-tight text-[var(--color-paper)]">
        DropSense AI
      </span>
    </span>
  )
}

/**
 * A wish numeral: 1, 2, 3 in a violet ring.
 *
 * The top three recommendations were always the three things to go and do. The
 * numeral says that out loud without renaming anything — the heading above it
 * still says "Fix these first", because a person scanning for what to do next
 * should not have to decode a metaphor to find it.
 */
export function WishNumeral({ n }) {
  return (
    <span
      aria-hidden="true"
      className="mt-px inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-[var(--color-genie)]/45 bg-[var(--color-genie-dim)]/25 font-mono text-[11px] font-semibold text-[var(--color-genie)]"
    >
      {n}
    </span>
  )
}

/**
 * The summoning: a big lamp, smoking, over whatever the caller wants to say.
 *
 * This is the screen someone stares at for one to two minutes — the longest
 * they look at this product in one go — so it is the one place the character is
 * allowed to take up real space.
 */
export function Summoning({ children }) {
  return (
    <div className="flex flex-col items-center text-center">
      <Lamp size={72} smoking />
      <p className={`${T.eyebrow} mt-3 text-[var(--color-genie)]`}>Summoning</p>
      {children}
    </div>
  )
}
