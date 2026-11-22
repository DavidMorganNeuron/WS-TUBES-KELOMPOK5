@extends('errors.layout')

@section('code', '419')

<<<<<<< HEAD
@section('title', 'Sesi telah berakhir')

@section('message', 'Sesi Anda telah berakhir. Kembali ke beranda, lalu ulangi tindakan tersebut.')

@section('actions')
    <a href="{{ url('/') }}" class="button button--primary">Kembali ke beranda</a>
    <a href="{{ url('/destinations') }}" class="button button--secondary">Jelajahi destinasi</a>
=======
@section('title', 'Page expired')

@section('message', 'Your session has expired. Go back to the dashboard and try the action again.')

@section('actions')
    <a href="{{ url('/') }}" class="button button--primary">Back to dashboard</a>
    <a href="{{ url('/destinations') }}" class="button button--secondary">Browse destinations</a>
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
@endsection
