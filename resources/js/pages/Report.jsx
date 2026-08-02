import React, { useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import { useQuery, useMutation } from '@tanstack/react-query'
import client from '../api/client'
import { Skeleton, EmptyState, ErrorState, PriorityTag, ScoreDial } from '../features/report/ui'

const RUNNING = ['pending', 'running']

export default function Report() {
  const { id } = useParams()
  const [showBreakdown, setShowBreakdown] = useState(false)

  // Ask "is it finished yet?" every 5 seconds, and stop on its own once it is.
  const status = useQuery({
    queryKey: ['audit', id],
    queryFn: async () => (await client.get(`/audits/${id}`)).data.data,
    refetchInterval: (q) => (RUNNING.includes(q.state.data?.status) ? 5000 : false),
  })

  const done = status.data?.status === 'completed'

  const report = useQuery({
    queryKey: ['report', id],
    queryFn: async () => (await client.get(`/audits/${id}/report`)).data.data,
    enabled: done,
  })

  const retry = useMutation({
    mutationFn: () => client.post(`/audits/${id}/retry`),
    onSuccess: (res) => { window.location.href = `/audits/${res.data.data.id}` },
  })

  if (status.isLoading) return <Skeleton lines={4} />
  if (status.isError) return <ErrorState message={status.error.friendly} onRetry={status.refetch} />

  if (status.data.status === 'failed') {
    return (
      <ErrorState
        message={status.data.error_message ?? 'That audit did not finish.'}
        onRetry={() => retry.mutate()}
        retryLabel={retry.isPending ? 'Starting…' : 'Try again'}
      />
    )
  }

  if (!done) {
    return (
      <div className="rounded-lg border border-stone-200 bg-white p-8">
        <p className="font-medium">{status.data.stage_label ?? 'Getting started…'}</p>
        <p className="mt-1 text-sm text-stone-500">
          This usually takes two to four minutes. You can leave this page open.
        </p>
        <div className="mt-5 h-2 overflow-hidden rounded-full bg-stone-200">
          <div className="h-full w-1/3 animate-pulse rounded-full bg-stone-800" />
        </div>
      </div>
    )
  }

  if (report.isLoading) return <Skeleton lines={6} />
  if (report.isError) return <ErrorState message={report.error.friendly} onRetry={report.refetch} />

  const r = report.data
  const top3 = r.recommendations.slice(0, 3)

  return (
    <div className="space-y-12">
      <div className="flex items-center justify-between">
        <Link to="/" className="text-sm text-stone-500 hover:text-stone-800">← All pages</Link>
        <a href={`/api/audits/${id}/pdf`} className="rounded-md border border-stone-300 px-3 py-1.5 text-sm font-medium hover:bg-stone-50">
          Download PDF
        </a>
      </div>

      {/* 1. Score and the three fixes that matter most. Read only this far and you still know what to do. */}
      <section className="rounded-xl border border-stone-200 bg-white p-6">
        <div className="flex flex-wrap items-center gap-6">
          <ScoreDial score={r.score.overall} />
          <div className="min-w-0 flex-1">
            <h1 className="text-xl font-semibold">{r.page.name}</h1>
            <p className="truncate text-sm text-stone-500">{r.page.url}</p>
            <button onClick={() => setShowBreakdown(!showBreakdown)} className="mt-2 text-sm text-stone-600 underline underline-offset-2">
              {showBreakdown ? 'Hide' : 'How was this number built?'}
            </button>
          </div>
        </div>

        {showBreakdown && (
          <div className="mt-5 grid gap-2 border-t border-stone-200 pt-5 sm:grid-cols-2">
            {r.score.categories.map((c) => (
              <div key={c.label} className="flex items-start justify-between gap-3 rounded-md bg-stone-50 px-3 py-2">
                <div className="min-w-0">
                  <p className="text-sm font-medium">{c.label} <span className="text-stone-400">· {c.weight}%</span></p>
                  {c.caveat && <p className="mt-0.5 text-xs text-amber-800">{c.caveat}</p>}
                </div>
                <p className="text-sm font-semibold tabular-nums">{c.score ?? 'not measured'}</p>
              </div>
            ))}
          </div>
        )}

        <div className="mt-6 border-t border-stone-200 pt-5">
          <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-500">Fix these first</h2>
          {top3.length === 0 ? (
            <EmptyState title="Nothing could be proven on this page">
              Every finding needs a number and a section to stand on. Add the optional metrics and run it again.
            </EmptyState>
          ) : (
            <ol className="space-y-4">
              {top3.map((rec, i) => <Fix key={rec.id} rec={rec} rank={i + 1} />)}
            </ol>
          )}
        </div>
      </section>

      {/* 2. The picture beside the words. This is what makes the advice believable. */}
      <section>
        <h2 className="mb-4 text-lg font-semibold">What the AI saw, section by section</h2>
        <div className="space-y-4">
          {r.sections.map((s) => (
            <article key={s.name} className="grid gap-5 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-[260px_1fr]">
              <div>
                {s.screenshot_url
                  ? <img src={s.screenshot_url} alt={`${s.name} section`} className="w-full rounded-md border border-stone-200" />
                  : <div className="rounded-md bg-stone-100 p-8 text-center text-xs text-stone-400">no picture</div>}
                <p className="mt-2 text-xs text-stone-500">
                  {s.position_percent}% down the page{s.above_the_fold ? ' · visible without scrolling' : ''}
                </p>
              </div>
              <div>
                <div className="flex items-center gap-3">
                  <h3 className="font-medium">{s.name}</h3>
                  {s.ai_score != null && (
                    <span className="rounded bg-stone-100 px-2 py-0.5 text-xs font-semibold tabular-nums">{s.ai_score}/100</span>
                  )}
                </div>
                <ul className="mt-3 space-y-3">
                  {s.problems.map((p, i) => (
                    <li key={i} className="border-l-2 border-stone-200 pl-3">
                      <p className="text-sm font-medium">{p.what}</p>
                      <p className="mt-0.5 text-sm text-stone-500">{p.why}</p>
                      <p className="mt-1 text-sm text-emerald-800">→ {p.fix}</p>
                    </li>
                  ))}
                </ul>
              </div>
            </article>
          ))}
        </div>
      </section>

      {/* 3. The full ranked list. */}
      <section>
        <h2 className="mb-1 text-lg font-semibold">Everything worth fixing, in order</h2>
        <p className="mb-4 text-sm text-stone-500">
          Ranked by how many visitors it affects, how badly, and how sure we are — divided by how much work it is.
        </p>
        <ol className="space-y-4">
          {r.recommendations.map((rec, i) => <Fix key={rec.id} rec={rec} rank={i + 1} />)}
        </ol>
      </section>

      <footer className="border-t border-stone-200 pt-4 text-xs text-stone-400">
        Audited {new Date(r.ran_at).toLocaleString()} · model {r.cost.model} · this audit cost ${r.cost.usd.toFixed(4)}
      </footer>
    </div>
  )
}

function Fix({ rec, rank }) {
  return (
    <li className="rounded-lg border border-stone-200 bg-white p-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="font-medium">{rank}. {rec.title}</p>
        <PriorityTag value={rec.priority} />
      </div>
      <dl className="mt-3 space-y-2 text-sm">
        <div><dt className="inline font-medium text-stone-500">Evidence: </dt><dd className="inline">{rec.evidence}</dd></div>
        <div><dt className="inline font-medium text-stone-500">Do this: </dt><dd className="inline">{rec.suggested_fix}</dd></div>
        <div><dt className="inline font-medium text-stone-500">If it works: </dt><dd className="inline">{rec.expected_impact}</dd></div>
      </dl>
      <p className="mt-2 text-xs text-stone-400">In {rec.section}</p>
    </li>
  )
}
