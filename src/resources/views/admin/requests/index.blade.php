@extends('layouts.app')

@section('title', '申請一覧')
@section('state', 'before')

@section('content')
  {{-- 見出し（左バー付き） --}}
  <h1 class="section-title">申請一覧</h1>

  {{-- タブ（承認待ち / 承認済み）--}}
  @php
    // クエリの tab=pending|approved で切り替え
    $tab = request('tab', 'pending');
  @endphp
  <div class="request-tabs">
    <a href="{{ route('admin.requests.index', ['tab' => 'pending']) }}"
        class="request-tab {{ $tab === 'pending' ? 'is-active' : '' }}">承認待ち</a>
    <a href="{{ route('admin.requests.index', ['tab' => 'approved']) }}"
        class="request-tab {{ $tab === 'approved' ? 'is-active' : '' }}">承認済み</a>
  </div>

  {{-- 一覧テーブル --}}
  <div class="card table-wrap">
    <table class="table table--requests">
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
        @forelse($rows as $row)
          <tr>
            <td>{{ $row->status_text }}</td>
            <td class="t-left">
              {{ $row->user_name }}
            </td>
            <td>{{ $row->target_at }}</td>
            <td class="t-left">{{ $row->reason }}</td>
            <td>{{ $row->requested_at }}</td>
            <td>
              <a class="link-detail"
                 href="{{ route('admin.requests.show', ['id' => $row->id]) }}">詳細</a>
            </td>
          </tr>
        @empty
          <tr>
            <td class="empty" colspan="6">表示する申請がありません</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
@endsection
