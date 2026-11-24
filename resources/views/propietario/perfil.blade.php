@extends('layouts.app')
@section('title', 'Mi perfil')
@section('heading', 'Mi perfil')
@section('back', route('propietario.inicio'))
@section('content')
@include('partials.perfil')
@endsection
