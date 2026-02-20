@extends('errors.layout')

@section('code', '404')
@section('icon', '🔍')
@section('title', 'Siden blev ikke fundet')
@section('description', 'Den side du leder efter eksisterer ikke eller er blevet flyttet.')

@section('actions')
    <a href="javascript:history.back()" class="btn-primary">Gå tilbage</a>
@endsection
