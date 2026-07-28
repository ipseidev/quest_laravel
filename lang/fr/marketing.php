<?php

/**
 * Copie du site public — français (marché principal).
 *
 * Trois règles tenues dans tout ce fichier, parce qu'elles engagent :
 *
 * 1. Aucun chiffre en dur. Les prix, le quota, le nombre de thèmes passent par
 *    `:monthly`, `:quota`, `:themes_total` et sont résolus par `App\Support\Copy`
 *    depuis `config/site.php`. Une page ne peut donc pas contredire une autre.
 * 2. Jamais de chiffrement de bout en bout. Les données synchronisées sont
 *    chiffrées au repos côté serveur avec une clé que le serveur peut lire — ce
 *    n'est pas du E2E, et le prétendre serait faux.
 * 3. Zéro esthétique de jeu. Le vocabulaire de quête est conceptuel. Pas de
 *    points, pas de niveaux, pas de badges, pas de classement.
 *
 * Tutoiement partout, comme dans l'app. Les espaces insécables (U+00A0) devant
 * « % », « : » et « € » sont volontaires : c'est la typographie française, et ça
 * évite qu'un prix se coupe en fin de ligne.
 */
return [

    'common' => [
        'skip_to_content' => 'Aller au contenu',
        'category' => 'Journal intime',
        'og_alt' => 'Nacre — un journal intime où ta vie devient une histoire',
        'app_description' => 'Un journal intime pour iOS et Android : écris tes journées, relie-les à ce que tu traverses et aux gens qui comptent, puis relis ton histoire fil par fil.',
        'download_ios' => 'Télécharger sur l’App Store',
        'download_android' => 'Télécharger sur Google Play',
        'soon_ios' => 'Bientôt sur l’App Store',
        'soon_android' => 'Bientôt sur Google Play',
        'previous_name_note' => 'Sur l’App Store, la fiche s’appelle encore :store_name — le nouveau nom arrive avec la prochaine mise à jour.',
        'learn_more' => 'En savoir plus',
        'free_note' => 'Gratuit, et sans compte obligatoire : l’app fonctionne entièrement sur ton téléphone.',
    ],

    'nav' => [
        'label' => 'Navigation principale',
        'home' => 'Accueil',
        'menu' => 'Ouvrir le menu',
        'download' => 'Télécharger',
        'switch_language' => 'Voir cette page en',
    ],

    'footer' => [
        'tagline' => 'Un journal intime où ta vie devient une histoire. Conçu et édité en France.',
        'product' => 'Le produit',
        'company' => 'Nacre',
        'legal' => 'Légal',
        'publisher' => 'Édité par :publisher, entrepreneur individuel — SIREN :siren. Contact :',
    ],

    /*
    |---------------------------------------------------------------------------
    | Accueil
    |---------------------------------------------------------------------------
    */

    'home' => [
        'short' => 'Accueil',

        'meta' => [
            'title' => 'Nacre — Journal intime privé où ta vie devient une histoire',
            'description' => 'Un journal intime pour iPhone et Android. Écris tes journées, relie-les à tes quêtes et aux gens qui comptent, puis relis ton histoire fil par fil. Gratuit.',
        ],

        'hero' => [
            'eyebrow' => 'Journal intime · iOS et Android',
            'title' => 'Ta vie est déjà une histoire.',
            'lead' => 'Nacre est un journal intime où chaque page se rattache à ce que tu traverses et aux gens qui comptent. Un an plus tard, tu ne relis pas une pile de dates : tu relis un fil.',
            'shot_alt' => 'Une entrée de journal dans Nacre : deux photos, une humeur, une personne liée et le texte de la journée.',
        ],

        'problem' => [
            'eyebrow' => 'Pourquoi ça ne tient pas',
            'title' => 'Tu as déjà essayé de tenir un journal.',
            'lead' => 'Et tu as probablement arrêté. Pas par manque de volonté — parce qu’un journal classique ne te rend rien.',
            'points' => [
                [
                    'title' => 'Tu écris dans le vide.',
                    'body' => 'Trois semaines d’entrées, puis plus rien. Personne ne relit une liste de dates. Pas même celui qui l’a écrite.',
                ],
                [
                    'title' => 'Les fils se perdent.',
                    'body' => 'Ce que tu as écrit sur ce projet, cette relation, cette décision : éparpillé sur onze mois, introuvable au moment où tu en aurais besoin.',
                ],
                [
                    'title' => 'La page blanche gagne.',
                    'body' => 'Il faudrait avoir quelque chose à dire. Certains soirs tu n’as qu’une humeur et deux phrases — et ça devrait suffire.',
                ],
            ],
        ],

        'pillars' => [
            'eyebrow' => 'Comment ça marche',
            'title' => 'Quatre choses, et rien de plus.',
            'lead' => 'Nacre pose une grille narrative sur un journal ordinaire. La grille est conceptuelle, jamais visuelle : aucun point, aucun niveau, aucun badge.',
            'items' => [
                [
                    'key' => 'pages',
                    'title' => 'Les pages',
                    'body' => 'Écris comme dans n’importe quel journal : du texte, des photos, une note vocale, un lieu, une humeur. Le titre se déduit de ta première ligne et tout s’enregistre pendant que tu écris.',
                    'shot' => 'pages',
                    'alt' => 'Le fil chronologique des pages dans Nacre, avec les quêtes et les personnes liées sous chaque entrée.',
                ],
                [
                    'key' => 'features.quests',
                    'title' => 'Les quêtes',
                    'body' => 'Une quête principale — la grande question de ton année. Des quêtes secondaires — un projet, une relation compliquée, un déménagement. Rattache une entrée d’un tap, ou pas du tout.',
                    'shot' => 'quests',
                    'alt' => 'L’écran Quêtes de Nacre, avec une quête principale et des quêtes secondaires.',
                ],
                [
                    'key' => 'features.people',
                    'title' => 'Les personnes',
                    'body' => 'Ta sœur, ton thérapeute, le collègue avec qui c’est tendu. Ouvre quelqu’un et revis, dans l’ordre, chaque page où il est apparu.',
                    'shot' => 'person',
                    'alt' => 'La fiche d’une personne dans Nacre, suivie de toutes les entrées où elle apparaît.',
                ],
                [
                    'key' => 'features.constellation',
                    'title' => 'La constellation',
                    'body' => 'Tes pages, tes quêtes et tes personnes dessinées en un ciel. Fais glisser le temps et regarde-le se construire, jour après jour.',
                    'shot' => 'constellation',
                    'alt' => 'La vue Constellation de Nacre : les entrées et les quêtes reliées en une carte d’étoiles.',
                ],
            ],
        ],

        'replay' => [
            'eyebrow' => 'Ce qu’un journal chronologique ne peut pas faire',
            'title' => 'Relire par fil, pas par date.',
            'lead' => 'C’est toute la différence. Ouvre « Trouver ce que je veux de ce travail » et tu vois les onze pages qui l’ont ponctué, dans l’ordre, sur huit mois. Ouvre « Priya » et tu revois chaque fois qu’elle est apparue.',
            'body' => 'Et ça ne se rattrape pas après coup. Au bout de trois ans, ce que tu as construit — tes pages croisées à tes quêtes et à tes personnes — n’existe nulle part ailleurs et ne se réimporte pas.',
            'shot_alt' => 'Une quête ouverte dans Nacre, avec la suite des entrées qui l’ont traversée.',
        ],

        'friction' => [
            'eyebrow' => 'Zéro friction',
            'title' => 'Deux secondes entre l’envie d’écrire et la première ligne.',
            'lead' => 'Un journal ne tient que si l’ouvrir ne coûte rien.',
            'points' => [
                'Une humeur et une phrase suffisent. C’est une entrée valide.',
                'Photo, appareil photo, note vocale, lieu : dans la barre de l’éditeur, pas au fond d’un menu.',
                'Enregistrement automatique pendant que tu écris. Rien à valider, rien à perdre.',
                'Une question du jour, les soirs où la page blanche gagne.',
                '« Ce jour-là » te ressort tes anciennes pages — avec les quêtes actives et les gens présents à l’époque.',
                'Calendrier, recherche plein texte, corbeille de :retention jours.',
            ],
        ],

        'not' => [
            'eyebrow' => 'Pour être clair',
            'title' => 'Ce que Nacre n’est pas.',
            'lead' => 'Autant le dire tout de suite. Ça t’évitera un téléchargement pour rien.',
            'items' => [
                [
                    'title' => 'Pas un jeu.',
                    'body' => 'On dit « quête » parce que c’est exactement ça, pas pour faire joli. Aucun XP, aucun niveau, aucun badge, aucun classement. Les streaks existent, discrets : une présence, pas un score.',
                ],
                [
                    'title' => 'Pas un réseau social.',
                    'body' => 'Aucun partage, aucun profil public, aucun fil d’actualité, aucun ami à ajouter. Personne ne lit tes pages.',
                ],
                [
                    'title' => 'Pas un outil de thérapie.',
                    'body' => 'Nacre accompagne une réflexion. Il ne diagnostique rien et ne remplace personne.',
                ],
                [
                    'title' => 'Pas une app de productivité.',
                    'body' => 'Aucune habitude à cocher, aucun objectif à atteindre. Tu n’as rien à performer ici.',
                ],
            ],
        ],

        'privacy' => [
            'eyebrow' => 'Vie privée',
            'title' => 'Ton journal reste à toi.',
            'lead' => 'Ce qui est vrai, dit précisément — y compris là où ça nous coûte.',
            'points' => [
                'Le compte est optionnel. Sans compte, tout reste sur ton téléphone.',
                'Aucun analytics, aucun traceur tiers, aucune publicité.',
                'Verrouillage par Face ID, Touch ID ou code.',
                'Export libre en :exports, à tout moment, sans rien demander.',
                'Les Chapitres écrits par IA sont désactivés par défaut. Tant que tu ne les actives pas, aucun mot de ton journal ne part vers un service d’IA.',
            ],
            'link' => 'Comment on protège tes pages, en détail',
            'shot_alt' => 'L’écran de verrouillage de Nacre, qui demande une authentification avant d’ouvrir le journal.',
        ],

        'nacre' => [
            'title' => 'Pourquoi « Nacre ».',
            'body' => 'La nacre ne se fabrique pas. Elle se dépose — couche après couche, année après année, jusqu’à devenir autre chose. C’est exactement ce que fait un journal qu’on tient vraiment : le premier mois ne vaut presque rien, et le troisième an ne se remplace pas.',
        ],

        'pricing' => [
            'eyebrow' => 'Tarifs',
            'title' => 'Gratuit pour écrire. Payant pour tout retrouver partout.',
            'lead' => 'Écrire, relier, relire, chercher et exporter : gratuit, sans limite de pages. Nacre Plus ajoute la synchronisation entre tes appareils, un Chapitre chaque mois et les :themes_total thèmes — :monthly par mois, ou :annual par an.',
            'link' => 'Voir le détail des deux formules',
        ],

        'faq' => [
            [
                'q' => 'Nacre est-il gratuit ?',
                'a' => 'Oui. Écrire, créer des quêtes et des personnes, relire par fil, chercher et exporter sont gratuits, sans limite de pages. Nacre Plus est un abonnement optionnel à :monthly par mois ou :annual par an qui ajoute la synchronisation entre appareils, un Chapitre chaque mois, les :themes_total thèmes et la sauvegarde illimitée de tes photos et notes vocales.',
            ],
            [
                'q' => 'Est-ce que je peux écrire sans créer de compte ?',
                'a' => 'Oui, et c’est le mode par défaut. Nacre fonctionne entièrement hors ligne sur ton téléphone. Un compte ne sert qu’à sauvegarder tes pages et à les retrouver sur un autre appareil.',
            ],
            [
                'q' => 'Est-ce qu’une IA lit mon journal ?',
                'a' => 'Pas sans que tu l’aies demandé. Les Chapitres — les récits que Nacre écrit à partir de ton journal — sont désactivés par défaut. Si tu les actives, le texte de tes entrées est envoyé à notre prestataire d’IA, :ai_provider, pour écrire le récit. Tu peux les couper à tout moment.',
            ],
            [
                'q' => 'Est-ce que je peux récupérer mes données ?',
                'a' => 'Toujours, et sans abonnement. L’export en :exports est dans les réglages, gratuit, et il contient tout : tes pages, tes quêtes, tes personnes et les liens entre eux.',
            ],
        ],

        'cta' => [
            'title' => 'Commence ce soir.',
            'lead' => 'Une humeur et deux phrases suffisent. Dans un an, tu auras un fil à relire.',
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | Fonctionnalités
    |---------------------------------------------------------------------------
    */

    'features' => [
        'short' => 'Fonctionnalités',

        'meta' => [
            'title' => 'Fonctionnalités — Nacre, application de journal intime',
            'description' => 'Pages, quêtes, personnes, constellation et Chapitres : tout ce que fait Nacre, l’application de journal intime où ta vie se relit comme une histoire.',
        ],

        'hero' => [
            'eyebrow' => 'Le produit',
            'title' => 'Un journal, et une façon de le relire.',
            'lead' => 'Nacre reste un journal : tu écris, c’est tout. Ce qui change, c’est ce que tu peux en faire un an plus tard.',
        ],

        'basics' => [
            'title' => 'Et tout ce qu’on attend d’un journal',
            'items' => [
                [
                    'title' => 'Un éditeur qui ne t’attend pas',
                    'body' => 'Texte enrichi, photos en pièce jointe, notes vocales, lieu, humeur, tags. Enregistrement automatique pendant la frappe, titre déduit de ta première ligne.',
                ],
                [
                    'title' => 'Retrouver, vraiment',
                    'body' => 'Recherche plein texte sur tout le journal, calendrier mensuel, « Ce jour-là » avec le contexte de l’époque, corbeille de :retention jours.',
                ],
                [
                    'title' => 'À ton goût',
                    'body' => ':themes_total thèmes, :fonts polices, couleur d’accent réglable. Le thème suit ton système si tu veux.',
                ],
                [
                    'title' => 'Fermé à clé',
                    'body' => 'Verrouillage par Face ID, Touch ID ou code, à l’ouverture de l’app.',
                ],
                [
                    'title' => 'Tes données sortent quand tu veux',
                    'body' => 'Export en :exports. Gratuit, complet, sans conditions.',
                ],
                [
                    'title' => 'Français et anglais',
                    'body' => 'L’app est écrite en français, pas traduite depuis l’anglais. iOS, iPad et Android.',
                ],
            ],
        ],

        'quests' => [
            'short' => 'Les quêtes',
            'meta' => [
                'title' => 'Les quêtes — donner un fil à ton journal | Nacre',
                'description' => 'Une quête principale pour la grande question de ton année, des quêtes secondaires pour ce que tu traverses. Relie tes pages et relis un fil entier dans l’ordre.',
            ],
            'hero' => [
                'eyebrow' => 'Fonctionnalité',
                'title' => 'Les quêtes',
                'lead' => 'Une quête, c’est un fil qui te traverse. Pas une tâche, pas un objectif : ce qui se joue vraiment en ce moment.',
                'shot_alt' => 'L’écran Quêtes de Nacre, avec une quête principale et deux quêtes secondaires.',
            ],
            'points' => [
                [
                    'title' => 'Une quête principale à la fois',
                    'body' => 'La grande question de ton année — « trouver ce que je veux de ce travail », « quitter la ville ». Une seule active, parce que c’est déjà beaucoup. La précédente part aux archives, jamais à la poubelle.',
                ],
                [
                    'title' => 'Des quêtes secondaires autant que ta vie en a',
                    'body' => 'Un projet qui traîne depuis mars, une relation compliquée, une transition. Elles ont un statut — active, terminée, archivée — et une date de début qui peut précéder le jour où tu l’as créée.',
                ],
                [
                    'title' => 'Des quotidiennes, si tu veux',
                    'body' => 'Une catégorie légère pour les petits arcs récurrents. Optionnelle, à activer dans les réglages.',
                ],
                [
                    'title' => 'Lier reste facultatif',
                    'body' => 'Un tap depuis l’éditeur rattache la page à une ou plusieurs quêtes. Ne rien rattacher est un usage normal, pas un oubli : écrire d’abord.',
                ],
                [
                    'title' => 'Et surtout : la relecture',
                    'body' => 'Ouvre une quête et tu as la suite des pages qui l’ont ponctuée, dans l’ordre, avec les gens qui y sont apparus. C’est là que le journal commence à servir.',
                ],
            ],
        ],

        'people' => [
            'short' => 'Les personnes',
            'meta' => [
                'title' => 'Les personnes — revivre chaque page où ils apparaissent | Nacre',
                'description' => 'Ajoute les gens qui reviennent dans ta vie et retrouve, dans l’ordre, chaque entrée de journal où ils sont apparus. Photo et note optionnelles.',
            ],
            'hero' => [
                'eyebrow' => 'Fonctionnalité',
                'title' => 'Les personnes',
                'lead' => 'Les gens qui traversent ton histoire. Ceux qui reviennent, page après page, parfois sans que tu t’en rendes compte.',
                'shot_alt' => 'La fiche d’une personne dans Nacre, suivie de la chronologie des entrées où elle apparaît.',
            ],
            'points' => [
                [
                    'title' => 'Un nom suffit',
                    'body' => 'Une relation et une note si tu veux, une photo si tu veux. Rien n’est obligatoire à part le nom.',
                ],
                [
                    'title' => 'Mentionne-les en écrivant',
                    'body' => 'Un tap depuis l’éditeur relie la page à une personne. Son nom apparaît alors dans le texte, dans sa couleur.',
                ],
                [
                    'title' => 'Ouvre quelqu’un, revis tout',
                    'body' => 'Sa fiche liste chaque page où il est apparu, du plus récent au plus ancien, avec l’humeur de chaque jour. C’est souvent plus parlant que ce qu’on croyait avoir écrit.',
                ],
                [
                    'title' => 'Ils apparaissent aussi dans tes quêtes',
                    'body' => 'Une quête montre les personnes qui l’ont traversée. Un déménagement, ce n’est pas qu’un lieu : ce sont ceux qui étaient là.',
                ],
            ],
        ],

        'chapters' => [
            'short' => 'Les Chapitres',
            'meta' => [
                'title' => 'Les Chapitres — ton journal réécrit en récit | Nacre',
                'description' => 'Nacre peut relire ton mois et en écrire l’histoire. Une couche d’IA optionnelle, désactivée par défaut : rien ne part tant que tu ne l’as pas activée.',
            ],
            'hero' => [
                'eyebrow' => 'Fonctionnalité optionnelle',
                'title' => 'Les Chapitres',
                'lead' => 'Une fois par mois, Nacre relit ce que tu as écrit et en fait un récit. Pas un résumé à puces : un texte, avec un titre, qui raconte ton mois.',
                'shot_alt' => 'L’écran Chapitres de Nacre, avec un chapitre mensuel et une fin d’arc.',
            ],
            'points' => [
                [
                    'title' => 'Quatre sortes de chapitres',
                    'body' => 'Le chapitre du mois. La fin d’un arc, quand une quête se termine. Ton année en récit. Et « depuis le début », qui reprend tout.',
                ],
                [
                    'title' => 'Désactivé par défaut',
                    'body' => 'C’est le point important. À l’installation, la couche IA est éteinte. Tant que tu ne l’allumes pas dans les réglages, aucun mot de ton journal ne quitte l’app pour un service d’IA.',
                ],
                [
                    'title' => 'Ce qui se passe si tu l’allumes',
                    'body' => 'Le texte de tes entrées est envoyé à notre prestataire d’IA, :ai_provider, qui écrit le récit. On te le dit dans les réglages, au moment où tu actives l’option, pas dans une note de bas de page.',
                ],
                [
                    'title' => 'Un chapitre offert pour voir',
                    'body' => 'Une fois que ton journal est un peu rempli, tu peux faire écrire ton premier Chapitre gratuitement. Ensuite, c’est Nacre Plus qui t’en envoie un chaque mois.',
                ],
                [
                    'title' => 'Tu peux couper à tout moment',
                    'body' => 'Un interrupteur, dans les réglages. Les chapitres déjà écrits restent lisibles.',
                ],
            ],
        ],

        'constellation' => [
            'short' => 'La constellation',
            'meta' => [
                'title' => 'La constellation — voir la forme de ton histoire | Nacre',
                'description' => 'Tes pages, tes quêtes et tes personnes dessinées en un ciel. Fais glisser le temps et regarde des années de journal se construire, jour après jour.',
            ],
            'hero' => [
                'eyebrow' => 'Fonctionnalité',
                'title' => 'La constellation',
                'lead' => 'La forme de ton histoire, vue de loin. Chaque page est une étoile, chaque quête et chaque personne un nœud qui les rassemble.',
                'shot_alt' => 'La vue Constellation de Nacre : des entrées reliées à leurs quêtes et à leurs personnes, sur un fond de nuit.',
            ],
            'points' => [
                [
                    'title' => 'Tape un nœud, vois ses liens',
                    'body' => 'Les connexions s’allument. Retape pour ouvrir la quête ou la personne, et retomber dans les pages.',
                ],
                [
                    'title' => 'Remonte le temps',
                    'body' => 'La barre en bas rejoue ton journal depuis le début. Les étoiles apparaissent dans l’ordre où tu les as écrites.',
                ],
                [
                    'title' => 'Elle ne ressemble qu’à toi',
                    'body' => 'C’est le seul écran de Nacre que personne d’autre ne peut avoir. Après deux ans, il n’y a pas deux ciels identiques.',
                ],
                [
                    'title' => 'Et elle est gratuite',
                    'body' => 'La constellation n’est pas derrière l’abonnement. C’est la signature de l’app, elle doit être à tout le monde.',
                ],
            ],
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | Vie privée (page marketing — la politique juridique est ailleurs)
    |---------------------------------------------------------------------------
    */

    'privacy' => [
        'short' => 'Vie privée',

        'meta' => [
            'title' => 'Vie privée — ce qu’on fait vraiment de ton journal | Nacre',
            'description' => 'Compte optionnel, aucun traceur, verrouillage biométrique, export libre, IA éteinte par défaut. Et ce qu’on ne prétend pas : ce n’est pas du bout en bout.',
        ],

        'hero' => [
            'eyebrow' => 'Vie privée',
            'title' => 'Ce qu’on fait de ton journal. Et ce qu’on ne prétend pas.',
            'lead' => 'Un journal intime ne se confie qu’à quelqu’un de précis. Voici les faits, y compris ceux qui ne nous arrangent pas.',
            'shot_alt' => 'L’écran de verrouillage de Nacre, qui demande une authentification avant d’ouvrir le journal.',
        ],

        'promises' => [
            'title' => 'Ce qui est vrai',
            'items' => [
                [
                    'title' => 'Le compte est optionnel',
                    'body' => 'Nacre s’installe et s’utilise entièrement sans compte. Dans ce mode, tes pages ne quittent jamais ton téléphone : il n’y a rien à intercepter parce qu’il n’y a rien qui part.',
                ],
                [
                    'title' => 'Aucun traceur, aucune publicité',
                    'body' => 'Pas d’analytics, pas de SDK publicitaire, pas de mesure d’audience tierce, pas de revente. Nacre n’a aucun intérêt économique à savoir ce que tu écris — c’est toi qui paies l’app, pas un annonceur.',
                ],
                [
                    'title' => 'Fermé à clé sur l’appareil',
                    'body' => 'Face ID, Touch ID ou code, exigé à l’ouverture. Le contenu ne s’affiche jamais brièvement avant le verrou.',
                ],
                [
                    'title' => 'L’IA est éteinte au départ',
                    'body' => 'Les Chapitres sont la seule fonction qui envoie du texte ailleurs, et ils sont désactivés par défaut. Si tu les actives, tes entrées sont transmises à :ai_provider pour écrire le récit, et tu peux couper à tout moment.',
                ],
                [
                    'title' => 'Tes données sortent librement',
                    'body' => 'Export en :exports depuis les réglages, gratuit, complet, y compris les liens entre pages, quêtes et personnes. Partir doit être facile, sinon rester ne veut rien dire.',
                ],
                [
                    'title' => 'Supprimer veut dire supprimer',
                    'body' => 'La corbeille garde :retention jours, puis efface définitivement — les fichiers joints avec. Supprimer ton compte efface tout le contenu associé.',
                ],
            ],
        ],

        'honest' => [
            'title' => 'Ce qu’on ne prétend pas',
            'lead' => 'Beaucoup d’apps de journal écrivent « chiffré de bout en bout » sans que ce soit vrai. Voilà notre situation, exactement.',
            'items' => [
                [
                    'title' => 'Ce n’est pas du chiffrement de bout en bout',
                    'body' => 'Si tu actives la synchronisation, le texte de tes pages est chiffré au repos sur le serveur — mais avec une clé que le serveur peut lire. Techniquement, l’hébergeur pourrait donc y accéder. C’est un choix assumé : il rend possible la récupération de compte et les Chapitres. Le vrai bout-en-bout est l’objectif de la V1, et on ne l’annoncera pas avant de l’avoir.',
                ],
                [
                    'title' => 'La base locale n’est pas chiffrée en plus',
                    'body' => 'Sur ton téléphone, tes pages sont protégées par le bac à sable du système et par le verrou biométrique, pas par une couche de chiffrement supplémentaire. Sur un appareil déverrouillé et compromis, ça ne suffirait pas.',
                ],
                [
                    'title' => 'On a besoin d’un minimum sur toi',
                    'body' => 'Si tu crées un compte, on stocke de quoi t’identifier et te reconnecter. Pas plus. Et si tu utilises Nacre sans compte, on n’a rien du tout.',
                ],
            ],
        ],

        'legal_links' => [
            'title' => 'Les documents qui engagent',
            'lead' => 'Cette page explique. Ce sont ceux-là qui ont valeur contractuelle.',
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | Tarifs
    |---------------------------------------------------------------------------
    */

    'pricing' => [
        'short' => 'Tarifs',

        'meta' => [
            'title' => 'Tarifs — Nacre est gratuit, Nacre Plus est optionnel',
            'description' => 'Écrire, relier et exporter sont gratuits, sans limite de pages. Nacre Plus : :monthly par mois ou :annual par an pour la synchro et un Chapitre chaque mois.',
        ],

        'hero' => [
            'eyebrow' => 'Tarifs',
            'title' => 'Gratuit pour écrire. Payant pour tout retrouver partout.',
            'lead' => 'Le journal complet est gratuit et sans limite de pages. Nacre Plus paie ce qui coûte de l’argent chaque mois : du stockage, des serveurs, et l’IA qui écrit tes Chapitres.',
        ],

        'free' => [
            'name' => 'Nacre',
            'price' => 'Gratuit',
            'price_note' => 'Pour toujours, sans carte bancaire',
            'summary' => 'Le journal en entier, sur un appareil.',
            'items' => [
                'Pages illimitées — texte, photos, notes vocales, lieu, humeur',
                'Quêtes et personnes illimitées, et la relecture par fil',
                'La constellation, en entier',
                'Recherche plein texte, calendrier, « Ce jour-là »',
                ':themes_free thèmes, :fonts polices, couleur d’accent',
                'Verrouillage Face ID / Touch ID',
                'Export en :exports',
                'Sauvegarde du texte dans le nuage, sans limite',
                ':quota de photos et de notes vocales sauvegardées',
                'Un Chapitre offert, pour voir ce que ça donne',
            ],
            'cta' => 'Télécharger Nacre',
        ],

        'plus' => [
            'name' => 'Nacre Plus',
            'monthly_label' => 'par mois',
            'annual_label' => 'par an',
            'annual_badge' => 'soit :annual_per_month par mois — :saving % de moins',
            'summary' => 'Tout ce qui est ci-contre, plus :',
            'items' => [
                'Tes pages sur tous tes appareils, dans les deux sens',
                'Un nouveau Chapitre chaque mois, et à chaque fin d’arc',
                'Les :themes_total thèmes, dont sépia, forêt, océan et coucher de soleil',
                'Photos et notes vocales sauvegardées sans limite de volume',
                'Le soutien d’un développeur indépendant, sans investisseurs à rembourser',
            ],
            'cta' => 'S’abonner depuis l’app',
            'cta_note' => 'L’abonnement se souscrit dans Nacre, depuis les réglages. Il se résilie depuis ton compte App Store ou Google Play, à tout moment.',
        ],

        'why' => [
            'title' => 'Pourquoi un abonnement, et pas un achat unique',
            'body' => 'Parce que la partie payante coûte de l’argent tous les mois : stocker tes photos, faire tourner la synchronisation, et payer l’IA qui écrit tes Chapitres. Un paiement unique pour un service récurrent, c’est une promesse qu’on finit par ne pas tenir. Le journal, lui, reste gratuit — et si tu arrêtes de payer, tu gardes tout et tu continues d’écrire.',
        ],

        'faq' => [
            [
                'q' => 'Qu’est-ce que je perds si j’arrête de payer ?',
                'a' => 'Rien de ce que tu as écrit. Tes pages, tes quêtes, tes personnes et tes chapitres déjà générés restent sur ton appareil et restent exportables. Tu reviens simplement au fonctionnement gratuit : un seul appareil synchronisé, :themes_free thèmes, et plus de nouveau Chapitre chaque mois.',
            ],
            [
                'q' => 'Pourquoi la synchronisation entre appareils est-elle payante ?',
                'a' => 'Parce que c’est la partie qui a un coût récurrent réel : des serveurs et du stockage, chaque mois, pour chaque utilisateur. La sauvegarde de ton texte, elle, est gratuite et sans limite — tu ne risques pas de perdre ton journal parce que tu ne paies pas.',
            ],
            [
                'q' => 'Comment je résilie ?',
                'a' => 'Depuis les abonnements de ton compte App Store ou Google Play, en deux taps, sans nous écrire. C’est Apple et Google qui gèrent la facturation ; nous ne voyons jamais ta carte.',
            ],
            [
                'q' => 'Y a-t-il un essai gratuit ?',
                'a' => 'Le journal entier est gratuit sans limite de durée, ce qui est déjà mieux qu’un essai : tu peux écrire pendant des mois avant de te demander si tu veux Plus. Et le premier Chapitre est offert, pour juger sur pièce.',
            ],
            [
                'q' => 'Puis-je payer une fois pour toutes ?',
                'a' => 'Non, et c’est volontaire. Les deux formules sont mensuelle et annuelle. Un achat définitif pour un service qui coûte chaque mois finit toujours mal, pour vous comme pour nous.',
            ],
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | FAQ
    |---------------------------------------------------------------------------
    */

    'faq' => [
        'short' => 'FAQ',

        'meta' => [
            'title' => 'Questions fréquentes — Nacre, journal intime iOS et Android',
            'description' => 'Prix, vie privée, IA, export, synchronisation, plateformes : les réponses aux questions qu’on nous pose sur Nacre.',
        ],

        'hero' => [
            'eyebrow' => 'FAQ',
            'title' => 'Questions fréquentes',
            'lead' => 'Et les réponses honnêtes, y compris quand elles ne nous avantagent pas.',
        ],

        'groups' => [
            'basics' => 'L’essentiel',
            'privacy' => 'Vie privée et données',
            'pricing' => 'Prix et abonnement',
            'product' => 'Le produit au quotidien',
        ],

        'faq' => [
            [
                'group' => 'basics',
                'q' => 'Qu’est-ce que Nacre, en une phrase ?',
                'a' => 'Une application de journal intime pour iPhone, iPad et Android où chaque page peut se rattacher à ce que tu traverses (les quêtes) et aux gens qui comptent (les personnes), pour que tu puisses relire un fil entier au lieu d’une suite de dates.',
            ],
            [
                'group' => 'basics',
                'q' => 'Sur quelles plateformes Nacre existe-t-il ?',
                'a' => 'iPhone et iPad. La version Android est en test fermé et arrive ensuite, avec les mêmes fonctions — la parité entre les deux plateformes est une règle du projet, pas une intention.',
            ],
            [
                'group' => 'basics',
                'q' => 'Y a-t-il une version web ou ordinateur ?',
                'a' => 'Non. Nacre est pensé pour le téléphone, parce que c’est là qu’on écrit un journal — le soir, dans le train, deux minutes avant de dormir.',
            ],
            [
                'group' => 'basics',
                'q' => 'Faut-il remplir les quêtes et les personnes pour s’en servir ?',
                'a' => 'Non. Tu peux utiliser Nacre comme un journal parfaitement ordinaire et ne jamais rien relier. Les liens sont là le jour où tu en as envie, et ils se posent après coup aussi bien qu’au moment d’écrire.',
            ],
            [
                'group' => 'privacy',
                'q' => 'Est-ce que quelqu’un peut lire mon journal ?',
                'a' => 'Sans compte, non : tout reste sur ton téléphone. Avec la synchronisation, tes pages sont chiffrées au repos sur le serveur, mais avec une clé lisible par le serveur — donc techniquement l’hébergeur pourrait y accéder. Ce n’est pas du chiffrement de bout en bout et nous ne l’écrivons nulle part. Le vrai bout-en-bout est prévu, il n’est pas encore là.',
            ],
            [
                'group' => 'privacy',
                'q' => 'Est-ce qu’une IA lit ce que j’écris ?',
                'a' => 'Uniquement si tu actives les Chapitres, qui sont désactivés par défaut. Dans ce cas, le texte de tes entrées est envoyé à :ai_provider pour écrire le récit. Aucune autre fonction n’envoie ton texte à un service d’IA, et tu peux désactiver l’option à tout moment.',
            ],
            [
                'group' => 'privacy',
                'q' => 'Y a-t-il des traceurs ou de la publicité ?',
                'a' => 'Aucun des deux. Pas de mesure d’audience tierce, pas de SDK publicitaire, aucune revente de données. Un rapport de plantage technique existe pour corriger les bugs ; il ne contient pas le contenu de tes pages.',
            ],
            [
                'group' => 'privacy',
                'q' => 'Comment je récupère tout et je m’en vais ?',
                'a' => 'Réglages, export, tu choisis :exports. C’est gratuit, complet, et ça inclut les liens entre tes pages, tes quêtes et tes personnes. Supprimer ton compte efface le contenu associé côté serveur.',
            ],
            [
                'group' => 'pricing',
                'q' => 'Combien ça coûte ?',
                'a' => 'Le journal est gratuit, sans limite de pages. Nacre Plus coûte :monthly par mois ou :annual par an (soit :annual_per_month par mois, :saving % de moins) et ajoute la synchronisation entre appareils, un Chapitre chaque mois, les :themes_total thèmes et la sauvegarde illimitée de tes médias.',
            ],
            [
                'group' => 'pricing',
                'q' => 'Que garde-t-on gratuitement, exactement ?',
                'a' => 'Toute l’écriture et toute la relecture : pages illimitées, quêtes, personnes, constellation, recherche, calendrier, « Ce jour-là », export, verrouillage biométrique, :themes_free thèmes, la sauvegarde de ton texte sans limite et :quota de photos et de notes vocales.',
            ],
            [
                'group' => 'pricing',
                'q' => 'Y a-t-il un achat définitif ?',
                'a' => 'Non. Deux formules seulement, mensuelle et annuelle. La partie payante a un coût récurrent (serveurs, stockage, IA), donc un paiement unique serait une promesse intenable.',
            ],
            [
                'group' => 'product',
                'q' => 'Est-ce que je peux écrire hors connexion ?',
                'a' => 'Oui, toujours, et c’est le mode normal. Nacre écrit d’abord sur ton téléphone puis synchronise quand il peut. Tu n’attends jamais le réseau pour commencer une phrase.',
            ],
            [
                'group' => 'product',
                'q' => 'Est-ce qu’il y a des streaks et des récompenses ?',
                'a' => 'Il y a une trace de ta régularité, discrète. Il n’y a ni points, ni niveaux, ni badges, ni classement, et il n’y en aura pas : Nacre structure pour donner du sens, il ne récompense pas pour faire revenir.',
            ],
            [
                'group' => 'product',
                'q' => 'Puis-je importer mon journal depuis une autre app ?',
                'a' => 'Pas encore. L’import depuis les autres applications de journal est prévu, mais il n’existe pas aujourd’hui — mieux vaut le savoir avant de télécharger si tu as déjà des années ailleurs.',
            ],
            [
                'group' => 'product',
                'q' => 'Qui développe Nacre ?',
                'a' => 'Un développeur indépendant, en France, sans investisseurs. C’est ce qui explique le rythme, et aussi pourquoi il n’y a personne à qui vendre tes données.',
            ],
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | À propos
    |---------------------------------------------------------------------------
    */

    'about' => [
        'short' => 'À propos',

        'meta' => [
            'title' => 'À propos — qui fait Nacre, et pourquoi',
            'description' => 'Nacre est développé par un indépendant, en France, sans investisseurs. Pourquoi ce journal existe et ce qu’il refuse de devenir.',
        ],

        'hero' => [
            'eyebrow' => 'À propos',
            'title' => 'Une personne, en France, sans investisseurs.',
            'lead' => 'Nacre est développé et édité par :publisher, en solo. Pas d’équipe, pas de levée de fonds, pas de pression pour faire croître un chiffre chaque trimestre.',
        ],

        'story' => [
            'title' => 'D’où ça vient',
            'body' => [
                'J’ai essayé de tenir un journal plusieurs fois, avec plusieurs applications. À chaque fois, ça a duré quelques semaines. Le problème n’était pas d’écrire : c’était qu’il ne se passait rien après. Une liste de dates ne se relit pas.',
                'Ce qui manquait, c’était de pouvoir suivre un fil. Ce que j’avais écrit sur une décision précise, sur quelqu’un en particulier, était éparpillé sur des mois et introuvable au moment où j’en aurais eu besoin. Nacre est né de ce seul manque : pouvoir ouvrir une quête ou une personne et retrouver tout ce qui s’y rapporte, dans l’ordre.',
                'Le vocabulaire — quête principale, quête secondaire — vient de la façon dont ma génération parle déjà de sa vie. Il est là parce qu’il décrit exactement ce que c’est, pas pour rendre l’app amusante. C’est pour ça qu’il n’y a ni points, ni niveaux, ni badges : le jour où écrire dans son journal rapporte des récompenses, on n’écrit plus pour soi.',
            ],
        ],

        'principles' => [
            'title' => 'Les règles que je m’impose',
            'items' => [
                [
                    'title' => 'Zéro friction',
                    'body' => 'Jamais plus de deux secondes entre l’envie d’écrire et la première ligne. Une humeur seule est une entrée valide.',
                ],
                [
                    'title' => 'Une grille, pas un jeu',
                    'body' => 'Les quêtes servent à penser, pas à marquer des points. Toute suggestion qui dérive vers la gamification est refusée.',
                ],
                [
                    'title' => 'Tes données sont à toi',
                    'body' => 'Export gratuit, complet, à tout moment. Partir doit être facile.',
                ],
                [
                    'title' => 'Android à égalité avec iOS',
                    'body' => 'Les mêmes fonctions des deux côtés. Pas de plateforme de seconde classe.',
                ],
                [
                    'title' => 'L’IA propose, elle n’impose pas',
                    'body' => 'Toute fonction d’IA est optionnelle et désactivable, et éteinte au départ.',
                ],
                [
                    'title' => 'Un prix honnête',
                    'body' => 'Pas de relance agressive, pas de dark pattern, pas de suivi publicitaire entre applications.',
                ],
            ],
        ],

        'contact' => [
            'title' => 'Écrire',
            'body' => 'Une question, un bug, un désaccord : la même adresse, et c’est moi qui réponds.',
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | Presse
    |---------------------------------------------------------------------------
    */

    'press' => [
        'short' => 'Presse',

        'meta' => [
            'title' => 'Kit presse — Nacre',
            'description' => 'Description, faits, visuels et contact pour parler de Nacre, l’application de journal intime où la vie se relit comme une histoire.',
        ],

        'hero' => [
            'eyebrow' => 'Presse',
            'title' => 'Kit presse',
            'lead' => 'Tout ce qu’il faut pour écrire sur Nacre sans avoir à demander. Si quelque chose manque, écrivez-moi et je l’ajoute.',
        ],

        'boilerplate' => [
            'title' => 'Description courte',
            'short_label' => 'Une phrase',
            'short' => 'Nacre est une application de journal intime pour iOS et Android où chaque page se rattache à ce que l’on traverse et aux gens qui comptent, pour pouvoir relire un fil entier plutôt qu’une suite de dates.',
            'long_label' => 'Un paragraphe',
            'long' => 'Nacre est une application de journal intime pour iPhone, iPad et Android. On y écrit comme dans n’importe quel journal — texte, photos, notes vocales, lieu, humeur — mais chaque page peut être rattachée d’un tap à une « quête » (un projet, une relation, une transition que l’on traverse) et aux personnes qui reviennent dans sa vie. Ouvrir une quête ou une personne redonne alors toutes les pages qui s’y rapportent, dans l’ordre : c’est la relecture par fil, ce qu’un journal chronologique ne permet pas. Le vocabulaire narratif est volontairement conceptuel — il n’y a ni points, ni niveaux, ni badges. L’application fonctionne entièrement hors ligne et sans compte, ne contient aucun traceur, et sa couche d’écriture assistée par IA est désactivée par défaut. Nacre est développé par un indépendant, en France.',
        ],

        'facts' => [
            'title' => 'Les faits',
            'rows' => [
                ['label' => 'Nom', 'value' => 'Nacre'],
                ['label' => 'Catégorie', 'value' => 'Journal intime, style de vie'],
                ['label' => 'Plateformes', 'value' => 'iPhone et iPad ; Android en test fermé'],
                ['label' => 'Langues', 'value' => 'Français et anglais'],
                ['label' => 'Prix', 'value' => 'Gratuit. Nacre Plus en option : :monthly par mois ou :annual par an'],
                ['label' => 'Éditeur', 'value' => ':publisher, entrepreneur individuel (France)'],
            ],
        ],

        'assets' => [
            'title' => 'Visuels',
            'lead' => 'Libres d’usage dans un article ou une vidéo qui parle de Nacre. Merci de ne pas les recadrer ni les recolorer.',
            'icon' => 'Icône de l’application',
            'icon_note' => 'PNG, 1024 × 1024',
            'og' => 'Visuel de partage',
            'og_note' => 'PNG, 1200 × 630',
            'screens' => 'Captures d’écran',
            'screens_note' => 'Interface en anglais ; captures en français sur demande.',
            'download' => 'Télécharger',
        ],

        'contact' => [
            'title' => 'Contact',
            'body' => 'Une demande d’interview, un accès anticipé, une capture précise : écrivez directement, il n’y a pas d’agence entre nous.',
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | Télécharger
    |---------------------------------------------------------------------------
    */

    'download' => [
        'short' => 'Télécharger',

        'meta' => [
            'title' => 'Télécharger Nacre — journal intime pour iPhone et Android',
            'description' => 'Nacre est gratuit sur l’App Store. La version Android est en test fermé. Aucun compte requis pour commencer à écrire.',
        ],

        'hero' => [
            'eyebrow' => 'Télécharger',
            'title' => 'Commence ce soir.',
            'lead' => 'Gratuit, sans compte, sans carte bancaire. Une humeur et deux phrases suffisent pour une première page.',
        ],

        'ios' => [
            'title' => 'iPhone et iPad',
            'body' => 'Disponible sur l’App Store, à partir d’:ios_min_os.',
        ],

        'android' => [
            'title' => 'Android',
            'body' => 'En test fermé, et publié dès la fin de la période de test imposée par Google. Les fonctions sont les mêmes que sur iOS.',
        ],

        'next' => [
            'title' => 'Ce qui se passe après l’installation',
            'steps' => [
                [
                    'title' => 'Tu écris.',
                    'body' => 'Pas de configuration, pas de questionnaire. L’app s’ouvre sur une page vide et une question du jour.',
                ],
                [
                    'title' => 'Tu nommes ce que tu traverses.',
                    'body' => 'Une quête principale, quand tu sauras laquelle. Elle peut attendre une semaine — ou un mois.',
                ],
                [
                    'title' => 'Tu relis.',
                    'body' => 'Au bout de quelques semaines, « Ce jour-là » et la constellation commencent à te répondre.',
                ],
            ],
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | Libellés des pages légales (servies par LegalController)
    |---------------------------------------------------------------------------
    */

    'legal' => [
        'privacy' => [
            'short' => 'Confidentialité',
            'meta' => [
                'title' => 'Politique de confidentialité — Nacre',
                'description' => 'Ce que Nacre collecte, ce qu’il ne collecte pas, où vivent tes données, et comment les récupérer ou les supprimer.',
            ],
        ],
        'terms' => [
            'short' => 'Conditions',
            'meta' => [
                'title' => 'Conditions générales d’utilisation — Nacre',
                'description' => 'Les conditions d’utilisation de Nacre et de l’abonnement Nacre Plus : compte, contenus, résiliation, responsabilités.',
            ],
        ],
        'support' => [
            'short' => 'Aide',
            'meta' => [
                'title' => 'Aide et support — Nacre',
                'description' => 'Une question, un bug, une suggestion ? Comment nous joindre, et les réponses aux demandes les plus fréquentes.',
            ],
        ],
        'notice' => [
            'short' => 'Mentions légales',
            'meta' => [
                'title' => 'Mentions légales — Nacre',
                'description' => 'Identification de l’éditeur de Nacre et de son hébergeur, et coordonnées de contact, comme l’exige la loi française.',
            ],
        ],
    ],

];
