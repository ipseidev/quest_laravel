@extends('legal.layout')

@section('title', $lang === 'fr' ? 'Aide & support' : 'Help & support')

@section('content')
@if ($lang === 'fr')
    <h1>Aide &amp; support</h1>
    <p class="updated">Nacre — un journal où ta vie devient une histoire.</p>

    <p>
        Une question, un bug, une suggestion ? Écris-nous — on lit tout et on répond
        généralement sous 2 jours ouvrés.
    </p>
    <p>
        <strong>Contact :</strong>
        <a href="mailto:{{ $legal['contact_email'] }}?subject=Nacre%20%E2%80%94%20Support">{{ $legal['contact_email'] }}</a>
    </p>

    <h2>Questions fréquentes</h2>

    <h3>Mes données sont-elles privées ?</h3>
    <p>
        Nacre fonctionne hors-ligne sur ton appareil ; un compte sert uniquement à
        sauvegarder et synchroniser entre tes appareils. Aucune publicité, aucune revente, aucun
        pistage. Voir la <a href="{{ route('legal.privacy', ['lang' => $lang]) }}">politique de confidentialité</a>.
    </p>

    <h3>Que contient Nacre Plus, et comment résilier ?</h3>
    <p>
        Nacre Plus ajoute la synchronisation continue de ton journal entre tes appareils, des
        Chapitres réguliers, la sauvegarde illimitée de tes photos et mémos vocaux, et les
        thèmes réservés. C'est un abonnement mensuel ou annuel, encaissé par Apple ou Google.
        La résiliation se fait depuis les réglages de ton compte App Store ou Google Play —
        pas depuis l'application. Détails dans les
        <a href="{{ route('legal.terms', ['lang' => $lang]) }}">conditions d'utilisation</a>.
    </p>

    <h3>Mon journal part-il à une IA ?</h3>
    <p>
        Seulement si tu l'actives toi-même (Réglages → IA), et uniquement pour écrire les
        Chapitres. Tant que c'est désactivé, rien ne quitte ton appareil ni nos serveurs vers
        un prestataire d'IA. Tu peux couper à tout moment.
    </p>

    <h3>Comment exporter mes entrées ?</h3>
    <p>
        Depuis les Réglages de l'app, tu peux exporter tout ton journal (Markdown, TXT
        ou JSON), gratuitement et à tout moment.
    </p>

    <h3>Comment supprimer mon compte ?</h3>
    <p>
        Réglages → Compte → Supprimer le compte. Cela efface tes données côté serveur.
        Les entrées supprimées partent d'abord à la corbeille (30 jours) avant suppression définitive.
    </p>

    <h3>La connexion Google / Apple ne fonctionne pas ?</h3>
    <p>
        Vérifie que tu es connecté à Internet et que l'app est à jour. Si le problème
        persiste, écris-nous avec le modèle de ton appareil et ta version d'iOS.
    </p>
@else
    <h1>Help &amp; support</h1>
    <p class="updated">Nacre — a journal where your life becomes a story.</p>

    <p>
        A question, a bug, a suggestion? Email us — we read everything and usually
        reply within 2 business days.
    </p>
    <p>
        <strong>Contact:</strong>
        <a href="mailto:{{ $legal['contact_email'] }}?subject=Nacre%20%E2%80%94%20Support">{{ $legal['contact_email'] }}</a>
    </p>

    <h2>Frequently asked questions</h2>

    <h3>Is my data private?</h3>
    <p>
        Nacre works offline on your device; an account only backs up and syncs across your
        devices. No ads, no reselling, no tracking. See the
        <a href="{{ route('legal.privacy', ['lang' => $lang]) }}">privacy policy</a>.
    </p>

    <h3>What is in Nacre Plus, and how do I cancel?</h3>
    <p>
        Nacre Plus adds continuous syncing of your journal across your devices, recurring
        Chapters, unlimited backup of your photos and voice notes, and subscriber-only themes.
        It is a monthly or yearly subscription, charged by Apple or Google. Cancel from your App Store
        or Google Play account settings — not from the app. Details in the
        <a href="{{ route('legal.terms', ['lang' => $lang]) }}">terms of service</a>.
    </p>

    <h3>Does my journal go to an AI?</h3>
    <p>
        Only if you turn it on yourself (Settings → AI), and only to write Chapters. While it
        is off, nothing leaves your device or our servers for an AI provider. You can switch it
        off at any time.
    </p>

    <h3>How do I export my entries?</h3>
    <p>
        From the app's Settings you can export your whole journal (Markdown, TXT or JSON),
        free and at any time.
    </p>

    <h3>How do I delete my account?</h3>
    <p>
        Settings → Account → Delete account. This removes your server-side data. Deleted
        entries first go to trash (30 days) before permanent deletion.
    </p>

    <h3>Google / Apple sign-in isn't working?</h3>
    <p>
        Make sure you're online and the app is up to date. If it persists, email us with
        your device model and iOS version.
    </p>
@endif
@endsection
