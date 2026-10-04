@extends('layouts.app')

@section('content')
<div class="text-center py-20">
    <h1 class="text-4xl font-bold mb-4">
        Welcome to Laravel App
    </h1>

    <p class="text-gray-600 mb-6">
        Simple authentication system built with Laravel
    </p>

    @guest
        <a href="/register"
           class="px-6 py-3 bg-blue-600 text-white rounded">
            Get Started
        </a>
    @endguest
</div>
@endsection
