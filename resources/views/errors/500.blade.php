@extends('errors.layout')

@section('code', '500')
@section('icon', '⚙️')
@section('title', 'Serverfejl')
@section('description', 'Noget gik galt på vores side. Vi er blevet underrettet og arbejder på at løse det. Prøv igen om lidt.')

@section('actions')
    <a href="javascript:history.back()" class="btn-primary">Gå tilbage</a>
@endsection
