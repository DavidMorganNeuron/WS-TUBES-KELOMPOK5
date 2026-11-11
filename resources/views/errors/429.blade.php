@extends('errors.layout')

@section('code', '429')

@section('title', 'Too many requests')

@section('message', 'You have sent too many requests in a short time. Please wait a minute before trying again.')

@section('actions')
    <a href="{{ url('/') }}" class="button button--primary">Back to dashboard</a>
    <a href="{{ url('/destinations') }}" class="button button--secondary">Browse destinations</a>
@endsection
