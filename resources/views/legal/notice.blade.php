@extends('legal.layout')

@section('title', $lang === 'fr' ? 'Mentions légales' : 'Legal notice')

@section('content')
@php
    $publisher = $legal['publisher'];
    $hosting = $legal['hosting'];

    // LCEN art. 1-1, I, 4° wants the host's address and phone alongside its
    // name. Append whichever we actually hold; §3 falls back to an on-request
    // line when either is missing. Built here rather than with inline @if
    // directives — Blade will not compile a second directive glued straight
    // onto an @endif, which silently breaks the page.
    $hostExtras = array_values(array_filter([
        $hosting['provider_address'],
        $hosting['provider_phone'],
    ]));
    $hostSuffix = $hostExtras === [] ? '' : ' — '.implode(' — ', $hostExtras);
    $hostFullyIdentified = $hosting['provider_address'] && $hosting['provider_phone'];
@endphp
@if ($lang === 'fr')
    <h1>Mentions légales</h1>
    <p class="updated">Dernière mise à jour : {{ $legal['last_updated']['notice']['fr'] }}</p>

    <h2>1. Éditeur</h2>
    <p>
        Nacre est édité par une personne physique exerçant à titre professionnel, immatriculée
        au Registre national des entreprises (RNE).
    </p>
    <ul class="identity">
        <li><strong>Éditeur :</strong> {{ $publisher['name'] }}</li>
        <li><strong>Forme juridique :</strong> entrepreneur individuel (EI)</li>
        <li><strong>SIREN :</strong> {{ $publisher['siren'] }}</li>
        <li><strong>SIRET (siège) :</strong> {{ $publisher['siret'] }}</li>
        <li><strong>Code APE :</strong> {{ $publisher['ape'] }} — Programmation informatique</li>
        <li><strong>N° de TVA intracommunautaire :</strong> {{ $publisher['vat'] }}</li>
        <li><strong>Pays d'établissement :</strong> {{ $publisher['country'] }}</li>
        <li><strong>Contact :</strong> <a href="mailto:{{ $legal['contact_email'] }}">{{ $legal['contact_email'] }}</a></li>
        @if ($publisher['address'])
            <li><strong>Adresse postale :</strong> {{ $publisher['address'] }}</li>
        @endif
        @if ($publisher['phone'])
            <li><strong>Téléphone :</strong> <a href="tel:{{ preg_replace('/\s+/', '', $publisher['phone']) }}">{{ $publisher['phone'] }}</a></li>
        @endif
        @unless ($publisher['address'] && $publisher['phone'])
            <li><strong>{{ $publisher['address'] ? 'Téléphone' : ($publisher['phone'] ? 'Adresse postale' : 'Adresse postale et téléphone') }} :</strong> communiqués sur demande à <a href="mailto:{{ $legal['contact_email'] }}">{{ $legal['contact_email'] }}</a>, sous 48 heures ouvrées</li>
        @endunless
    </ul>

    <h2>2. Directeur de la publication</h2>
    <p>{{ $publisher['publication_director'] }}, en qualité d'éditeur.</p>

    <h2>3. Hébergement</h2>
    <p>
        L'application et la base de données sont hébergées par
        <strong>{{ $hosting['provider'] }}</strong>
        (<a href="{{ $hosting['provider_url'] }}">{{ $hosting['provider_url'] }}</a>{{ $hostSuffix }}),
        qui s'appuie sur l'infrastructure d'{{ $hosting['infrastructure'] }},
        {{ $hosting['infrastructure_address'] }}, dans la région
        <strong>{{ $hosting['region'] }} ({{ $hosting['region_label'] }})</strong>.
        @unless ($hostFullyIdentified)
            Les coordonnées postales et téléphoniques de l'hébergeur sont communiquées sur demande à
            <a href="mailto:{{ $legal['contact_email'] }}">{{ $legal['contact_email'] }}</a>.
        @endunless
    </p>
    <p>
        Les fichiers que tu ajoutes à ton journal (photos, mémos vocaux) sont stockés séparément par
        <strong>{{ $hosting['object_storage'] }}</strong>.
        La liste complète des prestataires qui hébergent ou traitent tes données figure dans la
        <a href="{{ route('legal.privacy', ['lang' => $lang]) }}">politique de confidentialité</a>.
    </p>

    <h2>4. Propriété intellectuelle</h2>
    <p>
        Le nom « Nacre », l'application, son interface, ses textes et ses éléments graphiques sont
        protégés par le droit d'auteur et le droit des marques. Toute reproduction ou réutilisation
        sans autorisation écrite est interdite.
    </p>
    <p>
        <strong>Ton journal, en revanche, t'appartient entièrement.</strong> Nous n'en revendiquons
        aucune propriété — voir les
        <a href="{{ route('legal.terms', ['lang' => $lang]) }}">conditions d'utilisation</a>.
    </p>

    <h2>5. Données personnelles</h2>
    <p>
        Le responsable du traitement est {{ $publisher['name'] }}. Les traitements, les durées de
        conservation, les sous-traitants et tes droits sont décrits dans la
        <a href="{{ route('legal.privacy', ['lang' => $lang]) }}">politique de confidentialité</a>.
        L'autorité de contrôle compétente est la CNIL (<a href="https://www.cnil.fr">cnil.fr</a>).
    </p>

    <h2>6. Signalement de contenu</h2>
    <p>
        Nacre est un journal personnel : ce que tu écris n'est pas publié, pas partagé entre comptes
        et n'est visible par aucun autre utilisateur. Il n'y a donc pas d'espace public à modérer.
        Tout signalement relatif au service peut néanmoins être adressé à
        <a href="mailto:{{ $legal['contact_email'] }}">{{ $legal['contact_email'] }}</a>.
    </p>

    <h2>7. Droit applicable</h2>
    <p>
        Les présentes mentions sont régies par le droit français. Les modalités de réclamation et de
        règlement des litiges figurent dans les
        <a href="{{ route('legal.terms', ['lang' => $lang]) }}">conditions d'utilisation</a>.
    </p>
