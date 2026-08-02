import React, { useState } from 'react'

/**
 * Seven numbers. Three required, four optional.
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

export default function MetricsForm({ page, onSubmit, onCancel, submitting, errors }) {
  const [values, setValues] = useState({})
  const [reach, setReach] = useState('')

  const set = (key, v) => setValues({ ...values, [key]: v })

  const submit = (e) => {
    e.preventDefault()

    // "Hero: 82, Pricing: 20" -> { Hero: 82, Pricing: 20 }
    const section_reach = {}
    reach.split(',').forEach((pair) => {
      const [name, value] = pair.split(':').map((s) => s?.trim())
      if (name && value && !Number.isNaN(Number(value))) section_reach[name] = Number(value)
    })

    const payload = { ...values }
    Object.keys(payload).forEach((k) => { if (payload[k] === '') delete payload[k] })
    if (Object.keys(section_reach).length) payload.section_reach = section_reach

    onSubmit(payload)
  }

  const Field = ({ f, optional }) => (
    <label className="block">
      <span className="text-sm font-medium">
        {f.label} {optional && <span className="font-normal text-stone-400">— optional</span>}
      </span>
      <div className="mt-1 flex items-center gap-2">
        <input
          type="number" step="any" inputMode="decimal"
          name={f.key}
          className="w-full rounded-md border border-stone-300 px-3 py-2 text-sm"
          value={values[f.key] ?? ''}
          onChange={(e) => set(f.key, e.target.value)}
        />
        {f.unit && <span className="text-sm text-stone-400">{f.unit}</span>}
      </div>
      <span className="mt-1 block text-xs text-stone-500">{f.explain}</span>
      {optional && <span className="mt-0.5 block text-xs text-stone-400">If you leave this blank: {f.ifBlank}</span>}
      {errors?.[f.key] && <span className="mt-1 block text-xs text-red-700">{errors[f.key][0]}</span>}
    </label>
  )

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-label={`Run an audit on ${page.name}`}
      className="fixed inset-0 z-20 flex items-start justify-center overflow-y-auto bg-stone-900/40 p-4"
    >
      <form onSubmit={submit} className="my-8 w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
        <h3 className="text-lg font-semibold">Run an audit on {page.name}</h3>
        <p className="mt-1 text-sm text-stone-500">
          Copy these from your analytics. Only the first three are needed.
        </p>

        <div className="mt-5 space-y-4">
          {REQUIRED.map((f) => <Field key={f.key} f={f} />)}

          <details className="rounded-md border border-stone-200 p-3">
            <summary className="cursor-pointer text-sm font-medium">
              Four optional numbers — they unlock more of the analysis
            </summary>
            <div className="mt-4 space-y-4">
              {OPTIONAL.map((f) => <Field key={f.key} f={f} optional />)}

              <label className="block">
                <span className="text-sm font-medium">
                  How far down people get <span className="font-normal text-stone-400">— optional</span>
                </span>
                <input
                  name="section_reach"
                  className="mt-1 w-full rounded-md border border-stone-300 px-3 py-2 text-sm"
                  placeholder="Hero: 96, Features: 71, Pricing: 20"
                  value={reach}
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
