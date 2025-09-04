@extends('layouts.app')

@section('title', 'スタッフ一覧')
@section('state', 'before')

@section('content')
  <h1 class="section-title section-title--lg">スタッフ一覧</h1>

  <div class="card table-wrap table-wrap--admin-users">
    <table class="table table--admin-users">
      <thead>
        <tr>
          <th>名前</th>
          <th>メールアドレス</th>
          <th>月次勤怠</th>
        </tr>
      </thead>
      <tbody>
        @forelse($users as $user)
          <tr>
            <td class="t-left">
              {{ ($user->name ?? '') }}
            </td>
            <td>{{ $user->email }}</td>
            <td>
              <a class="link-detail"
                 href="{{ route('admin.user.attendances.monthly', ['user' => $user->id]) }}">
                詳細
              </a>
            </td>
          </tr>
        @empty
          <tr>
            <td class="empty" colspan="3">スタッフが登録されていません</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
@endsection
