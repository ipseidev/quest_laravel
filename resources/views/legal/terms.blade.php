@extends('legal.layout')

@section('title', $lang === 'fr' ? 'Conditions d\'utilisation' : 'Terms of Service')

@section('content')
@php
    $publisher = $legal['publisher'];
@endphp
@if ($lang === 'fr')
    <h1>Conditions d'utilisation</h1>
    <p class="updated">Dernière mise à jour : {{ $legal['last_updated']['terms']['fr'] }}</p>

    <h2>1. Éditeur et acceptation</h2>
    <p>
        Nacre est édité par {{ $publisher['name'] }}, entrepreneur individuel — SIREN
        {{ $publisher['siren'] }}, {{ $publisher['country'] }}
        (<a href="{{ route('legal.notice', ['lang' => $lang]) }}">mentions légales</a>).
        En utilisant Nacre, tu acceptes ces conditions. Si tu n'es pas d'accord, n'utilise pas
        l'application.
    </p>
    <p>
        L'application est réservée aux personnes de <strong>15 ans</strong> ou plus, ou à l'âge du
        consentement numérique applicable dans ton pays s'il est supérieur.
    </p>

    <h2>2. Le service</h2>
    <p>
        Nacre est une application de journal intime. Elle fonctionne <strong>hors-ligne sur ton
        appareil</strong>, sans compte. Un compte optionnel permet de sauvegarder ton journal et,
        avec Nacre Plus, de le retrouver sur tes autres appareils.
    </p>

    <h2>3. Ton compte</h2>
    <p>
        Tu es responsable de la confidentialité de tes identifiants et de l'activité sur ton compte.
        Fournis une adresse e-mail valide afin de pouvoir gérer ton accès. Un compte est personnel.
    </p>

    <h2>4. Ton contenu</h2>
    <p>
        Tu restes <strong>propriétaire de tout le contenu</strong> que tu crées. Tu nous accordes
        uniquement la licence limitée, non exclusive et révocable nécessaire pour héberger, sauvegarder
        et synchroniser ce contenu afin de te fournir le service — et, si tu actives les Chapitres, pour
        le transmettre à notre prestataire d'IA le temps de la génération. Nous ne revendiquons aucune
        propriété et ne l'utilisons ni à des fins publicitaires, ni pour entraîner un modèle.
    </p>

    <h2>5. Usage acceptable</h2>
    <ul>
        <li>Ne pas stocker ou diffuser de contenu illégal via le service.</li>
        <li>Ne pas tenter de perturber, surcharger ou contourner les limites de l'API.</li>
        <li>Ne pas accéder aux données d'autres utilisateurs.</li>
        <li>Ne pas revendre, redistribuer ou automatiser l'accès au service sans autorisation.</li>
    </ul>

    <h2>6. Version gratuite et Nacre Plus</h2>
    <p>La version gratuite comprend :</p>
    <ul>
        <li>le journal complet, hors-ligne, sans limite d'entrées ;</li>
        <li>l'export intégral de ton journal (Markdown, TXT, JSON), à tout moment ;</li>
        <li>la sauvegarde de ton journal vers ton compte, avec jusqu'à <strong>500 Mo</strong> de photos et de mémos vocaux ;</li>
        <li>un Chapitre écrit par l'IA, offert une fois, si tu actives cette fonction.</li>
    </ul>
    <p><strong>Nacre Plus</strong>, l'abonnement payant, ajoute :</p>
    <ul>
        <li>la synchronisation continue de ton journal entre tes appareils — <em>la version gratuite sauvegarde, et restaure ton journal quand tu te connectes sur un appareil, mais ne se synchronise pas en continu</em> ;</li>
        <li>des Chapitres récurrents, générés automatiquement ;</li>
        <li>la sauvegarde de tes photos et mémos vocaux sans limite de volume ;</li>
        <li>les thèmes réservés aux abonnés.</li>
    </ul>
    <p>
        Le contenu de chaque offre peut évoluer ; une réduction significative des fonctions incluses
        dans un abonnement en cours te sera notifiée dans l'application et te permettra de résilier.
    </p>

    <h2>7. Abonnement, reconduction et résiliation</h2>
    <ul>
        <li><strong>Vendeur</strong> : les abonnements sont vendus et encaissés par <strong>Apple</strong> (App Store) ou <strong>Google</strong> (Google Play), agissant comme vendeurs. Leurs conditions s'appliquent à la transaction et nous ne voyons jamais tes coordonnées bancaires.</li>
        <li><strong>Durées et prix</strong> : mensuel ou annuel. Le prix applicable, taxes comprises, t'est indiqué dans l'application avant l'achat et confirmé par la boutique.</li>
        <li><strong>Reconduction automatique</strong> : l'abonnement se renouvelle automatiquement pour la même durée, sauf résiliation au moins <strong>24 heures avant la fin de la période en cours</strong>. Le renouvellement est prélevé par la boutique dans les 24 heures précédant l'échéance.</li>
        <li><strong>Résiliation</strong> : à tout moment, depuis les réglages de ton compte App Store ou Google Play. La résiliation prend effet à la fin de la période déjà payée ; tu conserves l'accès jusque-là. Supprimer l'application ne résilie pas l'abonnement.</li>
        <li><strong>Remboursements</strong> : ils relèvent d'Apple ou de Google selon la boutique utilisée. Écris-nous si tu penses qu'une erreur s'est produite : nous t'aiderons dans la démarche.</li>
        <li><strong>Fin de l'abonnement</strong> : ton journal et tes entrées restent intacts. Tu conserves l'accès local, l'export et la sauvegarde dans les limites de la version gratuite.</li>
    </ul>

    <h2>8. Droit de rétractation</h2>
    <p>
        <strong>Tu disposes de 14 jours pour te rétracter</strong> à compter de la souscription de
        Nacre Plus. L'abonnement est un service numérique fourni de façon continue : il ne relève donc
        pas de l'exception applicable aux contenus numériques, et le droit de rétractation reste ouvert
        tant que le service n'a pas été <em>intégralement exécuté</em> (article L.221-28 1° du code de
        la consommation) — ce qui n'est jamais le cas d'un abonnement en cours.
    </p>
    <p>
        Si tu as demandé que l'abonnement démarre immédiatement, tu peux toujours te rétracter dans ce
        délai ; tu restes seulement redevable du montant correspondant à la période déjà fournie, au
        prorata (article L.221-25). Une simple demande par e-mail suffit, sans avoir à te justifier.
    </p>
    <p>
        En pratique, l'abonnement étant encaissé par Apple ou Google, le remboursement passe par leur
        canal : réglages du compte App Store → Historique des achats, ou Google Play → Abonnements.
        Écris-nous à <a href="mailto:{{ $legal['contact_email'] }}">{{ $legal['contact_email'] }}</a>
        en cas de refus ou de difficulté — nous appuyons la demande auprès de la boutique.
    </p>

    <h2>9. Chapitres IA</h2>
    <p>
        La fonction « Chapitres » est <strong>désactivée par défaut</strong> et ne s'active qu'avec ton
        accord explicite. Une fois activée, le contenu de journal nécessaire est transmis à notre
        prestataire d'IA pour écrire le texte (voir la
        <a href="{{ route('legal.privacy', ['lang' => $lang]) }}">politique de confidentialité</a>).
    </p>
    <p>
        Un Chapitre est un texte produit automatiquement. Il peut contenir des erreurs, des
        approximations ou des inventions. Il ne constitue <strong>ni un avis médical, ni un avis
        psychologique, ni un conseil juridique ou financier</strong>, et ne remplace pas l'accompagnement
        d'un professionnel. Nous ne garantissons ni l'exactitude, ni la disponibilité continue, ni le
        délai de génération.
    </p>

    <h2>10. Disponibilité et garanties</h2>
    <p>
        Nous faisons notre possible pour que le service reste disponible, sans garantir une absence
        totale d'interruption ou d'erreur — une maintenance, une panne d'un prestataire ou un incident
        peuvent survenir. Sauvegarde régulièrement ton journal via la fonction d'export : c'est le
        moyen le plus sûr d'en garder une copie qui ne dépend de personne.
    </p>
    <p>
        Ces conditions ne portent atteinte ni à la garantie légale de conformité des contenus et
        services numériques (articles L.224-25-1 et suivants du code de la consommation), ni à la
        garantie contre les vices cachés, dont tu bénéficies de plein droit.
    </p>

    <h2>11. Limitation de responsabilité</h2>
    <p>
        Dans les limites permises par la loi, {{ $publisher['name'] }} n'est pas responsable des
        dommages indirects. <strong>Rien dans ces conditions n'exclut ni ne limite la responsabilité
        en cas de dol, de faute lourde, de dommage corporel, ni les droits que la loi accorde
        impérativement aux consommateurs</strong> — en particulier notre responsabilité en cas de
        défaut de conformité du service, y compris une perte de tes données qui nous serait imputable.
        Le conseil d'exporter régulièrement ton journal reste un conseil : il ne réduit en rien cette
        responsabilité.
    </p>

    <h2>12. Suspension et résiliation</h2>
    <p>
        Tu peux supprimer ton compte et tes données serveur à tout moment depuis Réglages → Compte.
        Nous pouvons suspendre ou fermer un accès en cas de violation caractérisée de ces conditions ou
        d'usage manifestement abusif du service ; sauf urgence ou obligation légale, nous t'en
        informons d'abord et te laissons exporter ton journal.
    </p>

    <h2>13. Modification des conditions</h2>
    <p>
        Nous pouvons mettre à jour ces conditions ; la date en haut de page reflète la dernière
        version. En cas de changement important, nous t'en informons dans l'application. Si tu es
        abonné et que le changement te désavantage, tu peux résilier sans frais.
    </p>

    <h2>14. Réclamations et litiges</h2>
    <p>
        Écris-nous d'abord à
        <a href="mailto:{{ $legal['contact_email'] }}">{{ $legal['contact_email'] }}</a> : nous
        répondons à toute réclamation sous 14 jours et la plupart des problèmes se règlent là.
    </p>
    <p>
        Ces conditions sont régies par le <strong>droit français</strong>. Si tu es consommateur et
        résides dans un autre pays de l'Union européenne, tu conserves le bénéfice des dispositions
        impératives de ton pays de résidence et la possibilité de saisir les tribunaux de ton domicile.
    </p>

    <h2>15. Contact</h2>
    <p><a href="mailto:{{ $legal['contact_email'] }}">{{ $legal['contact_email'] }}</a></p>