@else
    <h1>Legal notice</h1>
    <p class="updated">Last updated: {{ $legal['last_updated']['notice']['en'] }}</p>

    <p>
        Nacre is published from France. French law (LCEN art. 1-1, as recast by law
        n° 2024-449 of 21 May 2024) requires anyone operating an online service professionally
        to identify themselves publicly. The details below cover that identification; the
        postal address and telephone number are provided on request rather than published.
    </p>

    <h2>1. Publisher</h2>
    <ul class="identity">
        <li><strong>Publisher:</strong> {{ $publisher['name'] }}</li>
        <li><strong>Legal form:</strong> sole trader (<em>entrepreneur individuel</em>), registered with the French National Business Register (RNE)</li>
        <li><strong>SIREN:</strong> {{ $publisher['siren'] }}</li>
        <li><strong>SIRET (registered establishment):</strong> {{ $publisher['siret'] }}</li>
        <li><strong>Activity code (APE):</strong> {{ $publisher['ape'] }} — Computer programming</li>
        <li><strong>EU VAT number:</strong> {{ $publisher['vat'] }}</li>
        <li><strong>Country of establishment:</strong> {{ $publisher['country'] }}</li>
        <li><strong>Contact:</strong> <a href="mailto:{{ $legal['contact_email'] }}">{{ $legal['contact_email'] }}</a></li>
        @if ($publisher['address'])
            <li><strong>Postal address:</strong> {{ $publisher['address'] }}</li>
        @endif
        @if ($publisher['phone'])
            <li><strong>Telephone:</strong> <a href="tel:{{ preg_replace('/\s+/', '', $publisher['phone']) }}">{{ $publisher['phone'] }}</a></li>
        @endif
        @unless ($publisher['address'] && $publisher['phone'])
            <li><strong>{{ $publisher['address'] ? 'Telephone' : ($publisher['phone'] ? 'Postal address' : 'Postal address and telephone') }}:</strong> provided on request at <a href="mailto:{{ $legal['contact_email'] }}">{{ $legal['contact_email'] }}</a>, within 48 business hours</li>
        @endunless
    </ul>

    <h2>2. Publication director</h2>
    <p>{{ $publisher['publication_director'] }}, as publisher.</p>

    <h2>3. Hosting</h2>
    <p>
        The application and the database are hosted by <strong>{{ $hosting['provider'] }}</strong>
        (<a href="{{ $hosting['provider_url'] }}">{{ $hosting['provider_url'] }}</a>{{ $hostSuffix }}),
        which runs on {{ $hosting['infrastructure'] }} infrastructure,
        {{ $hosting['infrastructure_address'] }}, in the
        <strong>{{ $hosting['region'] }} ({{ $hosting['region_label'] }})</strong> region.
        @unless ($hostFullyIdentified)
            The host's postal address and telephone number are provided on request at
            <a href="mailto:{{ $legal['contact_email'] }}">{{ $legal['contact_email'] }}</a>.
        @endunless
    </p>
    <p>
        Files you add to your journal (photos, voice notes) are stored separately by
        <strong>{{ $hosting['object_storage'] }}</strong>. The full list of providers that host or
        process your data is in the
        <a href="{{ route('legal.privacy', ['lang' => $lang]) }}">privacy policy</a>.
    </p>

    <h2>4. Intellectual property</h2>
    <p>
        The name “Nacre”, the app, its interface, its texts and its graphics are protected by
        copyright and trademark law. Reproduction or reuse without written permission is prohibited.
    </p>
    <p>
        <strong>Your journal, however, is entirely yours.</strong> We claim no ownership over it —
        see the <a href="{{ route('legal.terms', ['lang' => $lang]) }}">terms of service</a>.
    </p>

    <h2>5. Personal data</h2>
    <p>
        The data controller is {{ $publisher['name'] }}. Processing purposes, retention periods,
        sub-processors and your rights are described in the
        <a href="{{ route('legal.privacy', ['lang' => $lang]) }}">privacy policy</a>. The competent
        supervisory authority is the French CNIL (<a href="https://www.cnil.fr">cnil.fr</a>).
    </p>

    <h2>6. Reporting content</h2>
    <p>
        Nacre is a personal journal: what you write is not published, not shared between accounts and
        not visible to any other user, so there is no public space to moderate. Any report about the
        service can still be sent to
        <a href="mailto:{{ $legal['contact_email'] }}">{{ $legal['contact_email'] }}</a>.
    </p>

    <h2>7. Governing law</h2>
    <p>
        This notice is governed by French law. Complaint handling and dispute resolution are covered
        in the <a href="{{ route('legal.terms', ['lang' => $lang]) }}">terms of service</a>.
    </p>
@endif
@endsection
