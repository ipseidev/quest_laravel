@extends('errors.layout')

@php $locale = \App\Support\SiteMap::requestLocale(); @endphp

@section('title', $locale === 'en' ? 'Page not found' : 'Page introuvable')
@section('code', '404')

@section('heading')
    {{ $locale === 'en' ? 'This page does not exist.' : 'Cette page n’existe pas.' }}
@endsection

@section('body')
    {{ $locale === 'en'
        ? 'The address may have changed, or it was never here. Everything the site has is one link away.'
        : 'L’adresse a peut-être changé, ou elle n’a jamais existé. Tout le site est à un lien d’ici.' }}
@endsection