@else
    <h1>Terms of Service</h1>
    <p class="updated">Last updated: {{ $legal['last_updated']['terms']['en'] }}</p>

    <h2>1. Publisher and acceptance</h2>
    <p>
        Nacre is published by {{ $publisher['name'] }}, sole trader (<em>entrepreneur individuel</em>)
        — SIREN {{ $publisher['siren'] }}, {{ $publisher['country'] }}
        (<a href="{{ route('legal.notice', ['lang' => $lang]) }}">legal notice</a>).
        By using Nacre, you agree to these terms. If you do not agree, do not use the app.
    </p>
    <p>
        The app is for people aged <strong>15</strong> or older, or the digital consent age applicable
        in your country if it is higher.
    </p>

    <h2>2. The service</h2>
    <p>
        Nacre is a journaling app. It works <strong>offline on your device</strong>, with no account.
        An optional account backs up your journal and, with Nacre Plus, brings it back on your other
        devices.
    </p>

    <h2>3. Your account</h2>
    <p>
        You are responsible for keeping your credentials confidential and for activity on your account.
        Provide a valid email address so you can manage your access. An account is personal.
    </p>

    <h2>4. Your content</h2>
    <p>
        You retain <strong>ownership of all content</strong> you create. You grant us only the limited,
        non-exclusive, revocable licence needed to host, back up and sync that content in order to
        provide the service — and, if you enable Chapters, to pass it to our AI provider for the time
        it takes to generate one. We claim no ownership and use it neither for advertising nor to train
        any model.
    </p>

    <h2>5. Acceptable use</h2>
    <ul>
        <li>Do not store or distribute illegal content through the service.</li>
        <li>Do not attempt to disrupt, overload, or circumvent API limits.</li>
        <li>Do not access other users' data.</li>
        <li>Do not resell, redistribute or automate access to the service without permission.</li>
    </ul>

    <h2>6. Free plan and Nacre Plus</h2>
    <p>The free plan includes:</p>
    <ul>
        <li>the full journal, offline, with no limit on entries;</li>
        <li>a full export of your journal (Markdown, TXT, JSON), at any time;</li>
        <li>backup of your journal to your account, with up to <strong>500 MB</strong> of photos and voice notes;</li>
        <li>one AI-written Chapter, free once, if you enable the feature.</li>
    </ul>
    <p><strong>Nacre Plus</strong>, the paid subscription, adds:</p>
    <ul>
        <li>continuous syncing of your journal across your devices — <em>the free plan backs up, and restores your journal when you sign in on a device, but does not sync continuously</em>;</li>
        <li>recurring Chapters, generated automatically;</li>
        <li>unlimited backup of your photos and voice notes;</li>
        <li>subscriber-only themes.</li>
    </ul>
    <p>
        What each plan includes may change; a significant reduction of the features included in a
        running subscription will be announced in the app and will let you cancel.
    </p>

    <h2>7. Subscription, renewal and cancellation</h2>
    <ul>
        <li><strong>Seller</strong>: subscriptions are sold and charged by <strong>Apple</strong> (App Store) or <strong>Google</strong> (Google Play) as the sellers. Their terms govern the transaction, and we never see your payment details.</li>
        <li><strong>Terms and prices</strong>: monthly or yearly. The applicable price, including tax, is shown in the app before purchase and confirmed by the store.</li>
        <li><strong>Automatic renewal</strong>: the subscription renews automatically for the same period unless cancelled at least <strong>24 hours before the end of the current period</strong>. The store charges the renewal within the 24 hours before expiry.</li>
        <li><strong>Cancellation</strong>: at any time, in your App Store or Google Play account settings. Cancellation takes effect at the end of the period already paid for; you keep access until then. Deleting the app does not cancel the subscription.</li>
        <li><strong>Refunds</strong>: handled by Apple or Google, depending on the store. Write to us if you think something went wrong and we will help you through it.</li>
        <li><strong>After a subscription ends</strong>: your journal and entries stay intact. You keep local access, export, and backup within the free plan's limits.</li>
    </ul>

    <h2>8. Right of withdrawal</h2>
    <p>
        <strong>You have 14 days to withdraw</strong> from the moment you subscribe to Nacre Plus. The
        subscription is a continuously supplied digital service, so it does not fall under the
        exception written for digital content: the right of withdrawal stays open for as long as the
        service has not been <em>fully performed</em> (article L.221-28 1° of the French Consumer
        Code) — which is never the case for a running subscription.
    </p>
    <p>
        If you asked for the subscription to start immediately, you can still withdraw within that
        period; you only owe the amount matching the part of the service already supplied, pro rata
        (article L.221-25). A plain email is enough, with no reason required.
    </p>
    <p>
        In practice, because Apple or Google collect the payment, the refund goes through their
        channel: App Store account settings → Purchase history, or Google Play → Subscriptions. Write
        to <a href="mailto:{{ $legal['contact_email'] }}">{{ $legal['contact_email'] }}</a> if you are
        refused or run into trouble — we will back your request with the store.
    </p>

    <h2>9. AI Chapters</h2>
    <p>
        The “Chapters” feature is <strong>off by default</strong> and only turns on with your explicit
        consent. Once enabled, the journal content needed is sent to our AI provider to write the text
        (see the <a href="{{ route('legal.privacy', ['lang' => $lang]) }}">privacy policy</a>).
    </p>
    <p>
        A Chapter is automatically generated text. It may contain errors, approximations or
        inventions. It is <strong>not medical, psychological, legal or financial advice</strong>, and it
        does not replace a professional. We do not guarantee its accuracy, its continued availability,
        or how long generation takes.
    </p>

    <h2>10. Availability and warranties</h2>
    <p>
        We do our best to keep the service available, without guaranteeing uninterrupted or error-free
        operation — maintenance, a provider outage or an incident can happen. Back up your journal
        regularly using the export feature: it is the surest way to hold a copy that depends on no one.
    </p>
    <p>
        These terms do not affect the statutory guarantee of conformity for digital content and
        services (articles L.224-25-1 et seq. of the French Consumer Code), nor any mandatory consumer
        rights you have under the law of your country of residence.
    </p>

    <h2>11. Limitation of liability</h2>
    <p>
        To the extent permitted by law, {{ $publisher['name'] }} is not liable for indirect damages.
        <strong>Nothing in these terms excludes or limits liability for wilful misconduct, gross
        negligence, personal injury, or the rights that the law grants consumers on a mandatory
        basis</strong> — in particular our liability for a lack of conformity of the service,
        including data loss attributable to us. The advice to export your journal regularly stays
        advice: it does not reduce that liability in any way.
    </p>

    <h2>12. Suspension and termination</h2>
    <p>
        You may delete your account and server-side data at any time in Settings → Account. We may
        suspend or close access in case of a clear breach of these terms or manifest abuse of the
        service; except in an emergency or where the law requires otherwise, we tell you first and let
        you export your journal.
    </p>

    <h2>13. Changes to these terms</h2>
    <p>
        We may update these terms; the date at the top reflects the latest version. For a material
        change, we notify you in the app. If you are a subscriber and the change disadvantages you, you
        may cancel at no cost.
    </p>

    <h2>14. Complaints and disputes</h2>
    <p>
        Write to us first at
        <a href="mailto:{{ $legal['contact_email'] }}">{{ $legal['contact_email'] }}</a>: we answer any
        complaint within 14 days, and most problems are settled there.
    </p>
    <p>
        These terms are governed by <strong>French law</strong>. If you are a consumer resident in
        another European Union country, you keep the benefit of the mandatory provisions of your
        country of residence and may bring proceedings before the courts of your domicile.
    </p>

    <h2>15. Contact</h2>
    <p><a href="mailto:{{ $legal['contact_email'] }}">{{ $legal['contact_email'] }}</a></p>
@endif
@endsection
