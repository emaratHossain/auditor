import React, { useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import { useQuery, useMutation } from '@tanstack/react-query'
import client from '../api/client'
import { Skeleton, EmptyState, ErrorState, PriorityTag, ScoreReadout, CategoryRow, DepthTick } from '../features/report/ui'
import RewritePanel from '../features/report/RewritePanel'
import { T, SEVERITY, band } from '../features/report/theme'

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
        note={retry.isError ? retry.error?.friendly : null}
      />
    )
  }

  if (!done) {
    return (
      <div className={`${T.surface} p-8`}>
        <p className={T.eyebrow}>Running</p>
        <p className={`mt-2 ${T.title}`}>{status.data.stage_label ?? 'Getting started…'}</p>
        <p className={`mt-1 ${T.quiet}`}>
          This usually takes one to two minutes. You can leave this page open.
        </p>
        <div className="mt-5 h-[3px] overflow-hidden rounded-full bg-[var(--color-raised)]">
          <div className="h-full w-1/3 animate-pulse rounded-full bg-[var(--color-measured)]" />
        </div>
      </div>
    )
  }

  if (report.isLoading) return <Skeleton lines={6} />
  if (report.isError) return <ErrorState message={report.error.friendly} onRetry={report.refetch} />

  const r = report.data
  const top3 = r.recommendations.slice(0, 3)

  return (
    <div className="space-y-10">
      <div className="flex items-center justify-between">
        <Link to="/" className={`${T.eyebrow} hover:text-[var(--color-paper)]`}>← All pages</Link>
        <a href={`/api/audits/${id}/pdf`} className={T.buttonQuiet}>Download PDF</a>
      </div>

      {/*
        Nothing below is real unless something actually opened the page.
        This has to sit above the score, not in a footnote: the failure mode it
        prevents is someone showing invented findings about a real client URL
        and believing them.
      */}
      {r.simulated?.any && (
        <div
          data-testid="simulated-warning"
          className="rounded-lg border border-[var(--color-sev-med)]/50 bg-[var(--color-sev-med)]/10 p-4"
        >
          <p className="font-mono text-[10px] uppercase tracking-[0.18em] text-[var(--color-sev-med)]">
            Example data — not this page
          </p>
          <p className={`mt-2 ${T.body}`}>{r.simulated.note}</p>
        </div>
      )}

      {/* 1. Score and the three fixes that matter most. Read only this far and you still know what to do. */}
      <section className={`${T.surface} p-6`}>
        {/* Stacks on a phone. Side by side, the page name wraps to four lines
            beside the score and neither one reads. */}
        <div className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between sm:gap-6">
          <div data-testid="score-dial" className="shrink-0">
            <ScoreReadout score={r.score.overall} />
          </div>

          <div className="min-w-0 sm:flex-1 sm:text-right">
            <h1 className={T.title}>{r.page.name}</h1>
            <p className={`${T.figure} truncate text-xs text-[var(--color-mist)]`}>{r.page.url}</p>
            <p data-testid="metrics-source" className="mt-2 font-mono text-[10px] uppercase leading-relaxed tracking-[0.14em] text-[var(--color-mist)]">
              {r.metrics_source.label}
            </p>
          </div>
        </div>

        {/* The numbers this whole report is built on. */}
        <dl className="mt-6 grid grid-cols-2 gap-x-6 gap-y-4 border-t border-[var(--color-line)] pt-5 sm:grid-cols-3">
          {r.metrics.filter((m) => m.value !== null).map((m) => (
            <div key={m.key} data-testid="metric">
              <dt className={T.eyebrow}>{m.label}</dt>
              <dd className={`${T.figure} mt-1 text-lg`}>
                {typeof m.value === 'number' ? m.value.toLocaleString() : m.value}
                <span className="text-xs text-[var(--color-mist)]">{m.unit}</span>
              </dd>
              <dd data-testid="metric-explain" className="mt-0.5 text-xs leading-snug text-[var(--color-mist)]">
                {m.explain}
              </dd>
            </div>
          ))}
        </dl>

        <div className="mt-5 border-t border-[var(--color-line)] pt-4">
          <button onClick={() => setShowBreakdown(!showBreakdown)} className={T.buttonQuiet}>
            {showBreakdown ? 'Hide the breakdown' : 'How this score is built'}
          </button>

          {showBreakdown && (
            <div className="mt-4 grid gap-x-8 sm:grid-cols-2">
              {r.score.categories.map((c) => <CategoryRow key={c.label} category={c} />)}

              {r.lighthouse?.worst_checks?.length > 0 && (
                <div className="sm:col-span-2 mt-3 border-t border-[var(--color-line)] pt-3">
                  <p className={T.eyebrow}>Worst measured checks</p>
                  <ul className="mt-2 space-y-1">
                    {r.lighthouse.worst_checks.map((c) => (
                      <li key={c.id} className="flex items-baseline justify-between gap-3 text-xs">
                        <span className="text-[var(--color-mist)]">{c.title}</span>
                        <span className={`${T.figure} text-[var(--color-measured)]`}>{c.score}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              )}
            </div>
          )}
        </div>

        <div className="mt-6 border-t border-[var(--color-line)] pt-5">
          <h2 className={`${T.eyebrow} mb-3`}>Fix these first</h2>
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

      {/*
        2. The picture beside the words, hung off a depth spine.

        The spine is not decoration: each tick is where that section actually
        starts, measured during capture. It is the same number the buried-section
        rule is built on, so the layout itself says how far a visitor has to
        scroll before they meet each problem.
      */}
      <section>
        <div className="mb-4 flex items-baseline justify-between">
          <h2 className={T.title}>Down the page, section by section</h2>
          <span className={T.eyebrow}>depth ↓</span>
        </div>

        <div>
          {r.sections.map((s, i) => (
            <article key={s.name} data-testid="section-card" className="flex gap-0 sm:gap-2">
              <DepthTick percent={s.position_percent} delayMs={i * 90} />

              <div className={`${T.surface} mb-4 grid flex-1 gap-5 p-4 sm:grid-cols-[240px_1fr]`}>
                <div>
                  {s.screenshot_url
                    ? <img src={s.screenshot_url} alt={`${s.name} section`} className="w-full rounded border border-[var(--color-line)]" />
                    : <div className="rounded border border-[var(--color-line)] p-8 text-center text-xs text-[var(--color-mist)]">no picture</div>}
                  <p className={`${T.eyebrow} mt-2`}>
                    {s.position_percent}% down{s.above_the_fold ? ' · above the fold' : ''}
                  </p>
                </div>

                <div>
                  <div className="flex items-baseline justify-between gap-3">
                    <h3 className="font-medium text-[var(--color-paper)]">{s.name}</h3>
                    {s.ai_score != null && (
                      <span className={`${T.figure} text-sm`} style={{ color: SEVERITY[band(s.ai_score)].bar }}>
                        {s.ai_score}<span className="text-[var(--color-mist)]">/100</span>
                      </span>
                    )}
                  </div>

                  <ul className="mt-3 space-y-3">
                    {s.problems.map((p, j) => (
                      <li
                        key={j}
                        className="pl-3"
                        style={{ borderLeft: `2px solid ${SEVERITY[p.severity >= 4 ? 'high' : p.severity >= 3 ? 'medium' : 'low'].bar}` }}
                      >
                        <p className={T.body}>{p.what}</p>
                        <p className={`mt-0.5 ${T.quiet}`}>{p.why}</p>
                        <p className="mt-1 text-sm text-[var(--color-sev-low)]">→ {p.fix}</p>
                      </li>
                    ))}
                  </ul>

                  <RewritePanel
                    auditId={id}
                    section={s}
                    stored={(r.rewrites ?? []).filter((rw) => rw.section === s.name)}
                  />
                </div>
              </div>
            </article>
          ))}
        </div>
      </section>

      {/* 3. The full ranked list. */}
      <section>
        <h2 className={`mb-1 ${T.title}`}>Everything worth fixing, in order</h2>
        <p className={`mb-4 ${T.quiet}`}>
          Ranked by how many visitors it affects, how badly, and how sure we are — divided by how much work it is.
        </p>
        <ol className="space-y-4">
          {r.recommendations.map((rec, i) => <Fix key={rec.id} rec={rec} rank={i + 1} />)}
        </ol>
      </section>

      <footer className={`${T.eyebrow} border-t border-[var(--color-line)] pt-4`}>
        Audited {new Date(r.ran_at).toLocaleString()} · model {r.cost.model} · cost ${r.cost.usd.toFixed(4)}
      </footer>
    </div>
  )
}

function Fix({ rec, rank }) {
  return (
    <li className={`${T.surface} p-4`} style={{ borderLeftWidth: '2px', borderLeftColor: SEVERITY[rec.priority]?.bar }}>
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <p className="font-medium text-[var(--color-paper)]">
          <span className={`${T.figure} mr-2 text-[var(--color-mist)]`}>{String(rank).padStart(2, '0')}</span>
          {rec.title}
        </p>
        <PriorityTag value={rec.priority} />
      </div>
      <dl className="mt-3 space-y-1.5 text-sm">
        {/* Evidence first, always. It is the reason to believe the rest. */}
        <div><dt className={`inline ${T.eyebrow}`}>Evidence </dt><dd className={`inline ${T.body}`}>{rec.evidence}</dd></div>
        <div><dt className={`inline ${T.eyebrow}`}>Do this </dt><dd className={`inline ${T.body}`}>{rec.suggested_fix}</dd></div>
        <div><dt className={`inline ${T.eyebrow}`}>If it works </dt><dd className={`inline ${T.body}`}>{rec.expected_impact}</dd></div>
      </dl>
      <p className={`${T.eyebrow} mt-2`}>in {rec.section}</p>
    </li>
  )
}
