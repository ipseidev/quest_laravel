@extends('legal.layout')

@section('title', $lang === 'fr' ? 'Conditions d\'utilisation' : 'Terms of Service')

@section('content')
@if ($lang === 'fr')
    <h1>Conditions d'utilisation</h1>
    <p class="updated">Dernière mise à jour : [DATE À DÉFINIR]</p>

    <div class="notice">
        Brouillon à faire valider juridiquement avant publication. Les éléments entre crochets
        ([…]) doivent être complétés (entité légale, juridiction, date).
    </div>

    <h2>1. Acceptation</h2>
    <p>En utilisant Quest, tu acceptes ces conditions. Si tu n'es pas d'accord, n'utilise pas l'application.</p>

    <h2>2. Le service</h2>
    <p>
        Quest est une application de journal intime. Elle fonctionne hors-ligne sur ton appareil ;
        un compte optionnel permet de synchroniser tes données entre appareils.
    </p>

    <h2>3. Ton compte</h2>
    <p>
        Tu es responsable de la confidentialité de tes identifiants et de l'activité sur ton compte.
        Fournis une adresse e-mail valide afin de pouvoir gérer ton accès.
    </p>

    <h2>4. Ton contenu</h2>
    <p>
        Tu restes <strong>propriétaire de tout le contenu</strong> que tu crées. Tu nous accordes
        uniquement la licence limitée nécessaire pour héberger, sauvegarder et synchroniser ce contenu
        afin de te fournir le service. Nous ne revendiquons aucune propriété et ne l'utilisons pas à
        des fins publicitaires.
    </p>

    <h2>5. Usage acceptable</h2>
    <ul>
        <li>Ne pas stocker ou diffuser de contenu illégal via le service.</li>
        <li>Ne pas tenter de perturber, surcharger ou contourner les limites de l'API.</li>
        <li>Ne pas accéder aux données d'autres utilisateurs.</li>
    </ul>

    <h2>6. Tarif</h2>
    <p>
        L'application est <strong>gratuite</strong> au lancement. Une option d'achat unique « à vie »
        (sans abonnement pour les fonctions de base) pourra être proposée ultérieurement, de façon
        optionnelle, sans publicité ni dark patterns.
    </p>

    <h2>7. Disponibilité et garanties</h2>
    <p>
        Le service est fourni « en l'état », sans garantie d'absence d'interruption ou d'erreur. Sauvegarde
        régulièrement tes données via la fonction d'export.
    </p>

    <h2>8. Limitation de responsabilité</h2>
    <p>
        Dans les limites permises par la loi, [ENTITÉ LÉGALE] ne saurait être tenue responsable des dommages
        indirects ou de la perte de données. [Clause à préciser par un juriste selon la juridiction.]
    </p>

    <h2>9. Résiliation</h2>
    <p>
        Tu peux supprimer ton compte et tes données à tout moment. Nous pouvons suspendre un accès en cas de
        violation de ces conditions.
    </p>

    <h2>10. Modifications</h2>
    <p>Nous pouvons mettre à jour ces conditions ; la date ci-dessus reflète la dernière version.</p>

    <h2>11. Droit applicable</h2>
    <p>Ces conditions sont régies par le droit de [JURIDICTION].</p>

    <h2>12. Contact</h2>
    <p><a href="mailto:ns@theresidency.io">ns@theresidency.io</a></p>
@else
    <h1>Terms of Service</h1>
    <p class="updated">Last updated: [DATE TO SET]</p>

    <div class="notice">
        Draft — pending legal review before publication. Bracketed items ([…]) must be filled in
        (legal entity, jurisdiction, date).
    </div>

    <h2>1. Acceptance</h2>
    <p>By using Quest, you agree to these terms. If you do not agree, do not use the app.</p>

    <h2>2. The service</h2>
    <p>
        Quest is a journaling app. It works offline on your device; an optional account lets you sync your
        data across devices.
    </p>

    <h2>3. Your account</h2>
    <p>
        You are responsible for keeping your credentials confidential and for activity on your account.
        Provide a valid email address so you can manage your access.
    </p>

    <h2>4. Your content</h2>
    <p>
        You retain <strong>ownership of all content</strong> you create. You grant us only the limited
        license needed to host, back up, and sync that content in order to provide the service. We claim no
        ownership and do not use it for advertising.
    </p>

    <h2>5. Acceptable use</h2>
    <ul>
        <li>Do not store or distribute illegal content through the service.</li>
        <li>Do not attempt to disrupt, overload, or circumvent API limits.</li>
        <li>Do not access other users' data.</li>
    </ul>

    <h2>6. Pricing</h2>
    <p>
        The app is <strong>free</strong> at launch. An optional one-time “lifetime” purchase (no subscription
        for core features) may be offered later — optional, with no ads and no dark patterns.
    </p>

    <h2>7. Availability and warranties</h2>
    <p>
        The service is provided “as is”, with no warranty of uninterrupted or error-free operation. Back up
        your data regularly using the export feature.
    </p>

    <h2>8. Limitation of liability</h2>
    <p>
        To the extent permitted by law, [LEGAL ENTITY] is not liable for indirect damages or loss of data.
        [Clause to be refined by counsel per jurisdiction.]
    </p>

    <h2>9. Termination</h2>
    <p>
        You may delete your account and data at any time. We may suspend access in case of a breach of these
        terms.
    </p>

    <h2>10. Changes</h2>
    <p>We may update these terms; the date above reflects the latest version.</p>

    <h2>11. Governing law</h2>
    <p>These terms are governed by the laws of [JURISDICTION].</p>

    <h2>12. Contact</h2>
    <p><a href="mailto:ns@theresidency.io">ns@theresidency.io</a></p>
@endif
@endsection
