@extends('errors.layout')

@section('code', '419')

@section('title', 'Sesi telah berakhir')

@section('message', 'Sesi Anda telah berakhir. Kembali ke beranda, lalu ulangi tindakan tersebut.')

@section('actions')
    <a href="{{ url('/') }}" class="button button--primary">Kembali ke beranda</a>
    <a href="{{ url('/destinations') }}" class="button button--secondary">Jelajahi destinasi</a>
@endsection
