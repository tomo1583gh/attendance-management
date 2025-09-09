@extends('layouts.app')

@section('title', '勤怠一覧（管理者）')
@section('state', 'before')

@section('content')
  @php
    use Carbon\Carbon;
    $d = isset($date) ? Carbon::parse($date) : Carbon::today();
    $heading = ($dateText ?? $d->format('Y年n月j日')) . 'の勤怠';
    $centerDate = $d->format('Y/m/d');
  @endphp

  <h1 class="section-title">{{ $heading }}</h1>

  {{-- 日切替：前日 / YYYY/MM/dd / 翌日 --}}
  <div class="day-switch-area">
    <div class="day-switch">
      <a class="day-btn"
       href="{{ route('admin.attendances.daily', ['date' => $prevDate]) }}">← 前日</a>

    <div class="day-display">
      <span class="day-display__icon" aria-hidden="true">📅</span>
      <span class="day-display__text">{{ $centerDate }}</span>
    </div>

    <a class="day-btn"
       href="{{ route('admin.attendances.daily', ['date' => $nextDate]) }}">翌日 →</a>
    </div>
  </div>

  <div class="card table-wrap table-wrap--admin-daily">
    <table class="table table--admin-attend">
      <thead>
        <tr>
          <th>名前</th>
          <th>出勤</th>
          <th>退勤</th>
          <th>休憩</th>
          <th>合計</th>
          <th>詳細</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($rows as $row)
          <tr>
            <td class="t-left">{{ $row->user_name }}</td>
            <td>{{ $row->in_time  ?? '' }}</td>
            <td>{{ $row->out_time ?? '' }}</td>
            <td>{{ $row->break_text ?? '' }}</td>
            <td>{{ $row->total_text ?? '' }}</td>
            <td>
              @if(!empty($row->attendance_id))
                <a class="link-detail"
                   href="{{ route('admin.attendances.show', [
                        'id' => $row->attendance_id,
                        'return_to' => request()->fullUrl()
                        ]) }}">
                  詳細
                </a>
              @else
                <span class="link-detail" style="opacity:.5;cursor:not-allowed;">詳細</span>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td class="empty" colspan="6">データがありません</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
@endsection
