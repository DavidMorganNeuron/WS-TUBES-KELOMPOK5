@extends('errors.layout')

@section('code', '429')

@section('title', 'Terlalu banyak permintaan')

@section('message', 'Anda mengirim terlalu banyak permintaan dalam waktu singkat. Tunggu sebentar sebelum mencoba lagi.')

@section('actions')
    <a href="{{ url('/') }}" class="button button--primary">Kembali ke beranda</a>
    <a href="{{ url('/destinations') }}" class="button button--secondary">Jelajahi destinasi</a>
@endsection
