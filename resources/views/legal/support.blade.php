@extends('legal.layout')

@section('title', $lang === 'fr' ? 'Aide & support' : 'Help & support')

@section('content')
@if ($lang === 'fr')
    <h1>Aide &amp; support</h1>
    <p class="updated">Quest — un journal où ta vie devient une histoire.</p>

    <p>
        Une question, un bug, une suggestion ? Écris-nous — on lit tout et on répond
        généralement sous 2 jours ouvrés.
    </p>
    <p>
        <strong>Contact :</strong>
        <a href="mailto:contact@affiniteam.io?subject=Quest%20%E2%80%94%20Support">contact@affiniteam.io</a>
    </p>

    <h2>Questions fréquentes</h2>

    <h3>Mes données sont-elles privées ?</h3>
    <p>
        Quest fonctionne hors-ligne sur ton appareil ; un compte sert uniquement à
        synchroniser entre tes appareils. Aucune publicité, aucune revente, aucun
        pistage. Voir la <a href="{{ route('legal.privacy', ['lang' => $lang]) }}">politique de confidentialité</a>.
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
    <p class="updated">Quest — a journal where your life becomes a story.</p>

    <p>
        A question, a bug, a suggestion? Email us — we read everything and usually
        reply within 2 business days.
    </p>
    <p>
        <strong>Contact:</strong>
        <a href="mailto:contact@affiniteam.io?subject=Quest%20%E2%80%94%20Support">contact@affiniteam.io</a>
    </p>

    <h2>Frequently asked questions</h2>

    <h3>Is my data private?</h3>
    <p>
        Quest works offline on your device; an account only syncs across your devices.
        No ads, no reselling, no tracking. See the
        <a href="{{ route('legal.privacy', ['lang' => $lang]) }}">privacy policy</a>.
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
