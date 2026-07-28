@extends('errors.layout')

@php $locale = \App\Support\SiteMap::requestLocale(); @endphp

@section('title', $locale === 'en' ? 'Something broke' : 'Quelque chose a cassé')
@section('code', '500')

@section('heading')
    {{ $locale === 'en' ? 'Something broke on our side.' : 'Quelque chose a cassé de notre côté.' }}
@endsection

@section('body')
    {{ $locale === 'en'
        ? 'Nothing you wrote is affected — your journal lives on your phone. Try again in a moment.'
        : 'Rien de ce que tu as écrit n’est touché : ton journal vit sur ton téléphone. Réessaie dans un instant.' }}
@endsection
