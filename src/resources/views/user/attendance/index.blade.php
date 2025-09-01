@extends('layouts.app')
@section('title','出勤登録')

@section('content')
<h2 class="page-title">出勤登録（動作テスト）</h2>

<article class="card">
  <header class="card-header">
    <h3 class="card-title">本日の勤怠</h3>
    <p class="muted">{{ now()->format('Y/m/d H:i') }}</p>
  </header>
  <div class="card-body">
    <dl class="kv">
      <dt>ステータス</dt>
      <dd><span class="badge">{{ $attendance->status ?? '勤務外' }}</span></dd>
      <dt>出勤</dt>
      <dd>{{ optional($attendance->clock_in_at)->format('H:i') ?? '—' }}</dd>
      <dt>退勤</dt>
      <dd>{{ optional($attendance->clock_out_at)->format('H:i') ?? '—' }}</dd>
      <dt>休憩</dt>
      <dd>
        @forelse($attendance->breaks ?? [] as $b)
          <div>{{ optional($b->start_at)->format('H:i') }} - {{ optional($b->end_at)->format('H:i') }}</div>
        @empty
          なし
        @endforelse
      </dd>
    </dl>

    <div class="btn-row">
      @if(is_null($attendance->clock_in_at))
        <form method="POST" action="/attendance/clock-in">@csrf<button class="btn">出勤</button></form>
      @endif

      @if(!is_null($attendance->clock_in_at) && is_null($attendance->clock_out_at))
        @php $openBreak = $attendance->breaks()->whereNull('end_at')->exists(); @endphp
        @if(!$openBreak)
          <form method="POST" action="/attendance/break-in">@csrf<button class="btn btn-outline">休憩入</button></form>
        @else
          <form method="POST" action="/attendance/break-out">@csrf<button class="btn btn-outline">休憩戻</button></form>
        @endif
        <form method="POST" action="/attendance/clock-out" class="ml-auto">@csrf<button class="btn danger">退勤</button></form>
      @endif
    </div>
  </div>
</article>
@endsection
