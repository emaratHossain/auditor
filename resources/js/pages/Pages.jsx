import React, { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import client from '../api/client'
import { Skeleton, EmptyState, ErrorState } from '../features/report/ui'
import MetricsForm from '../features/report/MetricsForm'

export default function Pages() {
  const qc = useQueryClient()
  const navigate = useNavigate()
  const [form, setForm] = useState({ name: '', url: '' })
  const [fieldErrors, setFieldErrors] = useState({})
  const [auditingPage, setAuditingPage] = useState(null)

  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['pages'],
    queryFn: async () => (await client.get('/pages')).data.data,
  })

  const addPage = useMutation({
    mutationFn: (payload) => client.post('/pages', payload),
    onSuccess: () => {
      setForm({ name: '', url: '' })
      setFieldErrors({})
      qc.invalidateQueries({ queryKey: ['pages'] })
    },
    // The server is the only place validation is real; we just show what it said.
    onError: (e) => setFieldErrors(e.fields ?? {}),
  })

  const startAudit = useMutation({
    mutationFn: ({ pageId, metrics }) => client.post(`/pages/${pageId}/audits`, metrics),
    onSuccess: (res) => navigate(`/audits/${res.data.data.id}`),
  })

  return (
    <div className="space-y-10">
      <section>
        <h2 className="mb-1 text-lg font-semibold">Add a landing page</h2>
        <p className="mb-4 text-sm text-stone-500">
          Paste the address of a page you want looked at. You will be asked for its numbers when you run an audit.
        </p>

        <form
          className="grid gap-3 sm:grid-cols-[1fr_2fr_auto]"
          onSubmit={(e) => { e.preventDefault(); addPage.mutate(form) }}
        >
          <div>
            <input
              className="w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-sm"
              placeholder="Name, e.g. Spring campaign"
              value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
            />
            {fieldErrors.name && <p className="mt-1 text-xs text-red-700">{fieldErrors.name[0]}</p>}
          </div>
          <div>
            <input
              className="w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-sm"
              placeholder="https://example.com/landing"
              value={form.url}
              onChange={(e) => setForm({ ...form, url: e.target.value })}
            />
            {fieldErrors.url && <p className="mt-1 text-xs text-red-700">{fieldErrors.url[0]}</p>}
          </div>
          <button
            type="submit"
            disabled={addPage.isPending}
            className="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 disabled:opacity-50"
          >
            {addPage.isPending ? 'Adding…' : 'Add page'}
          </button>
        </form>
      </section>

      <section>
        <h2 className="mb-4 text-lg font-semibold">Your pages</h2>

        {isLoading && <Skeleton lines={3} />}

        {isError && <ErrorState message={error.friendly} onRetry={refetch} />}

        {data?.length === 0 && (
          <EmptyState title="No pages yet">
            Add one above and you can run your first audit straight away.
          </EmptyState>
        )}

        <ul className="space-y-3">
          {data?.map((page) => (
            <li key={page.id} className="rounded-lg border border-stone-200 bg-white p-4">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="min-w-0">
                  <p className="font-medium">{page.name}</p>
                  <p className="truncate text-sm text-stone-500">{page.url}</p>
                </div>
                <div className="flex items-center gap-3">
                  {page.latest_audit?.score != null ? (
                    <button
                      onClick={() => navigate(`/audits/${page.latest_audit.id}`)}
                      className="rounded-full bg-stone-100 px-3 py-1 text-sm font-semibold"
                      title="Open the last report"
                    >
                      {page.latest_audit.score}<span className="text-stone-400">/100</span>
                    </button>
                  ) : (
                    <span className="text-sm text-stone-400">Never audited</span>
                  )}
                  <button
                    onClick={() => setAuditingPage(page)}
                    className="rounded-md border border-stone-300 px-3 py-1.5 text-sm font-medium hover:bg-stone-50"
                  >
                    Run audit
                  </button>
                </div>
              </div>
            </li>
          ))}
        </ul>
      </section>

      {auditingPage && (
        <MetricsForm
          page={auditingPage}
          submitting={startAudit.isPending}
          errors={startAudit.error?.fields ?? {}}
          onCancel={() => setAuditingPage(null)}
          onSubmit={(metrics) => startAudit.mutate({ pageId: auditingPage.id, metrics })}
        />
      )}
    </div>
  )
}
