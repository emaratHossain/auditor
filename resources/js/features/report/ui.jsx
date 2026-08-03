import React from 'react'

/** No screen may show a blank white area. These three are why. */
export function Skeleton({ lines = 3 }) {
  return (
    <div className="animate-pulse space-y-3" role="status" aria-label="Loading">
      {Array.from({ length: lines }).map((_, i) => (
        <div key={i} className="h-4 rounded bg-stone-200" style={{ width: `${90 - i * 12}%` }} />
      ))}
    </div>
  )
}

export function EmptyState({ title, children }) {
  return (
    <div className="rounded-lg border border-dashed border-stone-300 bg-white p-8 text-center">
      <p className="font-medium text-stone-800">{title}</p>
      <div className="mt-2 text-sm text-stone-500">{children}</div>
    </div>
  )
}

export function ErrorState({ message, onRetry, retryLabel = 'Try again' }) {
  return (
    <div className="rounded-lg border border-red-200 bg-red-50 p-5">
      <p className="font-medium text-red-900">{message}</p>
      {onRetry && (
        <button
          onClick={onRetry}
          className="mt-3 rounded-md bg-red-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-800"
        >
          {retryLabel}
        </button>
      )}
    </div>
  )
}

const PRIORITY = {
  high:   'bg-red-100 text-red-800 ring-red-200',
  medium: 'bg-amber-100 text-amber-900 ring-amber-200',
  low:    'bg-emerald-100 text-emerald-900 ring-emerald-200',
}

export function PriorityTag({ value }) {
  return (
    <span className={`rounded-full px-2 py-0.5 text-[11px] font-medium uppercase tracking-wide ring-1 ${PRIORITY[value] ?? PRIORITY.low}`}>
      {value}
    </span>
  )
}

/** The score dial — ~20 lines of SVG, which is why Recharts was cut from V1. */
export function ScoreDial({ score }) {
  const value = score ?? 0
  const r = 52
  const circumference = 2 * Math.PI * r
  const stroke = value >= 75 ? '#15803d' : value >= 50 ? '#b45309' : '#b91c1c'

  return (
    <svg viewBox="0 0 130 130" className="h-32 w-32" role="img" aria-label={`Conversion Score ${score ?? 'not yet known'} out of 100`}>
      <circle cx="65" cy="65" r={r} fill="none" stroke="#e7e5e4" strokeWidth="12" />
      <circle
        cx="65" cy="65" r={r} fill="none" stroke={stroke} strokeWidth="12" strokeLinecap="round"
        strokeDasharray={circumference}
        strokeDashoffset={circumference - (value / 100) * circumference}
        transform="rotate(-90 65 65)"
      />
      <text x="65" y="70" textAnchor="middle" fontSize="30" fontWeight="600" fill="#1c1917">
        {score ?? '—'}
      </text>
      <text x="65" y="88" textAnchor="middle" fontSize="10" fill="#78716c">out of 100</text>
    </svg>
  )
}
