@extends('layouts.app')

@section('title', '申請一覧')
@section('state', 'before')

@section('content')
  {{-- 見出し（左バー付き） --}}
  <h1 class="section-title">申請一覧</h1>

  {{-- タブ（承認待ち / 承認済み） --}}
  @php($tab = $tab ?? 'pending')
  <div class="tabs-bar">
    <a href="{{ route('request.list', ['tab' => 'pending']) }}"
       class="tabs__link {{ $tab === 'pending' ? 'is-active' : '' }}">承認待ち</a>
    <a href="{{ route('request.list', ['tab' => 'approved']) }}"
       class="tabs__link {{ $tab === 'approved' ? 'is-active' : '' }}">承認済み</a>
  </div>

  {{-- 一覧カード（テーブル） --}}
  <div class="card table-wrap table-wrap--request">
    <table class="table table--request">
      <thead>
        <tr>
          <th>状態</th>
          <th>名前</th>
          <th>対象日時</th>
          <th>申請理由</th>
          <th>申請日時</th>
          <th>詳細</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($rows as $row)
          <tr>
            <td>{{ $row->status_label }}</td>
            <td>{{ $row->user_name }}</td>
            <td>{{ $row->target_date }}</td>   {{-- 例: 2023/06/01 --}}
            <td class="t-left">{{ $row->reason }}</td>
            <td>{{ $row->requested_at }}</td>  {{-- 例: 2023/06/02 --}}
            <td>
              @if(!empty($row->attendance_id))
                <a class="link-detail" href="{{ route('attendance.detail', $row->attendance_id) }}">詳細</a>
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
