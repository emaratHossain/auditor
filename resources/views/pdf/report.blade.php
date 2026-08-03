{{--
  A print layout, deliberately NOT the React screen.
  Paper has different needs: no hover, no clicking through, fixed width,
  page breaks that fall in sensible places.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $audit->page->name }} — DropSense AI conversion audit</title>
<style>
  * { box-sizing: border-box; }
  body { font-family: Georgia, 'Times New Roman', serif; color: #1c1917; font-size: 11pt; line-height: 1.5; margin: 0; }
  h1 { font-size: 21pt; margin: 0 0 4px; }
  h2 { font-size: 13pt; margin: 26px 0 10px; border-bottom: 1px solid #d6d3d1; padding-bottom: 4px; }
  .muted { color: #78716c; font-size: 9.5pt; }
  .score { display: flex; align-items: baseline; gap: 12px; margin: 14px 0 6px; }
  .score b { font-size: 40pt; line-height: 1; }
  .cats { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 9.5pt; }
  .cats td { padding: 4px 6px; border-bottom: 1px solid #e7e5e4; }
  .fix { border-left: 3px solid #a8a29e; padding: 6px 0 6px 12px; margin-bottom: 14px; page-break-inside: avoid; }
  .fix.high { border-left-color: #b91c1c; }
  .fix.medium { border-left-color: #b45309; }
  .fix.low { border-left-color: #15803d; }
  .fix h3 { font-size: 11.5pt; margin: 0 0 5px; }
  .fix p { margin: 0 0 3px; }
  .tag { font-size: 8pt; text-transform: uppercase; letter-spacing: .06em; color: #57534e; }
  .label { font-weight: bold; color: #57534e; }
  .shot { page-break-inside: avoid; margin-bottom: 16px; }
  .shot img { width: 100%; border: 1px solid #d6d3d1; }
  .rewrite { border: 1px solid #d6d3d1; padding: 8px 10px; margin: 8px 0 0; page-break-inside: avoid; }
  .rewrite-label { font-size: 8pt; text-transform: uppercase; letter-spacing: .06em; color: #57534e; margin: 0 0 4px; }
  .rewrite-old { font-size: 10pt; color: #78716c; margin: 0 0 6px; text-decoration: line-through; }
  .rewrite-new { font-size: 11pt; font-weight: bold; margin: 6px 0 1px; }
  .rewrite-why { font-size: 9.5pt; color: #57534e; margin: 0 0 4px; }
</style>
</head>
<body>

<p class="muted">DropSense AI — conversion audit</p>
<h1>{{ $audit->page->name }}</h1>
<p class="muted">{{ $audit->page->url }} · audited {{ $audit->created_at->format('j M Y, H:i') }}</p>

<div class="score">
  <b>{{ $audit->overall_score ?? '—' }}</b>
  <span class="muted">Conversion Score, out of 100 · six weighted categories</span>
</div>

<table class="cats">
  @foreach ($audit->category_scores ?? [] as $c)
    @if (is_array($c) && isset($c['label']))
      <tr>
        <td>{{ $c['label'] }} <span class="muted">· {{ $c['weight'] }}%</span></td>
        <td align="right">{{ $c['score'] ?? 'not measured' }}</td>
        <td class="muted">{{ $c['caveat'] ?? '' }}</td>
      </tr>
    @endif
  @endforeach
</table>

<h2>What to fix, in order</h2>
@forelse ($audit->recommendations as $i => $rec)
  <div class="fix {{ $rec->priority }}">
    <span class="tag">{{ $rec->priority }} · {{ $rec->section_name }}</span>
    <h3>{{ $i + 1 }}. {{ $rec->title }}</h3>
    <p><span class="label">Evidence:</span> {{ $rec->evidence }}</p>
    <p><span class="label">Do this:</span> {{ $rec->suggested_fix }}</p>
    <p><span class="label">If it works:</span> {{ $rec->expected_impact }}</p>
  </div>
@empty
  <p>Nothing on this page could be proven against a number, so nothing is claimed here.</p>
@endforelse

<h2>The page, section by section</h2>
@foreach ($sections as $section)
  <div class="shot">
    <p>
      <b>{{ $section['name'] }}</b>
      <span class="muted">
        · {{ $section['position_percent'] }}% down the page
        @if ($section['score'] !== null) · scored {{ $section['score'] }}/100 @endif
      </span>
    </p>
    @if ($section['image'])
      <img src="{{ $section['image'] }}" alt="{{ $section['name'] }}">
    @endif
    @foreach ($section['problems'] as $p)
      <p style="font-size:10pt">— {{ $p['what'] }} <span class="muted">{{ $p['fix'] }}</span></p>
    @endforeach

    {{-- The part a client can act on without opening the app. --}}
    @foreach ($rewrites[$section['name']] ?? [] as $rewrite)
      <div class="rewrite">
        <p class="rewrite-label">
          Suggested {{ $rewrite->element === 'cta' ? 'button label' : ($rewrite->element === 'subhead' ? 'supporting line' : 'headline') }}
        </p>
        <p class="rewrite-old">{{ $rewrite->original }}</p>
        @foreach ($rewrite->variants as $variant)
          <p class="rewrite-new">{{ $variant['text'] }}</p>
          <p class="rewrite-why">{{ $variant['reason'] }}</p>
        @endforeach
      </div>
    @endforeach
  </div>
@endforeach

<p class="muted" style="margin-top:24px">
  Every recommendation above names a real metric, a real number and a real section.
  Anything that could not do so was discarded rather than shown.
</p>

</body>
</html>
