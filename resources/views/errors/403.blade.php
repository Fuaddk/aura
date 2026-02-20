@extends('errors.layout')

@section('code', '403')
@section('icon', '🔒')
@section('title', 'Adgang nægtet')
@section('description', 'Du har ikke tilladelse til at se denne side. Kontakt os hvis du mener dette er en fejl.')

@section('actions')
    <a href="{{ url('/dashboard') }}" class="btn-primary">Gå til forsiden</a>
    <a href="javascript:history.back()" class="btn-ghost">Gå tilbage</a>
@endsection
