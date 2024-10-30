@extends('waas::layouts.master')

@section('content')
    <h1>Hello World</h1>

    <p>Module: {!! config('waas.name') !!}</p>
@endsection
