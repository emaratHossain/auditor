import React from 'react'
import { T, SEVERITY, band } from './theme'

/** No screen may show a blank area. These three are why. */
export function Skeleton({ lines = 3 }) {
  return (
    <div className="animate-pulse space-y-3" role="status" aria-label="Loading">
      {Array.from({ length: lines }).map((_, i) => (
        <div key={i} className="h-4 rounded bg-[var(--color-raised)]" style={{ width: `${90 - i * 12}%` }} />
      ))}
    </div>
  )
}

export function EmptyState({ title, children }) {
  return (
    <div className="rounded-lg border border-dashed border-[var(--color-line)] p-8 text-center">
      <p className="font-medium text-[var(--color-paper)]">{title}</p>
      <div className={`mt-2 ${T.quiet}`}>{children}</div>
    </div>
  )
}

export function ErrorState({ message, onRetry, retryLabel = 'Try again', note }) {
  return (
    <div className="rounded-lg border border-[var(--color-sev-high)]/40 bg-[var(--color-sev-high)]/10 p-5">
      <p className="text-[var(--color-paper)]">{message}</p>
      {onRetry && (
        <button onClick={onRetry} className={`mt-3 ${T.buttonPrime}`}>
          {retryLabel}
        </button>
      )}
      {/* When the retry itself fails. Without this the button flickers and
          nothing changes, which is a dead end on the screen that was already
          telling someone something went wrong. */}
      {note && (
        <p data-testid="retry-error" className="mt-3 text-sm text-[var(--color-sev-med)]">
          {note}
        </p>
      )}
    </div>
  )
}

/**
 * A last word before something goes for good.
 *
 * Nothing in this app can be undone, so the dialog's job is to say exactly what
 * is about to be lost — the name of the thing, and what goes with it — rather
 * than asking "are you sure?" about an unnamed row. Escape and the backdrop
 * both cancel, and Cancel takes the focus, because the destructive button
 * should never be the one a stray Enter presses.
 */
export function ConfirmDialog({ title, children, confirmLabel, onConfirm, onCancel, pending = false }) {
  const cancelRef = React.useRef(null)

  React.useEffect(() => {
    cancelRef.current?.focus()

    const onKey = (e) => { if (e.key === 'Escape') onCancel() }
    window.addEventListener('keydown', onKey)

    return () => window.removeEventListener('keydown', onKey)
  }, [onCancel])

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-label={title}
      className="fixed inset-0 z-20 flex items-center justify-center overflow-y-auto bg-black/70 p-4"
      onMouseDown={(e) => { if (e.target === e.currentTarget) onCancel() }}
    >
      <div className="w-full max-w-md rounded-xl bg-[var(--color-slate)] p-6 shadow-xl">
        <h3 className="text-lg font-semibold">{title}</h3>
        <div className={`mt-2 ${T.quiet}`}>{children}</div>

        <div className="mt-6 flex justify-end gap-3">
          <button ref={cancelRef} onClick={onCancel} disabled={pending} className={T.buttonPrime}>
            Cancel
          </button>
          <button
            onClick={onConfirm}
            disabled={pending}
            className="rounded-md border border-[var(--color-sev-high)]/50 bg-[var(--color-sev-high)]/15 px-4 py-2 text-sm font-medium text-[var(--color-sev-high)] hover:bg-[var(--color-sev-high)]/25 disabled:opacity-40"
          >
            {pending ? 'Deleting…' : confirmLabel}
          </button>
        </div>
      </div>
    </div>
  )
}

export function PriorityTag({ value }) {
  const sev = SEVERITY[value] ?? SEVERITY.low

  return (
    <span
      className={`font-mono text-[10px] uppercase tracking-[0.18em] ${sev.text}`}
      style={{ borderLeft: `2px solid ${sev.bar}`, paddingLeft: '6px' }}
    >
      {value}
    </span>
  )
}

/**
 * The score, as an instrument reads it.
 *
 * A donut dial is the answer every dashboard gives, and it makes a number
 * harder to read than the number itself does. This is the numeral, at the size
 * it deserves, over the scale it sits on — so you can see both what it is and
 * how bad that is.
 */
export function ScoreReadout({ score }) {
  const value = score ?? 0
  const sev = SEVERITY[band(score)]

  return (
    <div role="img" aria-label={`Conversion Score ${score ?? 'not yet known'} out of 100`}>
      <p className={T.eyebrow}>Conversion Score</p>

      <div className="mt-1 flex items-baseline gap-2">
        <span className={`${T.figure} text-6xl font-semibold leading-none`}>{score ?? '—'}</span>
        <span className={`${T.figure} text-sm text-[var(--color-mist)]`}>/ 100</span>
      </div>

      {/* The scale it sits on, so the number means something on its own. */}
      <div className="mt-3 h-1 w-44 rounded-full bg-[var(--color-raised)]">
        <div
          className="h-full rounded-full transition-[width] duration-700"
          style={{ width: `${Math.max(2, value)}%`, background: sev.bar }}
        />
      </div>
    </div>
  )
}

/**
 * A category row: name, weight, where it sits, and whether anyone measured it.
 *
 * The measured/estimated label is not a footnote. Four of these six numbers are
 * opinions with a number attached, and saying which is which is the whole
 * reason this product can be trusted.
 */
export function CategoryRow({ category }) {
  const sev = SEVERITY[band(category.score)]

  return (
    <div data-testid={`category-${category.label}`} className="py-2">
      <div className="flex items-baseline justify-between gap-3">
        <span className={T.body}>{category.label}</span>
        <span className={`${T.figure} text-sm`}>{category.score ?? '—'}</span>
      </div>

      <div className="mt-1.5 h-[3px] rounded-full bg-[var(--color-raised)]">
        <div
          className="h-full rounded-full"
          style={{ width: `${Math.max(2, category.score ?? 0)}%`, background: sev.bar }}
        />
      </div>

      <div className="mt-1.5 flex items-center justify-between gap-3">
        <span className={T.eyebrow}>{category.weight}% of the score</span>
        <span
          className="font-mono text-[10px] uppercase tracking-[0.18em]"
          style={{ color: category.measured ? 'var(--color-measured)' : 'var(--color-mist)' }}
        >
          {category.measured ? 'measured' : 'estimated'}
        </span>
      </div>

      {category.caveat && (
        <p className="mt-1 text-xs text-[var(--color-mist)]">{category.caveat}</p>
      )}
    </div>
  )
}

/**
 * The depth marker on the spine.
 *
 * This is the one thing on the screen worth remembering, and it is made of real
 * data: how far down the page a section actually starts, measured during
 * capture. It is the number the buried-section rule is built on, so putting it
 * in the layout rather than in a caption says something true about the product.
 */
export function DepthTick({ percent, delayMs = 0 }) {
  return (
    <div className="relative hidden w-16 shrink-0 sm:block" aria-hidden="true">
      <div
        className="spine-line absolute left-[7px] top-0 h-full w-px bg-[var(--color-line)]"
        style={{ animationDelay: `${delayMs}ms` }}
      />
      <div className="absolute left-0 top-6 flex items-center gap-2">
        <span className="h-[7px] w-[7px] rounded-full border border-[var(--color-mist)] bg-[var(--color-ink)]" />
      </div>
      <span
        className={`${T.figure} absolute left-5 top-[18px] text-[11px] text-[var(--color-mist)]`}
      >
        {percent}%
      </span>
    </div>
  )
}
