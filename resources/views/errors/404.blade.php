@extends('errors.layout')

@section('code', '404')

<<<<<<< HEAD
@section('title', 'Halaman tidak ditemukan')

@section('message', $message ?? 'Halaman yang Anda cari tidak ditemukan atau ID destinasi tidak tersedia di dalam ontologi.')

@section('actions')
    <a href="{{ url('/') }}" class="button button--primary">Kembali ke beranda</a>
    <a href="{{ url('/destinations') }}" class="button button--secondary">Jelajahi destinasi</a>
=======
@section('title', 'Page not found')

@section('message', 'The page you are looking for may have moved, or the destination ID does not exist in the ontology.')

@section('actions')
    <a href="{{ url('/') }}" class="button button--primary">Back to dashboard</a>
    <a href="{{ url('/destinations') }}" class="button button--secondary">Browse destinations</a>
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
@endsection
