@extends('errors.layout')

@section('code', '401')
@section('icon', '👤')
@section('title', 'Log ind påkrævet')
@section('description', 'Du skal være logget ind for at se denne side.')

@section('actions')
    <a href="{{ route('login') }}" class="btn-primary">Log ind</a>
    <a href="{{ url('/') }}" class="btn-ghost">Forside</a>
@endsection
