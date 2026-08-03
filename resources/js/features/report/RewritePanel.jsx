import React, { useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import client from '../../api/client'

/**
 * The page's own words, with a button that improves them.
 *
 * This is the "Fix" in Detect -> Explain -> Fix. Everything else on this screen
 * tells you what is wrong; this is the only part that hands you something to
 * paste.
 *
 * The `stored` prop is the rewrite that already rode along in the report
 * payload. It is the fallback when a live call fails — step 7 of the demo is a
 * live model call in a venue nobody controls.
 */
const ELEMENTS = [
  { key: 'headline', label: 'Headline' },
  { key: 'subhead',  label: 'Supporting line' },
  { key: 'cta',      label: 'Button label' },
]

export default function RewritePanel({ auditId, section, stored = [] }) {
  const copy = section.copy
  if (!copy) return null

  const textFor = (key) => (key === 'cta' ? copy.ctas?.[0]?.text : copy[key]?.text)

  const present = ELEMENTS.filter((e) => textFor(e.key))
  if (present.length === 0) return null

  return (
    <div className="mt-4 rounded-lg border border-stone-200 bg-stone-50 p-4">
      <h4 className="text-xs font-semibold uppercase tracking-wide text-stone-500">
        What this section says
      </h4>

      <div className="mt-3 space-y-4">
        {present.map((element) => (
          <Element
            key={element.key}
            auditId={auditId}
            sectionName={section.name}
            element={element}
            original={textFor(element.key)}
            stored={stored.find((r) => r.element === element.key)}
          />
        ))}
      </div>
    </div>
  )
}

function Element({ auditId, sectionName, element, original, stored }) {
  const [result, setResult] = useState(stored ?? null)
  const [fellBack, setFellBack] = useState(false)
  const [copied, setCopied] = useState(null)

  const rewrite = useMutation({
    mutationFn: () =>
      client.post(`/audits/${auditId}/rewrite`, { section: sectionName, element: element.key }),
    onSuccess: (res) => { setResult(res.data.data); setFellBack(false) },
    onError: () => {
      // A dead network must not kill step 7 of the demo. If we already have
      // versions saved from an earlier run, show those and say so.
      if (stored) { setResult(stored); setFellBack(true) }
    },
  })

  const copyToClipboard = async (text, i) => {
    try {
      await navigator.clipboard.writeText(text)
      setCopied(i)
      setTimeout(() => setCopied(null), 1500)
    } catch {
      // Clipboard is blocked outside a secure context. Not worth an error
      // state — the text is on screen and can be selected.
    }
  }

  return (
    <div>
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div className="min-w-0">
          <span className="block text-xs text-stone-500">{element.label}</span>
          <p data-testid={`copy-${element.key}`} className="text-sm font-medium text-stone-900">
            {original}
          </p>
        </div>

        <button
          type="button"
          onClick={() => rewrite.mutate()}
          disabled={rewrite.isPending}
          className="shrink-0 rounded-md border border-stone-300 bg-white px-3 py-1.5 text-xs font-medium hover:bg-stone-100 disabled:opacity-50"
        >
          {rewrite.isPending ? 'Rewriting…' : 'Rewrite this'}
        </button>
      </div>

      {rewrite.isPending && (
        <div data-testid="rewrite-loading" className="mt-3 space-y-2">
          <div className="h-4 w-3/4 animate-pulse rounded bg-stone-200" />
          <div className="h-3 w-1/2 animate-pulse rounded bg-stone-200" />
        </div>
      )}

      {!rewrite.isPending && rewrite.isError && !result && (
        <p className="mt-2 text-xs text-red-700">
          {rewrite.error?.friendly ?? 'Could not rewrite this just now.'}
        </p>
      )}

      {!rewrite.isPending && result && (
        <div className="mt-3 space-y-2">
          {fellBack && (
            <p data-testid="rewrite-fallback" className="text-xs text-amber-700">
              Could not reach the model just now — these are the versions saved earlier.
            </p>
          )}

          {result.variants.map((v, i) => (
            <div key={i} className="rounded-md border border-stone-200 bg-white p-3">
              <div className="flex items-start justify-between gap-3">
                <p data-testid="rewrite-variant" className="text-sm font-medium text-stone-900">
                  {v.text}
                </p>
                <button
                  type="button"
                  onClick={() => copyToClipboard(v.text, i)}
                  className="shrink-0 text-xs text-stone-500 underline hover:text-stone-900"
                >
                  {copied === i ? 'Copied' : 'Copy'}
                </button>
              </div>
              <p data-testid="rewrite-reason" className="mt-1 text-xs text-stone-500">
                {v.reason}
              </p>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
