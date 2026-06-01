@extends('legal.layout')

@section('title', $lang === 'fr' ? 'Politique de confidentialité' : 'Privacy Policy')

@section('content')
@if ($lang === 'fr')
    <h1>Politique de confidentialité</h1>
    <p class="updated">Dernière mise à jour : [DATE À DÉFINIR]</p>

    <div class="notice">
        Brouillon à faire valider juridiquement avant publication. Les éléments entre crochets
        ([…]) doivent être complétés (entité légale, adresse, juridiction, sous-traitants, date).
    </div>

    <p>
        Quest est un journal intime privé. Cette politique explique quelles données nous traitons,
        pourquoi, où elles sont stockées et quels sont tes droits. Notre principe de départ :
        <strong>tu es propriétaire de tes données</strong>, sans publicité ni revente, jamais.
    </p>

    <h2>1. Responsable du traitement</h2>
    <p>
        [ENTITÉ LÉGALE], [ADRESSE]. Contact : <a href="mailto:ns@theresidency.io">ns@theresidency.io</a>.
    </p>

    <h2>2. Données que nous traitons</h2>
    <ul>
        <li><strong>Compte (optionnel)</strong> : ton adresse e-mail. Si tu utilises « Se connecter avec Apple » ou « avec Google », l'identifiant et l'e-mail transmis par ces fournisseurs.</li>
        <li><strong>Contenu du journal</strong> : tes entrées (texte), humeurs, lieu optionnel que tu attaches, photos et audio que tu ajoutes, tes quêtes, tes personnages et les liens entre eux.</li>
        <li><strong>Données techniques</strong> : un identifiant d'appareil servant à coordonner la synchronisation, et des jetons d'authentification.</li>
        <li><strong>Diagnostics</strong> : rapports de plantage et d'erreur pseudonymisés (si activés), pour corriger les bugs.</li>
    </ul>

    <h2>3. Usage local sans compte</h2>
    <p>
        L'application fonctionne entièrement <strong>hors-ligne, sans compte</strong>. Dans ce cas, tes
        données restent sur ton appareil et ne nous sont jamais transmises. Un compte ne sert qu'à
        <strong>synchroniser</strong> ton journal entre plusieurs appareils.
    </p>

    <h2>4. Où et comment c'est stocké</h2>
    <ul>
        <li>Sur ton appareil : base locale (SQLite), protégée par le bac à sable du système d'exploitation et un verrou biométrique optionnel.</li>
        <li>Sur nos serveurs (si tu as un compte) : base PostgreSQL hébergée en [RÉGION], fichiers (photos, audio) sur un stockage objet ([PRESTATAIRE, ex. AWS S3]). Les échanges se font en HTTPS.</li>
    </ul>

    <h2>5. Chiffrement — transparence sur le modèle de menace</h2>
    <p>
        Les champs texte de ton contenu synchronisé sont <strong>chiffrés au repos côté serveur</strong>.
        Toutefois, ce chiffrement utilise une clé <strong>lisible par le serveur</strong> : il n'est
        <strong>pas</strong> de bout en bout (E2E). Cela signifie que, techniquement, nous pouvons accéder
        au contenu pour fournir certaines fonctions (récupération de compte, futures fonctions d'IA optionnelles).
        Le chiffrement de bout en bout est un objectif pour une version ultérieure (V1). Nous préférons
        être honnêtes sur ce point plutôt que de promettre une confidentialité que l'implémentation
        actuelle ne garantit pas.
    </p>

    <h2>6. Ce que nous ne faisons pas</h2>
    <ul>
        <li>Aucune publicité.</li>
        <li>Aucune revente de tes données.</li>
        <li>Aucun pistage inter-applications, aucun profilage publicitaire.</li>
    </ul>

    <h2>7. Conservation et suppression</h2>
    <p>
        Une entrée supprimée part d'abord à la corbeille puis est <strong>effacée définitivement après 30 jours</strong>.
        Tu peux supprimer ton compte à tout moment depuis Réglages → Compte ; cela efface tes données côté serveur.
    </p>

    <h2>8. Tes droits</h2>
    <ul>
        <li><strong>Export</strong> : tu peux exporter tout ton journal (Markdown / TXT / JSON) à tout moment, gratuitement.</li>
        <li>Accès, rectification, effacement, portabilité et opposition conformément au RGPD ([et lois applicables]).</li>
        <li>Pour exercer ces droits : <a href="mailto:ns@theresidency.io">ns@theresidency.io</a>. Tu peux aussi saisir l'autorité de contrôle compétente ([ex. CNIL]).</li>
    </ul>

    <h2>9. Sous-traitants</h2>
    <ul>
        <li>Hébergement et stockage objet : [PRESTATAIRE].</li>
        <li>Diagnostics de plantage : [ex. Sentry], le cas échéant.</li>
        <li>Connexion tierce : Apple, Google, lorsque tu choisis ces méthodes.</li>
    </ul>

    <h2>10. Mineurs</h2>
    <p>L'application n'est pas destinée aux personnes de moins de [16] ans.</p>

    <h2>11. Modifications</h2>
    <p>En cas de changement, nous mettrons à jour la date ci-dessus et, si nécessaire, t'en informerons dans l'application.</p>
@else
    <h1>Privacy Policy</h1>
    <p class="updated">Last updated: [DATE TO SET]</p>

    <div class="notice">
        Draft — pending legal review before publication. Bracketed items ([…]) must be filled in
        (legal entity, address, jurisdiction, sub-processors, date).
    </div>

    <p>
        Quest is a private journaling app. This policy explains what data we process, why, where it is
        stored, and your rights. Our starting principle: <strong>you own your data</strong> — no ads,
        no reselling, ever.
    </p>

    <h2>1. Data controller</h2>
    <p>[LEGAL ENTITY], [ADDRESS]. Contact: <a href="mailto:ns@theresidency.io">ns@theresidency.io</a>.</p>

    <h2>2. Data we process</h2>
    <ul>
        <li><strong>Account (optional)</strong>: your email address. If you use “Sign in with Apple” or “with Google”, the identifier and email those providers share.</li>
        <li><strong>Journal content</strong>: your entries (text), moods, optional location you attach, photos and audio you add, your quests, characters, and the links between them.</li>
        <li><strong>Technical data</strong>: a device identifier used to coordinate sync, and authentication tokens.</li>
        <li><strong>Diagnostics</strong>: pseudonymous crash and error reports (if enabled), to fix bugs.</li>
    </ul>

    <h2>3. Local use without an account</h2>
    <p>
        The app works fully <strong>offline, with no account</strong>. In that case your data stays on your
        device and is never sent to us. An account only exists to <strong>sync</strong> your journal across
        multiple devices.
    </p>

    <h2>4. Where and how it is stored</h2>
    <ul>
        <li>On your device: a local database (SQLite), protected by the operating-system sandbox and an optional biometric lock.</li>
        <li>On our servers (if you have an account): a PostgreSQL database hosted in [REGION]; files (photos, audio) on object storage ([PROVIDER, e.g. AWS S3]). All traffic uses HTTPS.</li>
    </ul>

    <h2>5. Encryption — honest threat model</h2>
    <p>
        The text fields of your synced content are <strong>encrypted at rest on the server</strong>.
        However, that encryption uses a <strong>server-readable key</strong>: it is <strong>not</strong>
        end-to-end (E2E). This means we can technically access content to provide features such as account
        recovery and future optional AI features. End-to-end encryption is a goal for a later version (V1).
        We prefer to be honest about this rather than promise privacy the current implementation does not
        guarantee.
    </p>

    <h2>6. What we never do</h2>
    <ul>
        <li>No advertising.</li>
        <li>No reselling of your data.</li>
        <li>No cross-app tracking, no ad profiling.</li>
    </ul>

    <h2>7. Retention and deletion</h2>
    <p>
        A deleted entry first goes to trash, then is <strong>permanently erased after 30 days</strong>.
        You can delete your account at any time in Settings → Account; this removes your server-side data.
    </p>

    <h2>8. Your rights</h2>
    <ul>
        <li><strong>Export</strong>: you can export your entire journal (Markdown / TXT / JSON) at any time, for free.</li>
        <li>Access, rectification, erasure, portability and objection under GDPR ([and applicable laws]).</li>
        <li>To exercise these rights: <a href="mailto:ns@theresidency.io">ns@theresidency.io</a>. You may also contact your competent supervisory authority.</li>
    </ul>

    <h2>9. Sub-processors</h2>
    <ul>
        <li>Hosting and object storage: [PROVIDER].</li>
        <li>Crash diagnostics: [e.g. Sentry], if enabled.</li>
        <li>Third-party sign-in: Apple, Google, when you choose those methods.</li>
    </ul>

    <h2>10. Minors</h2>
    <p>The app is not directed to people under [16].</p>

    <h2>11. Changes</h2>
    <p>If anything changes, we will update the date above and, where appropriate, notify you in the app.</p>
@endif
@endsection
