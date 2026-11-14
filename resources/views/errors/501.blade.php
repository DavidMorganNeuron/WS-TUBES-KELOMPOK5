@extends('errors.layout')

@section('code', '501')

@section('title', 'Format ekspor tidak tersedia')

@section('message', $message)

@section('actions')
    <a href="{{ route('destinations') }}" class="button button--primary">Jelajahi destinasi</a>
@endsection
