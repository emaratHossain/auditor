import React from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter, Routes, Route, Link } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import Pages from './pages/Pages'
import Report from './pages/Report'

const queryClient = new QueryClient({
  defaultOptions: { queries: { retry: 1, refetchOnWindowFocus: false } },
})

function Shell({ children }) {
  return (
    <div className="mx-auto max-w-5xl px-5 py-8 sm:py-12">
      <header className="mb-8 flex flex-wrap items-baseline justify-between gap-3 border-b border-stone-300 pb-5">
        <Link to="/" className="text-xl font-semibold tracking-tight text-stone-900">
          Landing Page Auditor
        </Link>
        <p className="text-sm text-stone-500">
          Analytics tells you people leave. This tells you why.
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
