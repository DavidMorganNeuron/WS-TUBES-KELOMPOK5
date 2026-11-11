@extends('errors.layout')

@section('code', '419')

@section('title', 'Page expired')

@section('message', 'Your session has expired. Go back to the dashboard and try the action again.')

@section('actions')
    <a href="{{ url('/') }}" class="button button--primary">Back to dashboard</a>
    <a href="{{ url('/destinations') }}" class="button button--secondary">Browse destinations</a>
@endsection
