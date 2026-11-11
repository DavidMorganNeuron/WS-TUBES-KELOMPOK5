@extends('errors.layout')

@section('code', '404')

@section('title', 'Page not found')

@section('message', 'The page you are looking for may have moved, or the destination ID does not exist in the ontology.')

@section('actions')
    <a href="{{ url('/') }}" class="button button--primary">Back to dashboard</a>
    <a href="{{ url('/destinations') }}" class="button button--secondary">Browse destinations</a>
@endsection
