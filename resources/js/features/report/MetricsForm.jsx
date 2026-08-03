import React, { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import client from '../../api/client'

/**
 * Seven numbers. Three required, four optional — and all of them already filled
 * in from the demo dataset, so the demo needs no typing at all.
 *
 * They stay visible and overwritable on purpose. Hiding them would buy a
 * marginally cleaner flow and cost two things worth more: the tool could no
 * longer audit a real page with real numbers, and nobody could answer "whose
 * numbers are these?"
 *
 * Every field says in plain words what the number means, and every optional one
 * says what stops working if it is left blank — because a blank switches off the
 * rules that need it, and must never become a guess.
 */
const REQUIRED = [
  { key: 'visitors',        label: 'Visitors',        unit: '',  explain: 'How many people came to this page. Any recent period will do.' },
  { key: 'bounce_rate',     label: 'Bounce rate',     unit: '%', explain: 'The share who arrive and leave without doing anything.' },
  { key: 'conversion_rate', label: 'Conversion rate', unit: '%', explain: 'The share who did the thing you wanted — signed up, bought, booked.' },
]

const OPTIONAL = [
  { key: 'cta_click_rate',     label: 'Main button click rate', unit: '%',
    explain: 'The share who press your main button.',
    ifBlank: 'Without it we cannot tell you whether people are seeing the button and ignoring it.' },
  { key: 'mobile_share',       label: 'Visitors on a phone', unit: '%',
    explain: 'How much of your traffic is on a small screen.',
    ifBlank: 'Used to say how much the phone problem is costing you.' },
  { key: 'mobile_bounce_rate', label: 'Bounce rate on a phone', unit: '%',
    explain: 'The same leaving-without-acting figure, phones only.',
    ifBlank: 'Without it we cannot tell you whether the phone layout is the leak.' },
]

/**
 * Defined at module scope, NOT inside the form.
 *
 * A component declared inside its parent is a new component type on every
 * render, so React unmounts and remounts it — and the input loses focus after
 * every keystroke. That was survivable when the form started empty; now that
 * typing over the demo numbers is the normal path, it would be the first thing
 * anyone noticed.
 */
function Field({ f, optional, value, error, onChange }) {
  return (
    <label className="block">
      <span className="text-sm font-medium">
        {f.label} {optional && <span className="font-normal text-stone-400">— optional</span>}
      </span>
      <div className="mt-1 flex items-center gap-2">
        <input
          type="number" step="any" inputMode="decimal"
          name={f.key}
          className="w-full rounded-md border border-stone-300 px-3 py-2 text-sm"
          value={value ?? ''}
          onChange={(e) => onChange(f.key, e.target.value)}
        />
        {f.unit && <span className="text-sm text-stone-400">{f.unit}</span>}
      </div>
      <span className="mt-1 block text-xs text-stone-500">{f.explain}</span>
      {optional && <span className="mt-0.5 block text-xs text-stone-400">If you leave this blank: {f.ifBlank}</span>}
      {error && <span className="mt-1 block text-xs text-red-700">{error}</span>}
    </label>
  )
}

export default function MetricsForm({ page, onSubmit, onCancel, submitting, errors }) {
  // null means "nobody has typed yet", which is what lets us tell a demo run
  // from a real one. An empty object would lose that distinction.
  const [values, setValues] = useState(null)
  const [reach, setReach] = useState(null)

  const demo = useQuery({
    queryKey: ['demo-metrics'],
    queryFn: async () => (await client.get('/demo-metrics')).data.data,
    staleTime: Infinity,
  })

  const d = demo.data
  const untouched = values === null && reach === null

  const filled = values ?? (d ? {
    visitors: d.visitors,
    bounce_rate: d.bounce_rate,
    conversion_rate: d.conversion_rate,
    cta_click_rate: d.cta_click_rate,
    mobile_share: d.mobile_share,
    mobile_bounce_rate: d.mobile_bounce_rate,
  } : {})

  const filledReach = reach ?? (d
    ? Object.entries(d.section_reach).map(([k, v]) => `${k}: ${v}`).join(', ')
    : '')

  const set = (key, v) => setValues({ ...filled, [key]: v })

  const submit = (e) => {
    e.preventDefault()

    // "Hero: 82, Pricing: 20" -> { Hero: 82, Pricing: 20 }
    const section_reach = {}
    filledReach.split(',').forEach((pair) => {
      const [name, value] = pair.split(':').map((s) => s?.trim())
      if (name && value && !Number.isNaN(Number(value))) section_reach[name] = Number(value)
    })

    const payload = { ...filled }
    Object.keys(payload).forEach((k) => {
      if (payload[k] === '' || payload[k] == null) delete payload[k]
    })
    if (Object.keys(section_reach).length) payload.section_reach = section_reach

    // Untouched demo values stay demo values. Touch one field and they are
    // yours — the report prints which, so this has to be true.
    if (untouched && d) {
      payload.source = 'demo'
      payload.rage_clicks = d.rage_clicks
      payload.dead_clicks = d.dead_clicks
    } else {
      payload.source = 'manual'
    }

    onSubmit(payload)
  }

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-label={`Run an audit on ${page.name}`}
      className="fixed inset-0 z-20 flex items-start justify-center overflow-y-auto bg-stone-900/40 p-4"
    >
      <form onSubmit={submit} className="my-8 w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
        <h3 className="text-lg font-semibold">Run an audit on {page.name}</h3>
        <p className="mt-1 text-sm text-stone-500" data-testid="metrics-form-source">
          {demo.isLoading
            ? 'Loading example numbers…'
            : untouched && d
              ? `${d.label}. Press Run audit, or type your own numbers over them.`
              : 'These are your numbers. Only the first three are needed.'}
        </p>

        <div className="mt-5 space-y-4">
          {REQUIRED.map((f) => (
            <Field key={f.key} f={f} value={filled[f.key]} error={errors?.[f.key]?.[0]} onChange={set} />
          ))}

          <details className="rounded-md border border-stone-200 p-3">
            <summary className="cursor-pointer text-sm font-medium">
              Four optional numbers — they unlock more of the analysis
            </summary>
            <div className="mt-4 space-y-4">
              {OPTIONAL.map((f) => (
                <Field key={f.key} f={f} optional value={filled[f.key]} error={errors?.[f.key]?.[0]} onChange={set} />
              ))}

              <label className="block">
                <span className="text-sm font-medium">
                  How far down people get <span className="font-normal text-stone-400">— optional</span>
                </span>
                <input
                  name="section_reach"
                  className="mt-1 w-full rounded-md border border-stone-300 px-3 py-2 text-sm"
                  placeholder="Hero: 96, Features: 71, Pricing: 20"
                  value={filledReach}
                  onChange={(e) => setReach(e.target.value)}
                />
                <span className="mt-1 block text-xs text-stone-500">
                  The share of visitors who scroll far enough to see each section.
                </span>
                <span className="mt-0.5 block text-xs text-stone-400">
                  If you leave this blank: we cannot tell you which sections are buried too far down.
                </span>
              </label>
            </div>
          </details>
        </div>

        <div className="mt-6 flex justify-end gap-3">
          <button type="button" onClick={onCancel} className="rounded-md px-4 py-2 text-sm font-medium text-stone-600 hover:bg-stone-100">
            Cancel
          </button>
          <button type="submit" disabled={submitting} className="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 disabled:opacity-50">
            {submitting ? 'Starting…' : 'Run audit'}
          </button>
        </div>
      </form>
    </div>
  )
}
