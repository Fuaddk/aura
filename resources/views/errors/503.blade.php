@extends('errors.layout')

@section('code', '503')
@section('icon', '🛠️')
@section('title', 'Midlertidigt utilgængelig')
@section('description', 'Aura er nede for vedligeholdelse. Vi er tilbage om lidt. Tak for din tålmodighed.')

@section('actions')
    <a href="{{ url('/') }}" class="btn-primary">Prøv igen</a>
@endsection
