@extends('errors.layout')

@section('code', '404')

@section('title', 'Halaman tidak ditemukan')

@section('message', $message ?? 'Halaman yang Anda cari tidak ditemukan atau ID destinasi tidak tersedia di dalam ontologi.')

@section('actions')
    <a href="{{ url('/') }}" class="button button--primary">Kembali ke beranda</a>
    <a href="{{ url('/destinations') }}" class="button button--secondary">Jelajahi destinasi</a>
@endsection
