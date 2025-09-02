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
  @endphp

  {{-- ★ 承認待ち表示（初期は非表示、クリック時に表示） --}}
  <p class="pending-notice {{ session('pending') ? 'is-visible' : '' }}">
    承認待ちのため修正はできません。
  </p>

  <form method="POST" action="{{ route('request.store', $attendance->id) }}" class="detail-form" id="detailForm">
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

      <div class="detail-row">
        <div class="detail-label">出勤・退勤</div>
        <div class="detail-field">
          <div class="time-pair">
            <input type="time" name="clock_in"  value="{{ old('clock_in',  $in)  }}" class="time-input">
            <span class="tilde">〜</span>
            <input type="time" name="clock_out" value="{{ old('clock_out', $out) }}" class="time-input">
          </div>
        </div>
      </div>

      <div class="detail-row">
        <div class="detail-label">休憩</div>
        <div class="detail-field">
          <div class="time-pair">
            <input type="time" name="breaks[0][start]" value="{{ old('breaks.0.start', $b1s) }}" class="time-input">
            <span class="tilde">〜</span>
            <input type="time" name="breaks[0][end]"   value="{{ old('breaks.0.end',   $b1e) }}" class="time-input">
          </div>
        </div>
      </div>

      <div class="detail-row">
        <div class="detail-label">休憩2</div>
        <div class="detail-field">
          <div class="time-pair">
            <input type="time" name="breaks[1][start]" value="{{ old('breaks.1.start', $b2s) }}" class="time-input">
            <span class="tilde">〜</span>
            <input type="time" name="breaks[1][end]"   value="{{ old('breaks.1.end',   $b2e) }}" class="time-input">
          </div>
        </div>
      </div>

      <div class="detail-row">
        <div class="detail-label">備考</div>
        <div class="detail-field">
          <input type="text" name="note" value="{{ old('note', $attendance->note ?? '') }}" class="note-input">
        </div>
      </div>
    </div>

    <div class="detail-actions">
      {{-- クリック時、承認待ちなら送信せずメッセージ表示に切替 --}}
      <button type="submit"
              id="btn-correct"
              class="btn-detail"
              data-is-pending="{{ $hasPending ? 1 : 0 }}">
        修正
      </button>
    </div>
  </form>

  {{-- フロント即時切替用の最小限JS --}}
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const btn = document.getElementById('btn-correct');
      const msg = document.querySelector('.pending-notice');
      const form = document.getElementById('detailForm');

      if (!btn || !msg || !form) return;

      btn.addEventListener('click', function (e) {
        if (this.dataset.isPending === '1') {
          e.preventDefault();                // 送信させない
          msg.classList.add('is-visible');   // メッセージを表示
        }
      });
    });
  </script>
@endsection
