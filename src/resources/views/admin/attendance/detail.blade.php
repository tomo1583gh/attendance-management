@extends('layouts.app')

@section('title', '勤怠詳細')

@section('content')
<div class="container">
  <h1 class="page-title">勤怠詳細</h1>

  @if ($errors->any())
    <div class="alert-error" role="alert">
      <ul>
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  @if (session('status'))
    <div class="alert-success" role="status">{{ session('status') }}</div>
  @endif

  @php
    // 休憩はコレクションなので get(index) で安全に取得
    $b0 = $attendance->breaks->get(0);
    $b1 = $attendance->breaks->get(1);
  @endphp

  <form action="{{ route('admin.attendance.update', ['id' => $attendance->id]) }}" method="post" class="detail-form">
    @csrf
    @method('PUT')

    <div class="detail-card">
      <div class="detail-row header">
        <div class="detail-col label">名前</div>
        <div class="detail-col value">
          {{ $attendance->user->last_name ?? '' }}　{{ $attendance->user->first_name ?? '' }}
        </div>
      </div>

      <div class="detail-row">
        <div class="detail-col label">日付</div>
        <div class="detail-col value date-split">
          <span class="date-year">{{ optional($attendance->work_date)->format('Y') }}年</span>
          <span class="date-monthday">{{ optional($attendance->work_date)->format('n月j日') }}</span>
        </div>
      </div>

      {{-- 出勤・退勤（*_at を参照） --}}
      <div class="detail-row">
        <div class="detail-col label">出勤・退勤</div>
        <div class="detail-col value time-range">
          <input type="time" name="clock_in"
                 value="{{ old('clock_in', optional($attendance->clock_in_at)->format('H:i')) }}"
                 class="input-time">
          <span class="time-tilde">〜</span>
          <input type="time" name="clock_out"
                 value="{{ old('clock_out', optional($attendance->clock_out_at)->format('H:i')) }}"
                 class="input-time">
        </div>
      </div>

      {{-- 休憩1（start_at / end_at） --}}
      <div class="detail-row">
        <div class="detail-col label">休憩</div>
        <div class="detail-col value time-range">
          <input type="time" name="breaks[0][start]"
                 value="{{ old('breaks.0.start', optional($b0?->start_at)->format('H:i')) }}"
                 class="input-time">
          <span class="time-tilde">〜</span>
          <input type="time" name="breaks[0][end]"
                 value="{{ old('breaks.0.end', optional($b0?->end_at)->format('H:i')) }}"
                 class="input-time">
        </div>
      </div>

      {{-- 休憩2（追加フィールド） --}}
      <div class="detail-row">
        <div class="detail-col label">休憩2</div>
        <div class="detail-col value time-range">
          <input type="time" name="breaks[1][start]"
                 value="{{ old('breaks.1.start', optional($b1?->start_at)->format('H:i')) }}"
                 class="input-time">
          <span class="time-tilde">〜</span>
          <input type="time" name="breaks[1][end]"
                 value="{{ old('breaks.1.end', optional($b1?->end_at)->format('H:i')) }}"
                 class="input-time">
        </div>
      </div>

      <div class="detail-row">
        <div class="detail-col label">備考</div>
        <div class="detail-col value">
          <textarea name="note" class="input-note" rows="3" placeholder="備考を入力してください">{{ old('note', $attendance->note) }}</textarea>
        </div>
      </div>
    </div>

    <div class="detail-actions">
      <button type="submit" class="btn-primary">修正</button>
    </div>
  </form>
</div>
@endsection
