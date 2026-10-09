@extends('layouts.guest', ['title' => 'Log in'])
@section('content')
<h1 class="mb-1 text-2xl font-extrabold">Log in</h1>
<p class="mb-6 text-sm text-ink-500">Welcome back. Sign in to manage your business.</p>
<form method="POST" action="{{ url('/login') }}" class="card max-w-md p-6">@csrf
<div class="mb-4"><label for="email" class="block text-sm font-medium">Email</label><input id="email" name="email" type="email" value="{{ old('email') }}" class="mt-1 w-full rounded border border-ink-100 px-3 py-2" required>@error('email')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
<div class="mb-4"><label for="password" class="block text-sm font-medium">Password</label><input id="password" name="password" type="password" value="" class="mt-1 w-full rounded border border-ink-100 px-3 py-2" required>@error('password')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
<button class="btn btn-primary w-full">Log in</button></form>
@endsection
