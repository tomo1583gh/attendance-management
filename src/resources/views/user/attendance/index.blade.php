@php($status = $status ?? 'before')
@extends('layouts.app')

@section('title', '勤怠打刻')
@section('state', $status ?? 'before')

@section('content')
  {{-- ステータスバッジ --}}
  <div class="status-badge">
    @switch($status)
      @case('before') 勤務外 @break
      @case('working') 出勤中 @break
      @case('break') 休憩中 @break
      @case('after') 退勤済 @break
    @endswitch
  </div>

  {{-- 日付・時刻 --}}
  <div class="date">{{ $dateText }}</div>
  <div class="time">{{ $timeText }}</div>

  {{-- ===== 状態ごとのUI ===== --}}
  @if ($status === 'before')
    {{-- 出勤前 --}}
    <div class="btn-primary-wrap">
      <form method="POST" action="{{ route('attendance.clockIn') }}">
        @csrf
        <button type="submit" class="btn btn--primary">出勤</button>
      </form>
    </div>
  @endif

  @if ($status === 'working')
    {{-- 出勤後 --}}
    <div class="btn-row">
      <form method="POST" action="{{ route('attendance.clockOut') }}">
        @csrf
        <button type="submit" class="btn btn--primary">退勤</button>
      </form>
      <form method="POST" action="{{ route('attendance.breakIn') }}">
        @csrf
        <button type="submit" class="btn btn--ghost">休憩入</button>
      </form>
    </div>
  @endif

  @if ($status === 'break')
    {{-- 休憩中 --}}
    <div class="btn-primary-wrap">
      <form method="POST" action="{{ route('attendance.breakOut') }}">
        @csrf
        <button type="submit" class="btn btn--primary">休憩戻</button>
      </form>
    </div>
  @endif

  @if ($status === 'after')
    {{-- 退勤後 --}}
    <p class="after-message">お疲れ様でした。</p>
  @endif

  {{-- エラーメッセージやフラッシュ --}}
  @if (session('status'))
    <p style="margin-top:20px;font-weight:700;">{{ session('status') }}</p>
  @endif
  @error('attendance')
    <p style="margin-top:20px;color:#d00;font-weight:700;">{{ $message }}</p>
  @enderror
@endsection
