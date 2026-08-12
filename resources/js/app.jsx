import React from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter, Routes, Route, Link } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import Pages from './pages/Pages'
import Report from './pages/Report'
import { Wordmark } from './features/report/Genie'

const queryClient = new QueryClient({
  defaultOptions: { queries: { retry: 1, refetchOnWindowFocus: false } },
})

function Shell({ children }) {
  return (
    <div className="mx-auto max-w-5xl px-5 py-8 sm:py-10">
      <header className="mb-8 flex flex-wrap items-center justify-between gap-3 border-b border-[var(--color-line)] pb-5">
        <Link to="/" className="flex items-center gap-2.5">
          <Wordmark />
        </Link>
        {/* Cramped next to the wordmark on a phone, and it is a tagline — the
            first thing that should give up its space. */}
        <p className="hidden font-mono text-[10px] uppercase tracking-[0.18em] text-[var(--color-mist)] sm:block">
          Three wishes for your landing page
        </p>
      </header>
      {children}
    </div>
  )
}

createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <Shell>
          <Routes>
            <Route path="/" element={<Pages />} />
            <Route path="/audits/:id" element={<Report />} />
          </Routes>
        </Shell>
      </BrowserRouter>
    </QueryClientProvider>
  </React.StrictMode>,
)
