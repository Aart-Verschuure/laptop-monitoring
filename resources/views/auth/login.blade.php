@extends('layouts.app', ['title' => 'Inloggen'])

@section('content')
    <div class="mx-auto mt-16 max-w-sm">
        <h1 class="mb-6 text-center text-2xl font-semibold">💻 Laptop Monitoring</h1>

        <form method="POST" action="{{ route('login') }}" class="space-y-4 rounded-xl bg-white p-6 shadow">
            @csrf

            <div>
                <label for="email" class="mb-1 block text-sm font-medium">E-mailadres</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none">
                @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="password" class="mb-1 block text-sm font-medium">Wachtwoord</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none">
                @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remember" class="rounded"> Onthoud mij
            </label>

            <button class="w-full rounded-lg bg-indigo-600 py-2 font-medium text-white hover:bg-indigo-700">Inloggen</button>
        </form>
    </div>
@endsection
