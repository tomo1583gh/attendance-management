<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Features;


class RegisterController extends Controller
{
    public function create()
    {
        // 既に Fortify::registerView() を使っているなら不要
        return view('auth.register');
    }

    public function store(RegisterRequest $request)
    {
        $data = $request->validated();

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        event(new Registered($user));

        Auth::login($user);

        // Fortify のメール認証を有効にしている場合は誘導画面へ
        if (Features::enabled(Features::emailVerification())) {
            return redirect()->route('verification.notice');
        }

        // 誘導を使わない場合はログイン→打刻画面へ
        return redirect()->intended('/attendance');
    }
}
