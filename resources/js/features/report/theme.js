/**
 * One place where the look is decided, imported by every screen.
 *
 * Tokens rather than ad-hoc classes, so the Pages screen inherits the identity
 * for free without any bespoke work of its own — it is on screen for about ten
 * seconds and does not deserve a design day.
 *
 * The two rules these encode are written up in resources/css/app.css:
 * colour only ever means severity or measurement, and monospace only ever
 * means a machine measured it.
 */
/** Shared by both sizes of the lamp button, so they can never drift apart. */
const LAMP =
  'rounded-md border border-[var(--color-genie)]/45 bg-[var(--color-genie-dim)]/30 text-sm font-medium text-[var(--color-genie)] transition-colors hover:border-[var(--color-genie)] hover:bg-[var(--color-genie-dim)]/50 disabled:opacity-40'

export const T = {
  page:       'mx-auto max-w-5xl px-5 py-8 sm:py-10',
  surface:    'rounded-lg border border-[var(--color-line)] bg-[var(--color-slate)]',
  raised:     'rounded-md border border-[var(--color-line)] bg-[var(--color-raised)]',

  // Sans = a human judgment. Mono = something was measured.
  title:      'text-lg font-semibold tracking-tight text-[var(--color-paper)]',
  body:       'text-sm leading-relaxed text-[var(--color-paper)]',
  quiet:      'text-sm text-[var(--color-mist)]',
  eyebrow:    'font-mono text-[10px] uppercase tracking-[0.18em] text-[var(--color-mist)]',
  figure:     'font-mono tnum text-[var(--color-paper)]',

  buttonPrime: 'rounded-md border border-[var(--color-line)] bg-[var(--color-raised)] px-4 py-2 text-sm font-medium text-[var(--color-paper)] hover:border-[var(--color-mist)] disabled:opacity-40',
  buttonQuiet: 'rounded-md border border-[var(--color-line)] px-3 py-1.5 font-mono text-[11px] uppercase tracking-wider text-[var(--color-mist)] hover:border-[var(--color-mist)] hover:text-[var(--color-paper)] disabled:opacity-40',

  /*
  | The genie's own tokens. Rule 3 in app.css: violet is the genie, and the
  | genie never touches the data — so these appear on the lamp, the summoning,
  | the wish numerals and the one button that starts the whole thing, and on
  | nothing that carries a finding or a number.
  */

  // The one action the product exists for. It is the only violet button, which
  // is what makes it the obvious thing to press on a screen of neutral chrome.
  // Two sizes, one look — the row version is smaller because it sits in a list.
  buttonLamp:    `${LAMP} px-4 py-2`,
  buttonLampRow: `${LAMP} px-3 py-1.5`,

  genieText:   'text-[var(--color-genie)]',
  genieRule:   'border-[var(--color-genie)]/25',

  // A surface the genie is speaking from, rather than one holding evidence.
  geniePanel:  'rounded-lg border border-[var(--color-genie)]/25 bg-[var(--color-genie-dim)]/12',
}

/** Three buckets only, because more would be noise. */
export const SEVERITY = {
  high:   { bar: 'var(--color-sev-high)', text: 'text-[var(--color-sev-high)]' },
  medium: { bar: 'var(--color-sev-med)',  text: 'text-[var(--color-sev-med)]' },
  low:    { bar: 'var(--color-sev-low)',  text: 'text-[var(--color-sev-low)]' },
}

/** Where a score sits on the ramp. Same thresholds everywhere. */
export function band(score) {
  if (score == null) return 'low'
  if (score < 50) return 'high'
  if (score < 75) return 'medium'
  return 'low'
}
