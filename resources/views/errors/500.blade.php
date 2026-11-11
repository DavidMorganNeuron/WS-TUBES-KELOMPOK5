@extends('errors.layout')

@section('code', '500')

@section('title', 'Something went wrong')

@section('message', 'The server could not complete this request. Please try again in a moment.')

@section('actions')
    <a href="{{ url('/') }}" class="button button--primary">Back to dashboard</a>
    <a href="{{ url('/destinations') }}" class="button button--secondary">Browse destinations</a>
@endsection
