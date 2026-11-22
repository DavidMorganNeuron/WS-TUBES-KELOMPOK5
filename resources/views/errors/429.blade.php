@extends('errors.layout')

@section('code', '429')

<<<<<<< HEAD
@section('title', 'Terlalu banyak permintaan')

@section('message', 'Anda mengirim terlalu banyak permintaan dalam waktu singkat. Tunggu sebentar sebelum mencoba lagi.')

@section('actions')
    <a href="{{ url('/') }}" class="button button--primary">Kembali ke beranda</a>
    <a href="{{ url('/destinations') }}" class="button button--secondary">Jelajahi destinasi</a>
=======
@section('title', 'Too many requests')

@section('message', 'You have sent too many requests in a short time. Please wait a minute before trying again.')

@section('actions')
    <a href="{{ url('/') }}" class="button button--primary">Back to dashboard</a>
    <a href="{{ url('/destinations') }}" class="button button--secondary">Browse destinations</a>
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
@endsection
