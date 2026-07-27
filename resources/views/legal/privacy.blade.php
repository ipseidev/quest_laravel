@extends('legal.layout')

@section('title', $lang === 'fr' ? 'Politique de confidentialité' : 'Privacy Policy')

@section('content')
@php
    $publisher = $legal['publisher'];
    $hosting = $legal['hosting'];
@endphp
@if ($lang === 'fr')
    <h1>Politique de confidentialité</h1>
    <p class="updated">Dernière mise à jour : {{ $legal['last_updated']['privacy']['fr'] }}</p>

    <p>
        Nacre est un journal intime privé. Cette politique explique quelles données nous traitons,
        pourquoi, où elles sont stockées et quels sont tes droits. Notre principe de départ :
        <strong>tu es propriétaire de tes données</strong>, sans publicité ni revente, jamais.
    </p>

    <h2>1. Responsable du traitement</h2>
    <p>
        {{ $publisher['name'] }}, entrepreneur individuel — SIREN {{ $publisher['siren'] }},
        SIRET {{ $publisher['siret'] }}, {{ $publisher['country'] }}.
        Contact : <a href="mailto:{{ $legal['contact_email'] }}">{{ $legal['contact_email'] }}</a>
        (l'adresse postale est communiquée sur demande à cette adresse).
        Voir les <a href="{{ route('legal.notice', ['lang' => $lang]) }}">mentions légales</a>.
    </p>

    <h2>2. Données que nous traitons</h2>
    <ul>
        <li><strong>Compte (optionnel)</strong> : ton adresse e-mail. Si tu utilises « Se connecter avec Apple » ou « avec Google », l'identifiant et l'e-mail transmis par ces fournisseurs.</li>
        <li><strong>Contenu du journal</strong> : tes entrées (texte), tes humeurs, le lieu que tu attaches toi-même à une entrée, les photos et les mémos vocaux que tu ajoutes, tes quêtes, tes personnages et les liens entre eux.</li>
        <li><strong>Données techniques</strong> : un identifiant d'appareil servant à coordonner la synchronisation, des jetons d'authentification, et l'adresse IP de tes requêtes, traitée de façon transitoire par les journaux serveur et par les limitations de débit qui protègent l'API des abus.</li>
        <li><strong>Abonnement</strong> : si tu souscris à Nacre Plus, un identifiant applicatif anonyme, le produit souscrit et sa date d'expiration. Nous ne voyons jamais tes coordonnées bancaires : le paiement est encaissé par Apple ou Google.</li>
        <li><strong>Chapitres IA</strong> : si — et seulement si — tu actives cette fonction, le contenu du journal nécessaire à la génération est transmis à notre prestataire d'IA. Voir l'article 7.</li>
    </ul>
    <p>
        <strong>Ce que nous ne collectons pas :</strong> aucun identifiant publicitaire, aucun
        traceur tiers, aucune analyse comportementale. Les rappels d'écriture sont des notifications
        <strong>locales</strong>, programmées par l'application sur ton appareil : aucun jeton de
        notification push n'est créé et aucun serveur de notification n'est contacté. La collecte de
        rapports de plantage est prévue par le code mais <strong>n'est pas activée</strong> à ce jour ;
        si nous l'activons, cette page sera mise à jour avant.
    </p>

    <h2>3. Bases légales</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Traitement</th><th>Base légale (RGPD)</th></tr>
            </thead>
            <tbody>
                <tr><td>Compte, sauvegarde et synchronisation de ton journal</td><td>Exécution du contrat — art. 6.1.b</td></tr>
                <tr><td>Gestion de l'abonnement Nacre Plus</td><td>Exécution du contrat — art. 6.1.b</td></tr>
                <tr><td>Chapitres IA</td><td>Ton consentement — art. 6.1.a, révocable à tout moment</td></tr>
                <tr><td>Sécurité, prévention des abus, limitation de débit</td><td>Intérêt légitime — art. 6.1.f</td></tr>
                <tr><td>Obligations comptables et légales</td><td>Obligation légale — art. 6.1.c</td></tr>
            </tbody>
        </table>
    </div>

    <h2>4. Usage local sans compte</h2>
    <p>
        L'application fonctionne entièrement <strong>hors-ligne, sans compte</strong>. Dans ce cas, tes
        données restent sur ton appareil et ne nous sont jamais transmises. Un compte ne sert qu'à
        <strong>sauvegarder et synchroniser</strong> ton journal entre plusieurs appareils.
    </p>

    <h2>5. Où et comment c'est stocké</h2>
    <ul>
        <li><strong>Sur ton appareil</strong> : une base locale (SQLite), protégée par le bac à sable du système d'exploitation et un verrou biométrique optionnel.</li>
        <li><strong>Sur nos serveurs</strong> (si tu as un compte) : une base PostgreSQL hébergée par {{ $hosting['provider'] }} sur l'infrastructure {{ $hosting['infrastructure'] }}, région <strong>{{ $hosting['region'] }} ({{ $hosting['region_label'] }})</strong> — donc dans l'Union européenne.</li>
        <li><strong>Tes fichiers</strong> (photos, mémos vocaux) : sur un stockage objet {{ $hosting['object_storage'] }}, distinct de la base.</li>
        <li>Tous les échanges entre l'application et le serveur se font en HTTPS.</li>
    </ul>

    <h2>6. Chiffrement — transparence sur le modèle de menace</h2>
    <p>
        Les champs texte de ton contenu synchronisé (titres, entrées, descriptions de quêtes, noms et
        notes de personnages) sont <strong>chiffrés au repos côté serveur</strong>.
        Toutefois, ce chiffrement utilise une clé <strong>lisible par le serveur</strong> : il n'est
        <strong>pas</strong> de bout en bout (E2E). Cela signifie que, techniquement, nous pouvons accéder
        au contenu — c'est ce qui rend possibles la récupération de compte et les Chapitres IA.
        Le chiffrement de bout en bout reste un objectif pour une version ultérieure. Nous préférons
        être honnêtes sur ce point plutôt que de promettre une confidentialité que l'implémentation
        actuelle ne garantit pas.
    </p>

    <h2>7. Chapitres IA</h2>
    <p>
        Nacre peut relire ton journal et en écrire le récit — c'est la fonction « Chapitres ». Elle est
        <strong>désactivée par défaut</strong> et ne se déclenche qu'après une activation explicite de ta
        part (Réglages → IA). Tant que tu ne l'as pas activée, <strong>aucun contenu de ton journal
        n'est transmis à un prestataire d'IA</strong>.
    </p>
    <ul>
        <li><strong>Ce qui est transmis</strong> : les entrées de la période concernée par le chapitre demandé — leur texte, leur date et leur humeur — ainsi que les titres de quêtes et les noms de personnages qui y sont rattachés. Ni ton adresse e-mail, ni ton identité, ni tes fichiers (photos, mémos vocaux).</li>
        <li><strong>À qui</strong> : Anthropic PBC (États-Unis), via son API, en qualité de sous-traitant.</li>
        <li><strong>Ce qu'ils en font</strong> : Anthropic n'utilise pas les contenus transmis via son API pour entraîner ses modèles. Ils peuvent être conservés temporairement pour des besoins de sécurité, puis supprimés.</li>
        <li><strong>Comment revenir en arrière</strong> : désactive la fonction dans les réglages. La génération s'arrête immédiatement et les chapitres déjà écrits cessent d'être affichés dans l'application, y compris hors-ligne. Ils restent cependant stockés sur nos serveurs : pour les faire effacer, écris-nous à <a href="mailto:{{ $legal['contact_email'] }}">{{ $legal['contact_email'] }}</a> — et la suppression de ton compte les efface avec le reste de tes données.</li>
    </ul>
    <p>
        Un chapitre est un texte produit par une machine à partir de ce que tu as écrit. Il peut se
        tromper, mal interpréter ou inventer. Ne lui prête pas plus d'autorité qu'à un brouillon.
    </p>

    <h2>8. Ce que nous ne faisons pas</h2>
    <ul>
        <li>Aucune publicité.</li>
        <li>Aucune revente de tes données.</li>
        <li>Aucun pistage inter-applications, aucun profilage publicitaire.</li>
        <li>Aucune lecture de ton journal en dehors des cas décrits ici, ni décision automatisée produisant des effets juridiques à ton égard.</li>
    </ul>

    <h2>9. Conservation et suppression</h2>
    <ul>
        <li>Une entrée supprimée part d'abord à la corbeille, puis est <strong>effacée définitivement après 30 jours</strong>, y compris les fichiers associés sur le stockage objet.</li>
        <li>Les marqueurs techniques de suppression, qui servent à propager un effacement vers tes autres appareils, sont purgés au bout de 90 jours.</li>
        <li>Tu peux supprimer ton compte à tout moment depuis Réglages → Compte : tes données serveur et tes jetons d'accès sont supprimés immédiatement, et tes photos et mémos vocaux sont effacés du stockage dans la foulée par une tâche dédiée.</li>
        <li>Les Chapitres IA font exception : ils ne sont pas stockés sur ton appareil et ne passent pas par la corbeille. Ils sont conservés sur nos serveurs jusqu'à la suppression de ton compte, ou plus tôt si tu nous demandes de les effacer.</li>
        <li>Tes données locales restent sur ton appareil tant que tu n'as pas désinstallé l'application ou effacé le journal.</li>
    </ul>

    <h2>10. Tes droits</h2>
    <ul>
        <li><strong>Export</strong> : tu peux exporter tout ton journal (Markdown / TXT / JSON) à tout moment, gratuitement, sans nous demander quoi que ce soit.</li>
        <li>Accès, rectification, effacement, limitation, portabilité, opposition, et retrait de ton consentement pour les Chapitres IA — conformément au RGPD et à la loi Informatique et Libertés.</li>
        <li>Pour exercer ces droits : <a href="mailto:{{ $legal['contact_email'] }}">{{ $legal['contact_email'] }}</a>. Nous répondons sous un mois.</li>
        <li>Tu peux aussi introduire une réclamation auprès de la CNIL, 3 place de Fontenoy — TSA 80715, 75334 Paris Cedex 07 (<a href="https://www.cnil.fr">cnil.fr</a>).</li>
    </ul>

    <h2>11. Sous-traitants</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Prestataire</th><th>Rôle</th><th>Localisation</th></tr>
            </thead>
            <tbody>
                <tr><td>{{ $hosting['provider'] }} / {{ $hosting['infrastructure'] }}</td><td>Hébergement de l'API et de la base de données</td><td>{{ $hosting['region_label'] }} ({{ $hosting['region'] }})</td></tr>
                <tr><td>Cloudflare, Inc. (R2)</td><td>Stockage de tes photos et mémos vocaux</td><td>Société établie aux États-Unis</td></tr>
                <tr><td>Anthropic PBC</td><td>Génération des Chapitres IA — uniquement si tu actives la fonction</td><td>États-Unis</td></tr>
                <tr><td>RevenueCat, Inc.</td><td>Gestion des abonnements Nacre Plus</td><td>États-Unis</td></tr>
            </tbody>
        </table>
    </div>
    <p>
        Chacun agit en qualité de <strong>sous-traitant</strong>, sur instruction et dans le cadre de
        l'accord de traitement des données de son prestataire. Cette liste est tenue à jour ; toute
        addition sera publiée ici.
    </p>
    <p>
        <strong>Apple et Google occupent une position différente</strong> et ne figurent pas dans ce
        tableau : lorsque tu choisis « Se connecter avec Apple » ou « avec Google », et lorsque tu
        souscris un abonnement dont ils encaissent le paiement, ils traitent tes données pour leurs
        propres finalités, en qualité de <strong>responsables de traitement indépendants</strong> et
        non sur nos instructions. Ce qu'ils en font relève de leurs politiques de confidentialité, pas
        de la nôtre.
    </p>

    <h2>12. Transferts hors de l'Union européenne</h2>
    <p>
        Ta base de données PostgreSQL — donc le texte de ton journal — est hébergée en France. Deux
        nuances que nous préférons écrire noir sur blanc plutôt que de laisser croire que tout reste
        sur le territoire :
    </p>
    <ul>
        @if ($hosting['object_storage_eu_pinned'])
            <li>Tes photos et mémos vocaux sont stockés sur {{ $hosting['object_storage'] }}, avec une restriction de juridiction européenne : ces fichiers restent dans l'Union européenne.</li>
        @else
            <li>Tes photos et mémos vocaux sont stockés sur {{ $hosting['object_storage'] }}, dont l'emplacement de stockage <strong>n'est pas restreint à l'Union européenne</strong> : ces fichiers peuvent être conservés en dehors de l'UE.</li>
        @endif
        <li>Laravel Holdings, Inc. et Cloudflare, Inc. sont des sociétés américaines : leurs équipes techniques peuvent accéder à distance aux systèmes qu'elles exploitent, même lorsque les données sont physiquement en France.</li>
    </ul>
    <p>
        Ces transferts, comme ceux vers Anthropic et RevenueCat, sont encadrés par les clauses
        contractuelles types de la Commission européenne et, le cas échéant, par la certification
        <em>EU-US Data Privacy Framework</em> du prestataire concerné.
    </p>

    <h2>13. Mineurs</h2>
    <p>
        L'application n'est pas destinée aux personnes de moins de <strong>15 ans</strong> — l'âge du
        consentement numérique en France — ni, dans les pays où cet âge est plus élevé, aux personnes
        n'ayant pas atteint cet âge. Si nous apprenons qu'un compte a été créé en deçà, nous le
        supprimons.
    </p>

    <h2>14. Sécurité</h2>
    <p>
        Chiffrement des échanges en HTTPS, chiffrement au repos des champs texte, cloisonnement strict
        entre comptes vérifié par des tests automatisés, limitation de débit sur les points d'entrée
        sensibles, verrou biométrique optionnel sur l'appareil. Si tu penses avoir trouvé une faille,
        écris-nous à <a href="mailto:{{ $legal['contact_email'] }}">{{ $legal['contact_email'] }}</a> —
        nous répondons vite et nous ne poursuivons pas les signalements de bonne foi.
    </p>

    <h2>15. Modifications</h2>
    <p>
        En cas de changement, nous mettrons à jour la date en haut de cette page et, lorsque le
        changement est important, nous t'en informerons dans l'application.
    </p>
@else
    <h1>Privacy Policy</h1>
    <p class="updated">Last updated: {{ $legal['last_updated']['privacy']['en'] }}</p>

    <p>
        Nacre is a private journaling app. This policy explains what data we process, why, where it is
        stored, and your rights. Our starting principle: <strong>you own your data</strong> — no ads,
        no reselling, ever.
    </p>

    <h2>1. Data controller</h2>
    <p>
        {{ $publisher['name'] }}, sole trader (<em>entrepreneur individuel</em>) —
        SIREN {{ $publisher['siren'] }}, SIRET {{ $publisher['siret'] }}, {{ $publisher['country'] }}.
        Contact: <a href="mailto:{{ $legal['contact_email'] }}">{{ $legal['contact_email'] }}</a>
        (postal address provided on request at that address).
        See the <a href="{{ route('legal.notice', ['lang' => $lang]) }}">legal notice</a>.
    </p>

    <h2>2. Data we process</h2>
    <ul>
        <li><strong>Account (optional)</strong>: your email address. If you use “Sign in with Apple” or “with Google”, the identifier and email those providers share.</li>
        <li><strong>Journal content</strong>: your entries (text), moods, the location you choose to attach to an entry, the photos and voice notes you add, your quests, characters, and the links between them.</li>
        <li><strong>Technical data</strong>: a device identifier used to coordinate sync, authentication tokens, and the IP address of your requests, processed transiently by server logs and by the rate limits that protect the API from abuse.</li>
        <li><strong>Subscription</strong>: if you subscribe to Nacre Plus, an anonymous app identifier, the purchased product and its expiry date. We never see your payment details — Apple or Google collect the payment.</li>
        <li><strong>AI Chapters</strong>: if — and only if — you turn the feature on, the journal content needed to generate a chapter is sent to our AI processor. See section 7.</li>
    </ul>
    <p>
        <strong>What we do not collect:</strong> no advertising identifier, no third-party tracker, no
        behavioural analytics. Writing reminders are <strong>local</strong> notifications scheduled by
        the app on your device: no push token is created and no notification server is contacted.
        Crash reporting exists in the code but is <strong>not enabled</strong> today; if we enable it,
        this page will be updated first.
    </p>

    <h2>3. Legal bases</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Processing</th><th>Legal basis (GDPR)</th></tr>
            </thead>
            <tbody>
                <tr><td>Account, backup and sync of your journal</td><td>Performance of a contract — art. 6(1)(b)</td></tr>
                <tr><td>Managing your Nacre Plus subscription</td><td>Performance of a contract — art. 6(1)(b)</td></tr>
                <tr><td>AI Chapters</td><td>Your consent — art. 6(1)(a), withdrawable at any time</td></tr>
                <tr><td>Security, abuse prevention, rate limiting</td><td>Legitimate interest — art. 6(1)(f)</td></tr>
                <tr><td>Accounting and legal obligations</td><td>Legal obligation — art. 6(1)(c)</td></tr>
            </tbody>
        </table>
    </div>

    <h2>4. Local use without an account</h2>
    <p>
        The app works fully <strong>offline, with no account</strong>. In that case your data stays on your
        device and is never sent to us. An account only exists to <strong>back up and sync</strong> your
        journal across multiple devices.
    </p>

    <h2>5. Where and how it is stored</h2>
    <ul>
        <li><strong>On your device</strong>: a local database (SQLite), protected by the operating-system sandbox and an optional biometric lock.</li>
        <li><strong>On our servers</strong> (if you have an account): a PostgreSQL database hosted by {{ $hosting['provider'] }} on {{ $hosting['infrastructure'] }} infrastructure, in the <strong>{{ $hosting['region'] }} ({{ $hosting['region_label'] }})</strong> region — inside the European Union.</li>
        <li><strong>Your files</strong> (photos, voice notes): on {{ $hosting['object_storage'] }} object storage, separate from the database.</li>
        <li>All traffic between the app and the server uses HTTPS.</li>
    </ul>

    <h2>6. Encryption — honest threat model</h2>
    <p>
        The text fields of your synced content (titles, entries, quest descriptions, character names and
        notes) are <strong>encrypted at rest on the server</strong>. However, that encryption uses a
        <strong>server-readable key</strong>: it is <strong>not</strong> end-to-end (E2E). This means we
        can technically access content — which is what makes account recovery and AI Chapters possible.
        End-to-end encryption remains a goal for a later version. We prefer to be honest about this
        rather than promise privacy the current implementation does not guarantee.
    </p>

    <h2>7. AI Chapters</h2>
    <p>
        Nacre can read back your journal and write its story — the “Chapters” feature. It is
        <strong>off by default</strong> and only runs after you explicitly turn it on
        (Settings → AI). Until you do, <strong>no journal content is sent to any AI provider</strong>.
    </p>
    <ul>
        <li><strong>What is sent</strong>: the entries covering the period of the requested chapter — their text, date and mood — along with the titles of the quests and the names of the characters linked to them. Not your email address, not your identity, not your files (photos, voice notes).</li>
        <li><strong>To whom</strong>: Anthropic PBC (United States), through its API, acting as a processor.</li>
        <li><strong>What they do with it</strong>: Anthropic does not use content submitted through its API to train its models. It may be retained briefly for safety purposes, then deleted.</li>
        <li><strong>How to undo it</strong>: turn the feature off in settings. Generation stops immediately and chapters already written are no longer shown in the app, including offline. They do remain stored on our servers: to have them erased, write to <a href="mailto:{{ $legal['contact_email'] }}">{{ $legal['contact_email'] }}</a> — and deleting your account erases them along with the rest of your data.</li>
    </ul>
    <p>
        A chapter is machine-written text derived from what you wrote. It can be wrong, misread you, or
        invent things. Give it no more authority than a draft.
    </p>

    <h2>8. What we never do</h2>
    <ul>
        <li>No advertising.</li>
        <li>No reselling of your data.</li>
        <li>No cross-app tracking, no ad profiling.</li>
        <li>No reading of your journal outside the cases described here, and no automated decisions producing legal effects concerning you.</li>
    </ul>

    <h2>9. Retention and deletion</h2>
    <ul>
        <li>A deleted entry first goes to trash, then is <strong>permanently erased after 30 days</strong>, including its files on object storage.</li>
        <li>The technical deletion markers used to propagate an erasure to your other devices are purged after 90 days.</li>
        <li>You can delete your account at any time in Settings → Account: your server-side data and access tokens are removed immediately, and your photos and voice notes are erased from storage right after by a dedicated job.</li>
        <li>AI Chapters are the exception: they are not stored on your device and do not pass through the trash. They are kept on our servers until you delete your account, or earlier if you ask us to erase them.</li>
        <li>Your local data stays on your device until you uninstall the app or clear the journal.</li>
    </ul>

    <h2>10. Your rights</h2>
    <ul>
        <li><strong>Export</strong>: you can export your entire journal (Markdown / TXT / JSON) at any time, for free, without asking us for anything.</li>
        <li>Access, rectification, erasure, restriction, portability, objection, and withdrawal of your consent for AI Chapters — under the GDPR and the French Data Protection Act.</li>
        <li>To exercise these rights: <a href="mailto:{{ $legal['contact_email'] }}">{{ $legal['contact_email'] }}</a>. We answer within one month.</li>
        <li>You may also lodge a complaint with the French CNIL, 3 place de Fontenoy — TSA 80715, 75334 Paris Cedex 07 (<a href="https://www.cnil.fr">cnil.fr</a>), or with your own national supervisory authority.</li>
    </ul>

    <h2>11. Sub-processors</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Provider</th><th>Role</th><th>Location</th></tr>
            </thead>
            <tbody>
                <tr><td>{{ $hosting['provider'] }} / {{ $hosting['infrastructure'] }}</td><td>Hosting of the API and database</td><td>{{ $hosting['region_label'] }} ({{ $hosting['region'] }})</td></tr>
                <tr><td>Cloudflare, Inc. (R2)</td><td>Storage of your photos and voice notes</td><td>Company established in the United States</td></tr>
                <tr><td>Anthropic PBC</td><td>AI Chapter generation — only if you enable the feature</td><td>United States</td></tr>
                <tr><td>RevenueCat, Inc.</td><td>Nacre Plus subscription management</td><td>United States</td></tr>
            </tbody>
        </table>
    </div>
    <p>
        Each acts as a <strong>processor</strong>, on instruction and under its data processing
        agreement. This list is kept current; any addition will be published here.
    </p>
    <p>
        <strong>Apple and Google sit in a different position</strong> and are deliberately not in that
        table: when you choose “Sign in with Apple” or “with Google”, and when you subscribe through
        their billing, they process your data for their own purposes, as
        <strong>independent controllers</strong> rather than on our instructions. What they do with it
        is governed by their privacy policies, not ours.
    </p>

    <h2>12. Transfers outside the European Union</h2>
    <p>
        Your PostgreSQL database — so the text of your journal — is hosted in France. Two caveats we
        would rather state plainly than let you assume everything stays on French soil:
    </p>
    <ul>
        @if ($hosting['object_storage_eu_pinned'])
            <li>Your photos and voice notes are stored on {{ $hosting['object_storage'] }} under an EU jurisdiction restriction: those files stay inside the European Union.</li>
        @else
            <li>Your photos and voice notes are stored on {{ $hosting['object_storage'] }}, whose storage location is <strong>not restricted to the European Union</strong>: those files may be held outside the EU.</li>
        @endif
        <li>Laravel Holdings, Inc. and Cloudflare, Inc. are US companies: their engineering teams can access the systems they operate remotely, even when the data physically sits in France.</li>
    </ul>
    <p>
        Those transfers, like the ones to Anthropic and RevenueCat, rely on the European Commission's
        Standard Contractual Clauses and, where applicable, on the provider's
        <em>EU-US Data Privacy Framework</em> certification.
    </p>

    <h2>13. Minors</h2>
    <p>
        The app is not directed to people under <strong>15</strong> — the digital consent age in France
        — nor, in countries where that age is higher, to people below it. If we learn that an account
        was created below that age, we delete it.
    </p>

    <h2>14. Security</h2>
    <p>
        HTTPS in transit, encryption at rest for text fields, strict account isolation verified by
        automated tests, rate limiting on sensitive endpoints, an optional biometric lock on device.
        If you think you have found a vulnerability, write to
        <a href="mailto:{{ $legal['contact_email'] }}">{{ $legal['contact_email'] }}</a> — we answer
        quickly and we do not pursue good-faith reports.
    </p>

    <h2>15. Changes</h2>
    <p>
        If anything changes, we will update the date at the top of this page and, where the change
        matters, notify you in the app.
    </p>
@endif
@endsection
