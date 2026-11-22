@extends('errors.layout')

@section('code', '500')

<<<<<<< HEAD
@section('title', 'Terjadi kesalahan')

@section('message', 'Server tidak dapat menyelesaikan permintaan ini. Silakan coba lagi beberapa saat.')

@section('actions')
    <a href="{{ url('/') }}" class="button button--primary">Kembali ke beranda</a>
    <a href="{{ url('/destinations') }}" class="button button--secondary">Jelajahi destinasi</a>
=======
@section('title', 'Something went wrong')

@section('message', 'The server could not complete this request. Please try again in a moment.')

@section('actions')
    <a href="{{ url('/') }}" class="button button--primary">Back to dashboard</a>
    <a href="{{ url('/destinations') }}" class="button button--secondary">Browse destinations</a>
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
@endsection
