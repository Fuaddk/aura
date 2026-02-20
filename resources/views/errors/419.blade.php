@extends('errors.layout')

@section('code', '419')
@section('icon', '⏱️')
@section('title', 'Session udløbet')
@section('description', 'Din session er udløbet. Genindlæs siden og prøv igen.')

@section('actions')
    <a href="{{ url()->previous() ?: url('/') }}" class="btn-primary">Genindlæs siden</a>
@endsection
