@extends('errors.layout')

@section('code', '503')

@section('title', 'Data destinasi tidak tersedia')

@section('message', $message)

@section('actions')
    <a href="{{ url('/') }}" class="button button--primary">Coba lagi dari beranda</a>
    <a href="{{ url('/destinations') }}" class="button button--secondary">Jelajahi destinasi</a>
@endsection
