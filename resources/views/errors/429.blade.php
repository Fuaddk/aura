@extends('errors.layout')

@section('code', '429')
@section('icon', '⚡')
@section('title', 'For mange forespørgsler')
@section('description', 'Du har sendt for mange forespørgsler på kort tid. Vent et øjeblik og prøv igen.')

@section('actions')
    <a href="{{ url()->previous() ?: url('/dashboard') }}" class="btn-primary">Prøv igen</a>
@endsection
