@extends('layouts.app')

@section('title', '勤怠詳細')
@section('state', 'before')

@section('content')
  <h1 class="section-title">勤怠詳細</h1>

  @php
    use Carbon\Carbon;
    $workDate = Carbon::parse($attendance->work_date);
    $year  = $workDate->year;
    $month = (int)$workDate->format('n');
    $day   = (int)$workDate->format('j');

    $in  = $attendance->clock_in_at  ? Carbon::parse($attendance->clock_in_at)->format('H:i')  : '';
    $out = $attendance->clock_out_at ? Carbon::parse($attendance->clock_out_at)->format('H:i') : '';

    $break1 = $attendance->breaks[0] ?? null;
    $break2 = $attendance->breaks[1] ?? null;

    $b1s = $break1 && $break1->start_at ? Carbon::parse($break1->start_at)->format('H:i') : '';
    $b1e = $break1 && $break1->end_at   ? Carbon::parse($break1->end_at)->format('H:i')   : '';
    $b2s = $break2 && $break2->start_at ? Carbon::parse($break2->start_at)->format('H:i') : '';
    $b2e = $break2 && $break2->end_at   ? Carbon::parse($break2->end_at)->format('H:i')   : '';

    $userName = $attendance->user->name ?? auth()->user()->name ?? '';

    // 承認待ち判定（セッション or DB）
  $isPending = (session('pending') === true) || ($hasPending ?? false);
  @endphp

  <form method="POST" 
        action="{{ route('request.store', $attendance->id) }}" 
        class="detail-form" 
        id="detailForm"
        @if($isPending) aria-disabled="true" @endif>
    @csrf

    <div class="detail-card">
      <div class="detail-row">
        <div class="detail-label">名前</div>
        <div class="detail-field"><span class="detail-text">{{ $userName }}</span></div>
      </div>

      <div class="detail-row">
        <div class="detail-label">日付</div>
        <div class="detail-field">
          <div class="date-split">
            <span class="date-chip">{{ $year }}年</span>
            <span class="date-chip">{{ $month }}月{{ $day }}日</span>
          </div>
        </div>
      </div>

      {{-- 出勤・退勤 --}}
      <div class="detail-row">
        <div class="detail-label">出勤・退勤</div>
        <div class="detail-field">
          @if($isPending)
            <span class="detail-text">{{ $in ?: '—' }}</span>
            <span class="tilde">〜</span>
            <span class="detail-text">{{ $out ?: '—' }}</span>
          @else
            <div class="time-pair">
              <input type="time" name="clock_in"  value="{{ old('clock_in',  $in)  }}" class="time-input">
              <span class="tilde">〜</span>
              <input type="time" name="clock_out" value="{{ old('clock_out', $out) }}" class="time-input">
            </div>
          @endif
        </div>
      </div>

      {{-- 休憩 --}}
      <div class="detail-row">
        <div class="detail-label">休憩</div>
        <div class="detail-field">
          @if($isPending)
            <span class="detail-text">{{ $b1s ?: '—' }}</span>
            <span class="tilde">〜</span>
            <span class="detail-text">{{ $b1e ?: '—' }}</span>
          @else
            <div class="time-pair">
              <input type="time" name="breaks[0][start]" value="{{ old('breaks.0.start', $b1s) }}" class="time-input">
              <span class="tilde">〜</span>
              <input type="time" name="breaks[0][end]"   value="{{ old('breaks.0.end',   $b1e) }}" class="time-input">
            </div>
          @endif
        </div>
      </div>

      {{-- 休憩2 --}}
      <div class="detail-row">
        <div class="detail-label">休憩2</div>
        <div class="detail-field">
          @if($isPending)
            <span class="detail-text">{{ $b2s ?: '—' }}</span>
            <span class="tilde">〜</span>
            <span class="detail-text">{{ $b2e ?: '—' }}</span>
          @else
            <div class="time-pair">
              <input type="time" name="breaks[1][start]" value="{{ old('breaks.1.start', $b2s) }}" class="time-input">
              <span class="tilde">〜</span>
              <input type="time" name="breaks[1][end]"   value="{{ old('breaks.1.end',   $b2e) }}" class="time-input">
            </div>
          @endif
        </div>
      </div>
      
      {{-- 備考 --}}
      <div class="detail-row">
        <div class="detail-label">備考</div>
        <div class="detail-field">
          @if($isPending)
            <span class="detail-text">{{ $attendance->note ?: '—' }}</span>
          @else
            <input type="text" name="note" value="{{ old('note', $attendance->note ?? '') }}" class="note-input">
          @endif
        </div>
        </div>
      </div>


    @if($isPending)
      <div class="detail-footer">
        <p class="pending-note" role="alert">※ 承認待ちのため修正はできません。</p>
    @endif

    @unless($isPending)
      <div class="detail-actions">
        <button type="submit" id="btn-correct" class="btn-detail">修正</button>
      </div>
    @endunless
  </form>
@endsection
