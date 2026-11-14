@extends('errors.layout')

@section('code', '500')

@section('title', 'Terjadi kesalahan')

@section('message', 'Server tidak dapat menyelesaikan permintaan ini. Silakan coba lagi beberapa saat.')

@section('actions')
    <a href="{{ url('/') }}" class="button button--primary">Kembali ke beranda</a>
    <a href="{{ url('/destinations') }}" class="button button--secondary">Jelajahi destinasi</a>
@endsection
