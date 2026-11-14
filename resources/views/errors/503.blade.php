@extends('errors.layout')

@section('code', '503')

@section('title', 'Destination data unavailable')

@section('message', $message)

@section('actions')
    <a href="{{ url('/') }}" class="button button--primary">Try again from dashboard</a>
    <a href="{{ url('/destinations') }}" class="button button--secondary">Browse destinations</a>
@endsection
