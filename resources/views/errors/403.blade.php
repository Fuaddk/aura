@extends('errors.layout')

@section('code', '403')
@section('icon', '🔒')
@section('title', 'Adgang nægtet')
@section('description', 'Du har ikke tilladelse til at se denne side. Kontakt os hvis du mener dette er en fejl.')

@section('actions')
    <a href="javascript:history.back()" class="btn-primary">Gå tilbage</a>
@endsection
