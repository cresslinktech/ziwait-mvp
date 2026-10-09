@extends('layouts.app')
@section('content')
<h1 class="mb-4 text-2xl font-bold">{{ $branch->name }}: QR code</h1>
<img src="{{ route('branch.qr.svg', $branch) }}" alt="QR code linking to the {{ $branch->name }} menu" width="240" height="240" class="rounded border bg-white p-2">
<p class="mt-4 break-all text-sm">Menu link: <a class="underline" href="{{ $link->url() }}">{{ $link->url() }}</a></p>
<form method="POST" action="{{ route('branch.qr.rotate', $branch) }}" class="mt-6" onsubmit="return confirm('Printed QR codes will stop working. Continue?')">@csrf
    <button class="text-sm text-red-700 underline">Issue a new link (revokes the current QR code)</button></form>
@endsection
