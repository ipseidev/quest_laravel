<?php

/**
 * Public site copy — English.
 *
 * Structurally identical to `lang/fr/marketing.php`: the test suite walks both
 * files and fails on any key present in one and missing from the other, because
 * a missing key renders as a raw translation path to a real visitor.
 *
 * The same three rules apply. No hard-coded figures (they arrive as `:monthly`,
 * `:quota`, `:themes_total` from `config/site.php` via `App\Support\Copy`). Never
 * a claim of end-to-end encryption — synced data is encrypted at rest with a
 * server-readable key, which is not the same thing. And no gaming language: the
 * quest vocabulary is conceptual, there are no points, levels or badges.
 *
 * Written as English, not translated from the French — same facts, its own
 * rhythm.
 */
return [

    'common' => [
        'skip_to_content' => 'Skip to content',
        'category' => 'Journal',
        'og_alt' => 'Nacre — a journal where your life becomes a story',
        'app_description' => 'A private journal for iOS and Android: write your days, link them to what you are living through and the people who matter, then reread your story one thread at a time.',
        'download_ios' => 'Download on the App Store',
        'download_android' => 'Get it on Google Play',
        'soon_ios' => 'Coming to the App Store',
        'soon_android' => 'Coming to Google Play',
        'previous_name_note' => 'The App Store listing is still titled :store_name — the new name ships with the next update.',
        'learn_more' => 'Learn more',
        'free_note' => 'Free, and no account required: the app works entirely on your phone.',
    ],

    'nav' => [
        'label' => 'Main navigation',
        'home' => 'Home',
        'menu' => 'Open menu',
        'download' => 'Download',
        'switch_language' => 'View this page in',
    ],

    'footer' => [
        'tagline' => 'A journal where your life becomes a story. Made and published in France.',
        'product' => 'Product',
        'company' => 'Nacre',
        'legal' => 'Legal',
        'publisher' => 'Published by :publisher, sole trader — SIREN :siren. Contact:',
    ],

    /*
    |---------------------------------------------------------------------------
    | Home
    |---------------------------------------------------------------------------
    */

    'home' => [
        'short' => 'Home',

        'meta' => [
            'title' => 'Nacre — the private journal where your life becomes a story',
            'description' => 'A journal app for iPhone and Android. Write your days, link them to your quests and the people who matter, then reread your story by thread. Free.',
        ],

        'hero' => [
            'eyebrow' => 'Private journal · iOS and Android',
            'title' => 'Your life is already a story.',
            'lead' => 'Nacre is a journal where every page can attach to what you are living through and the people who matter. A year later you are not rereading a pile of dates — you are rereading a thread.',
            'shot_alt' => 'A journal entry in Nacre: two photos, a mood, a linked person, and the day’s writing.',
        ],

        'problem' => [
            'eyebrow' => 'Why it never sticks',
            'title' => 'You have tried keeping a journal before.',
            'lead' => 'And you probably stopped. Not for lack of discipline — because an ordinary journal gives nothing back.',
            'points' => [
                [
                    'title' => 'You write into a void.',
                    'body' => 'Three weeks of entries, then nothing. Nobody rereads a list of dates. Not even the person who wrote it.',
                ],
                [
                    'title' => 'The threads get lost.',
                    'body' => 'What you wrote about that project, that relationship, that decision: scattered across eleven months, unfindable the moment you actually need it.',
                ],
                [
                    'title' => 'The blank page wins.',
                    'body' => 'You are supposed to have something to say. Some nights you have a mood and two sentences — and that should be enough.',
                ],
            ],
        ],

        'pillars' => [
            'eyebrow' => 'How it works',
            'title' => 'Four things, and nothing more.',
            'lead' => 'Nacre lays a narrative grid over an ordinary journal. The grid is conceptual, never visual: no points, no levels, no badges.',
            'items' => [
                [
                    'key' => 'pages',
                    'title' => 'Pages',
                    'body' => 'Write the way you would in any journal: text, photos, a voice note, a place, a mood. The title comes from your first line, and everything saves while you type.',
                    'shot' => 'pages',
                    'alt' => 'The chronological thread of pages in Nacre, with linked quests and people under each entry.',
                ],
                [
                    'key' => 'features.quests',
                    'title' => 'Quests',
                    'body' => 'One main quest — the big question of your year. Side quests — a project, a complicated relationship, a move. Attach an entry with one tap, or not at all.',
                    'shot' => 'quests',
                    'alt' => 'The Quests screen in Nacre, showing a main quest and side quests.',
                ],
                [
                    'key' => 'features.people',
                    'title' => 'People',
                    'body' => 'Your sister, your therapist, the colleague it is tense with. Open someone and relive, in order, every page they appeared in.',
                    'shot' => 'person',
                    'alt' => 'A person’s page in Nacre, followed by every entry they appear in.',
                ],
                [
                    'key' => 'features.constellation',
                    'title' => 'Constellation',
                    'body' => 'Your pages, quests and people drawn as a sky. Drag through time and watch it build itself, day by day.',
                    'shot' => 'constellation',
                    'alt' => 'Nacre’s Constellation view: entries and quests linked into a map of stars.',
                ],
            ],
        ],

        'replay' => [
            'eyebrow' => 'What a chronological journal cannot do',
            'title' => 'Reread by thread, not by date.',
            'lead' => 'This is the whole difference. Open “Find what I want from this work” and you get the eleven pages that punctuated it, in order, across eight months. Open “Priya” and you see every time she appeared.',
            'body' => 'And it cannot be reconstructed later. After three years, what you have built — your pages crossed with your quests and your people — exists nowhere else and cannot be imported back.',
            'shot_alt' => 'A quest opened in Nacre, showing the run of entries that passed through it.',
        ],

        'friction' => [
            'eyebrow' => 'Zero friction',
            'title' => 'Two seconds between wanting to write and the first line.',
            'lead' => 'A journal only survives if opening it costs nothing.',
            'points' => [
                'A mood and one sentence is enough. That is a valid entry.',
                'Photo, camera, voice note, place: in the editor’s bar, not buried in a menu.',
                'Saves automatically while you type. Nothing to confirm, nothing to lose.',
                'A prompt of the day, for the nights the blank page wins.',
                '“On this day” brings back your older pages — with the quests that were active and the people who were around.',
                'Calendar, full-text search, a :retention-day trash.',
            ],
        ],

        'not' => [
            'eyebrow' => 'To be clear',
            'title' => 'What Nacre is not.',
            'lead' => 'Better said up front. It will save you a download.',
            'items' => [
                [
                    'title' => 'Not a game.',
                    'body' => 'We say “quest” because that is exactly what it is, not to be playful. No XP, no levels, no badges, no leaderboard. Streaks exist, quietly: presence, not a score.',
                ],
                [
                    'title' => 'Not a social network.',
                    'body' => 'No sharing, no public profile, no feed, no friends to add. Nobody reads your pages.',
                ],
                [
                    'title' => 'Not a therapy tool.',
                    'body' => 'Nacre accompanies reflection. It diagnoses nothing and replaces no one.',
                ],
                [
                    'title' => 'Not a productivity app.',
                    'body' => 'No habits to tick, no targets to hit. You have nothing to perform here.',
                ],
            ],
        ],

        'privacy' => [
            'eyebrow' => 'Privacy',
            'title' => 'Your journal stays yours.',
            'lead' => 'What is true, stated precisely — including where it costs us.',
            'points' => [
                'The account is optional. Without one, everything stays on your phone.',
                'No analytics, no third-party trackers, no ads.',
                'Locked behind Face ID, Touch ID or a passcode.',
                'Free export to :exports, any time, without asking anyone.',
                'AI-written Chapters are off by default. Until you turn them on, not a word of your journal goes to an AI service.',
            ],
            'link' => 'How your pages are protected, in detail',
            'shot_alt' => 'Nacre’s lock screen, asking to authenticate before the journal opens.',
        ],

        'nacre' => [
            'title' => 'Why “Nacre”.',
            'body' => 'Nacre — mother-of-pearl — is not manufactured. It is deposited, layer over layer, year after year, until it becomes something else. That is exactly what a journal you actually keep does: the first month is worth almost nothing, and the third year cannot be replaced.',
        ],

        'pricing' => [
            'eyebrow' => 'Pricing',
            'title' => 'Free to write. Paid to have it everywhere.',
            'lead' => 'Writing, linking, rereading, searching and exporting are free, with no page limit. Nacre Plus adds sync across your devices, a Chapter every month, and all :themes_total themes — :monthly a month, or :annual a year.',
            'link' => 'See what each plan includes',
        ],

        'faq' => [
            [
                'q' => 'Is Nacre free?',
                'a' => 'Yes. Writing, creating quests and people, rereading by thread, searching and exporting are all free, with no page limit. Nacre Plus is an optional subscription at :monthly a month or :annual a year that adds sync across devices, a Chapter every month, all :themes_total themes, and unlimited backup of your photos and voice notes.',
            ],
            [
                'q' => 'Can I write without creating an account?',
                'a' => 'Yes, and that is the default. Nacre works entirely offline on your phone. An account only exists to back your pages up and pick them up on another device.',
            ],
            [
                'q' => 'Does an AI read my journal?',
                'a' => 'Not unless you ask it to. Chapters — the narratives Nacre writes from your journal — are off by default. If you turn them on, the text of your entries is sent to our AI provider, :ai_provider, to write the narrative. You can switch it off at any time.',
            ],
            [
                'q' => 'Can I get my data out?',
                'a' => 'Always, and without subscribing. Export to :exports lives in the settings, is free, and contains everything: your pages, your quests, your people, and the links between them.',
            ],
        ],

        'cta' => [
            'title' => 'Start tonight.',
            'lead' => 'A mood and two sentences is enough. In a year you will have a thread to reread.',
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | Features
    |---------------------------------------------------------------------------
    */

    'features' => [
        'short' => 'Features',

        'meta' => [
            'title' => 'Features — Nacre, a journal app that reads like a story',
            'description' => 'Pages, quests, people, constellation and Chapters: everything Nacre does, the journal app where your life rereads as a story.',
        ],

        'hero' => [
            'eyebrow' => 'The product',
            'title' => 'A journal, and a way to reread it.',
            'lead' => 'Nacre is still a journal: you write, that is all. What changes is what you can do with it a year later.',
        ],

        'basics' => [
            'title' => 'And everything you expect from a journal',
            'items' => [
                [
                    'title' => 'An editor that does not keep you waiting',
                    'body' => 'Rich text, attached photos, voice notes, place, mood, tags. Saves as you type, and the title comes from your first line.',
                ],
                [
                    'title' => 'Actually finding things',
                    'body' => 'Full-text search across the whole journal, a monthly calendar, “On this day” with the context of the time, and a :retention-day trash.',
                ],
                [
                    'title' => 'To your taste',
                    'body' => ':themes_total themes, :fonts typefaces, adjustable accent colour. The theme can follow your system.',
                ],
                [
                    'title' => 'Locked',
                    'body' => 'Face ID, Touch ID or a passcode, required when the app opens.',
                ],
                [
                    'title' => 'Your data leaves whenever you want',
                    'body' => 'Export to :exports. Free, complete, no conditions.',
                ],
                [
                    'title' => 'French and English',
                    'body' => 'The app is written in both, not machine-translated into either. iOS, iPad and Android.',
                ],
            ],
        ],

        'quests' => [
            'short' => 'Quests',
            'meta' => [
                'title' => 'Quests — give your journal a thread | Nacre',
                'description' => 'One main quest for the big question of your year, side quests for what you are living through. Link your pages and reread a whole thread in order.',
            ],
            'hero' => [
                'eyebrow' => 'Feature',
                'title' => 'Quests',
                'lead' => 'A quest is a thread running through you. Not a task, not a goal: what is actually at stake right now.',
                'shot_alt' => 'The Quests screen in Nacre, with a main quest and two side quests.',
            ],
            'points' => [
                [
                    'title' => 'One main quest at a time',
                    'body' => 'The big question of your year — “find what I want from this work”, “leave the city”. Only one active, because one is already a lot. The previous one is archived, never deleted.',
                ],
                [
                    'title' => 'As many side quests as your life has',
                    'body' => 'A project that has dragged since March, a complicated relationship, a transition. Each has a status — active, completed, archived — and a start date that can precede the day you created it.',
                ],
                [
                    'title' => 'Daily ones, if you want them',
                    'body' => 'A lighter category for small recurring arcs. Optional, switched on in the settings.',
                ],
                [
                    'title' => 'Linking stays optional',
                    'body' => 'One tap in the editor attaches the page to one or more quests. Attaching nothing is normal use, not an oversight: write first.',
                ],
                [
                    'title' => 'And above all: the rereading',
                    'body' => 'Open a quest and you get the run of pages that punctuated it, in order, with the people who appeared in them. That is where a journal starts being useful.',
                ],
            ],
        ],

        'people' => [
            'short' => 'People',
            'meta' => [
                'title' => 'People — relive every page they appear in | Nacre',
                'description' => 'Add the people who recur in your life and find, in order, every journal entry they appeared in. Photo and note optional.',
            ],
            'hero' => [
                'eyebrow' => 'Feature',
                'title' => 'People',
                'lead' => 'The people who move through your story. The ones who keep coming back, page after page, sometimes without you noticing.',
                'shot_alt' => 'A person’s page in Nacre, followed by the timeline of entries they appear in.',
            ],
            'points' => [
                [
                    'title' => 'A name is enough',
                    'body' => 'A relationship and a note if you want, a photo if you want. Nothing is required but the name.',
                ],
                [
                    'title' => 'Mention them as you write',
                    'body' => 'One tap in the editor links the page to a person. Their name then appears in the text, in their colour.',
                ],
                [
                    'title' => 'Open someone, relive all of it',
                    'body' => 'Their page lists every entry they appeared in, newest first, with each day’s mood. It is usually more telling than you remember writing.',
                ],
                [
                    'title' => 'They show up in your quests too',
                    'body' => 'A quest surfaces the people who passed through it. A move is not only a place: it is who was there.',
                ],
            ],
        ],

        'chapters' => [
            'short' => 'Chapters',
            'meta' => [
                'title' => 'Chapters — your journal rewritten as a narrative | Nacre',
                'description' => 'Nacre can reread your month and write its story. An optional AI layer, off by default: nothing leaves until you turn it on.',
            ],
            'hero' => [
                'eyebrow' => 'Optional feature',
                'title' => 'Chapters',
                'lead' => 'Once a month, Nacre rereads what you wrote and turns it into a narrative. Not a bulleted summary: a text, with a title, that tells your month.',
                'shot_alt' => 'The Chapters screen in Nacre, with a monthly chapter and the end of an arc.',
            ],
            'points' => [
                [
                    'title' => 'Four kinds of chapter',
                    'body' => 'The chapter of the month. The end of an arc, when a quest closes. Your year as a narrative. And “everything so far”, which takes in all of it.',
                ],
                [
                    'title' => 'Off by default',
                    'body' => 'This is the part that matters. On install, the AI layer is dark. Until you switch it on in the settings, not a word of your journal leaves the app for an AI service.',
                ],
                [
                    'title' => 'What happens if you switch it on',
                    'body' => 'The text of your entries is sent to our AI provider, :ai_provider, which writes the narrative. You are told this in the settings, at the moment you enable it, not in a footnote.',
                ],
                [
                    'title' => 'One Chapter free, to see',
                    'body' => 'Once your journal has a little in it, you can have your first Chapter written for free. After that, Nacre Plus sends one every month.',
                ],
                [
                    'title' => 'You can switch it off any time',
                    'body' => 'One toggle, in the settings. Chapters already written stay readable.',
                ],
            ],
        ],

        'constellation' => [
            'short' => 'Constellation',
            'meta' => [
                'title' => 'Constellation — see the shape of your story | Nacre',
                'description' => 'Your pages, quests and people drawn as a sky. Drag through time and watch years of journal build themselves, day by day.',
            ],
            'hero' => [
                'eyebrow' => 'Feature',
                'title' => 'Constellation',
                'lead' => 'The shape of your story, seen from far away. Every page is a star, every quest and every person a node pulling them together.',
                'shot_alt' => 'Nacre’s Constellation view: entries linked to their quests and people against a night sky.',
            ],
            'points' => [
                [
                    'title' => 'Tap a node, see its links',
                    'body' => 'Its connections light up. Tap again to open the quest or the person, and fall back into the pages.',
                ],
                [
                    'title' => 'Run time backwards',
                    'body' => 'The bar at the bottom replays your journal from the start. Stars appear in the order you wrote them.',
                ],
                [
                    'title' => 'It looks like nobody else',
                    'body' => 'It is the one screen in Nacre no one else can have. After two years, no two skies are alike.',
                ],
                [
                    'title' => 'And it is free',
                    'body' => 'Constellation is not behind the subscription. It is the app’s signature; it should belong to everyone.',
                ],
            ],
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | Privacy (marketing page — the legal policy lives elsewhere)
    |---------------------------------------------------------------------------
    */

    'privacy' => [
        'short' => 'Privacy',

        'meta' => [
            'title' => 'Privacy — what actually happens to your journal | Nacre',
            'description' => 'Optional account, no trackers, biometric lock, free export, AI off by default. And what we do not claim: this is not end-to-end encryption.',
        ],

        'hero' => [
            'eyebrow' => 'Privacy',
            'title' => 'What we do with your journal. And what we do not claim.',
            'lead' => 'A private journal is only ever confided to someone specific. Here are the facts, including the ones that do not flatter us.',
            'shot_alt' => 'Nacre’s lock screen, asking to authenticate before the journal opens.',
        ],

        'promises' => [
            'title' => 'What is true',
            'items' => [
                [
                    'title' => 'The account is optional',
                    'body' => 'Nacre installs and runs entirely without an account. In that mode your pages never leave your phone: there is nothing to intercept because nothing is sent.',
                ],
                [
                    'title' => 'No trackers, no ads',
                    'body' => 'No analytics, no ad SDK, no third-party audience measurement, no data sold. Nacre has no economic interest in what you write — you pay for the app, not an advertiser.',
                ],
                [
                    'title' => 'Locked on the device',
                    'body' => 'Face ID, Touch ID or a passcode, required on open. Content never flashes on screen before the lock appears.',
                ],
                [
                    'title' => 'The AI starts switched off',
                    'body' => 'Chapters are the only feature that sends text anywhere, and they are off by default. If you enable them, your entries are passed to :ai_provider to write the narrative, and you can switch it off whenever you like.',
                ],
                [
                    'title' => 'Your data leaves freely',
                    'body' => 'Export to :exports from the settings: free, complete, links between pages, quests and people included. Leaving has to be easy, otherwise staying means nothing.',
                ],
                [
                    'title' => 'Deleting means deleting',
                    'body' => 'The trash holds for :retention days, then erases permanently — attachments included. Deleting your account removes the content attached to it.',
                ],
            ],
        ],

        'honest' => [
            'title' => 'What we do not claim',
            'lead' => 'Plenty of journal apps write “end-to-end encrypted” without it being true. Here is our situation, exactly.',
            'items' => [
                [
                    'title' => 'This is not end-to-end encryption',
                    'body' => 'If you enable sync, the text of your pages is encrypted at rest on the server — but with a key the server can read. So technically the operator could access it. That is a deliberate trade-off: it makes account recovery and Chapters possible. Real end-to-end is the V1 goal, and we will not announce it before we have it.',
                ],
                [
                    'title' => 'The local database is not separately encrypted',
                    'body' => 'On your phone your pages are protected by the operating system’s sandbox and by the biometric lock, not by an extra layer of encryption. On an unlocked, compromised device, that would not be enough.',
                ],
                [
                    'title' => 'We need a minimum about you',
                    'body' => 'If you create an account we store what is needed to identify you and sign you back in. No more. And if you use Nacre without an account, we hold nothing at all.',
                ],
            ],
        ],

        'legal_links' => [
            'title' => 'The documents that bind',
            'lead' => 'This page explains. Those are the ones with contractual weight.',
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | Pricing
    |---------------------------------------------------------------------------
    */

    'pricing' => [
        'short' => 'Pricing',

        'meta' => [
            'title' => 'Pricing — Nacre is free, Nacre Plus is optional',
            'description' => 'Writing, linking and exporting are free, with no page limit. Nacre Plus: :monthly a month or :annual a year for cross-device sync and a Chapter every month.',
        ],

        'hero' => [
            'eyebrow' => 'Pricing',
            'title' => 'Free to write. Paid to have it everywhere.',
            'lead' => 'The full journal is free, with no page limit. Nacre Plus pays for the parts that cost money every month: storage, servers, and the AI that writes your Chapters.',
        ],

        'free' => [
            'name' => 'Nacre',
            'price' => 'Free',
            'price_note' => 'Forever, no card required',
            'summary' => 'The whole journal, on one device.',
            'items' => [
                'Unlimited pages — text, photos, voice notes, place, mood',
                'Unlimited quests and people, and rereading by thread',
                'Constellation, in full',
                'Full-text search, calendar, “On this day”',
                ':themes_free themes, :fonts typefaces, accent colour',
                'Face ID / Touch ID lock',
                'Export to :exports',
                'Unlimited cloud backup of your writing',
                ':quota of photos and voice notes backed up',
                'One Chapter free, to see what it does',
            ],
            'cta' => 'Download Nacre',
        ],

        'plus' => [
            'name' => 'Nacre Plus',
            'monthly_label' => 'per month',
            'annual_label' => 'per year',
            'annual_badge' => 'that is :annual_per_month a month — :saving% less',
            'summary' => 'Everything on the left, plus:',
            'items' => [
                'Your pages on all your devices, in both directions',
                'A new Chapter every month, and at the end of every arc',
                'All :themes_total themes, including sepia, forest, ocean and sunset',
                'Photos and voice notes backed up with no volume limit',
                'Backing an independent developer with no investors to repay',
            ],
            'cta' => 'Subscribe from the app',
            'cta_note' => 'The subscription is taken out inside Nacre, from the settings. Cancel from your App Store or Google Play account at any time.',
        ],

        'why' => [
            'title' => 'Why a subscription and not a one-off purchase',
            'body' => 'Because the paid part costs money every month: storing your photos, running sync, and paying for the AI that writes your Chapters. A single payment for a recurring service is a promise you eventually fail to keep. The journal itself stays free — and if you stop paying, you keep everything and carry on writing.',
        ],

        'faq' => [
            [
                'q' => 'What do I lose if I stop paying?',
                'a' => 'Nothing you have written. Your pages, quests, people and already-generated chapters stay on your device and stay exportable. You simply return to the free tier: one synced device, :themes_free themes, and no new Chapter each month.',
            ],
            [
                'q' => 'Why is cross-device sync paid?',
                'a' => 'Because it is the part with a real recurring cost: servers and storage, every month, for every user. Backing up your text is free and unlimited — you will never lose your journal because you did not pay.',
            ],
            [
                'q' => 'How do I cancel?',
                'a' => 'From the subscriptions section of your App Store or Google Play account, in two taps, without writing to us. Apple and Google handle billing; we never see your card.',
            ],
            [
                'q' => 'Is there a free trial?',
                'a' => 'The entire journal is free with no time limit, which is better than a trial: you can write for months before wondering whether you want Plus. And the first Chapter is free, so you can judge it on the evidence.',
            ],
            [
                'q' => 'Can I pay once and be done?',
                'a' => 'No, deliberately. There are two plans, monthly and yearly. A permanent purchase for a service that costs money every month always ends badly, for you as much as for us.',
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
            'title' => 'Frequently asked questions — Nacre, a journal for iOS and Android',
            'description' => 'Price, privacy, AI, export, sync, platforms: answers to the questions we get asked about Nacre.',
        ],

        'hero' => [
            'eyebrow' => 'FAQ',
            'title' => 'Frequently asked questions',
            'lead' => 'With honest answers, including when they do not favour us.',
        ],

        'groups' => [
            'basics' => 'The basics',
            'privacy' => 'Privacy and data',
            'pricing' => 'Price and subscription',
            'product' => 'Living with it',
        ],

        'faq' => [
            [
                'group' => 'basics',
                'q' => 'What is Nacre, in one sentence?',
                'a' => 'A journal app for iPhone, iPad and Android where every page can attach to what you are living through (quests) and the people who matter (people), so you can reread a whole thread instead of a run of dates.',
            ],
            [
                'group' => 'basics',
                'q' => 'Which platforms is Nacre on?',
                'a' => 'iPhone and iPad. The Android version is in closed testing and follows, with the same features — parity between the two platforms is a rule of the project, not an intention.',
            ],
            [
                'group' => 'basics',
                'q' => 'Is there a web or desktop version?',
                'a' => 'No. Nacre is built for the phone, because that is where a journal actually gets written — in the evening, on the train, two minutes before sleep.',
            ],
            [
                'group' => 'basics',
                'q' => 'Do I have to fill in quests and people to use it?',
                'a' => 'No. You can use Nacre as a perfectly ordinary journal and never link anything. The links are there the day you want them, and they can be added long after the fact just as well as while writing.',
            ],
            [
                'group' => 'privacy',
                'q' => 'Can anyone read my journal?',
                'a' => 'Without an account, no: everything stays on your phone. With sync enabled, your pages are encrypted at rest on the server, but with a key the server can read — so technically the operator could access them. That is not end-to-end encryption and we do not write that anywhere. Real end-to-end is planned; it is not here yet.',
            ],
            [
                'group' => 'privacy',
                'q' => 'Does an AI read what I write?',
                'a' => 'Only if you enable Chapters, which are off by default. In that case the text of your entries is sent to :ai_provider to write the narrative. No other feature sends your text to an AI service, and you can disable it at any time.',
            ],
            [
                'group' => 'privacy',
                'q' => 'Are there trackers or ads?',
                'a' => 'Neither. No third-party audience measurement, no ad SDK, no data sold. A technical crash report exists so bugs can be fixed; it does not contain the content of your pages.',
            ],
            [
                'group' => 'privacy',
                'q' => 'How do I take everything and leave?',
                'a' => 'Settings, export, pick :exports. It is free, complete, and includes the links between your pages, quests and people. Deleting your account removes the associated content on the server.',
            ],
            [
                'group' => 'pricing',
                'q' => 'What does it cost?',
                'a' => 'The journal is free, with no page limit. Nacre Plus costs :monthly a month or :annual a year (that is :annual_per_month a month, :saving% less) and adds cross-device sync, a Chapter every month, all :themes_total themes, and unlimited media backup.',
            ],
            [
                'group' => 'pricing',
                'q' => 'What exactly stays free?',
                'a' => 'All the writing and all the rereading: unlimited pages, quests, people, constellation, search, calendar, “On this day”, export, biometric lock, :themes_free themes, unlimited backup of your text, and :quota of photos and voice notes.',
            ],
            [
                'group' => 'pricing',
                'q' => 'Is there a lifetime purchase?',
                'a' => 'No. Two plans only, monthly and yearly. The paid part has a recurring cost (servers, storage, AI), so a one-off payment would be a promise we could not keep.',
            ],
            [
                'group' => 'product',
                'q' => 'Can I write offline?',
                'a' => 'Always, and that is the normal mode. Nacre writes to your phone first and syncs when it can. You never wait for a network to start a sentence.',
            ],
            [
                'group' => 'product',
                'q' => 'Are there streaks and rewards?',
                'a' => 'There is a quiet trace of your regularity. There are no points, levels, badges or leaderboards, and there will not be: Nacre structures to give meaning, it does not reward you into coming back.',
            ],
            [
                'group' => 'product',
                'q' => 'Can I import my journal from another app?',
                'a' => 'Not yet. Importing from other journal apps is planned but does not exist today — worth knowing before you download if you already have years elsewhere.',
            ],
            [
                'group' => 'product',
                'q' => 'Who makes Nacre?',
                'a' => 'One independent developer, in France, with no investors. That explains the pace, and also why there is nobody to sell your data to.',
            ],
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | About
    |---------------------------------------------------------------------------
    */

    'about' => [
        'short' => 'About',

        'meta' => [
            'title' => 'About — who makes Nacre, and why',
            'description' => 'Nacre is built by one independent developer in France, with no investors. Why this journal exists and what it refuses to become.',
        ],

        'hero' => [
            'eyebrow' => 'About',
            'title' => 'One person, in France, with no investors.',
            'lead' => 'Nacre is built and published by :publisher, solo. No team, no funding round, no pressure to grow a number every quarter.',
        ],

        'story' => [
            'title' => 'Where it comes from',
            'body' => [
                'I tried keeping a journal several times, in several apps. Each time it lasted a few weeks. The problem was not the writing: it was that nothing happened afterwards. A list of dates does not get reread.',
                'What was missing was being able to follow a thread. What I had written about one specific decision, or about one particular person, was scattered across months and unfindable the moment I would have needed it. Nacre came out of that single gap: being able to open a quest or a person and get everything that belongs to it, in order.',
                'The vocabulary — main quest, side quest — comes from the way my generation already talks about its own life. It is there because it describes exactly what these things are, not to make the app fun. Which is why there are no points, no levels and no badges: the day writing in your journal earns rewards, you stop writing for yourself.',
            ],
        ],

        'principles' => [
            'title' => 'The rules I hold myself to',
            'items' => [
                [
                    'title' => 'Zero friction',
                    'body' => 'Never more than two seconds between wanting to write and the first line. A mood on its own is a valid entry.',
                ],
                [
                    'title' => 'A grid, not a game',
                    'body' => 'Quests are for thinking, not for scoring. Any suggestion that drifts toward gamification gets refused.',
                ],
                [
                    'title' => 'Your data is yours',
                    'body' => 'Free, complete export, any time. Leaving has to be easy.',
                ],
                [
                    'title' => 'Android equal to iOS',
                    'body' => 'The same features on both sides. No second-class platform.',
                ],
                [
                    'title' => 'AI offers, never imposes',
                    'body' => 'Every AI feature is optional and disableable, and starts switched off.',
                ],
                [
                    'title' => 'An honest price',
                    'body' => 'No aggressive nagging, no dark patterns, no cross-app ad tracking.',
                ],
            ],
        ],

        'contact' => [
            'title' => 'Get in touch',
            'body' => 'A question, a bug, a disagreement: same address, and I am the one who answers.',
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | Press
    |---------------------------------------------------------------------------
    */

    'press' => [
        'short' => 'Press',

        'meta' => [
            'title' => 'Press kit — Nacre',
            'description' => 'Description, facts, assets and contact for writing about Nacre, the journal app where life rereads as a story.',
        ],

        'hero' => [
            'eyebrow' => 'Press',
            'title' => 'Press kit',
            'lead' => 'Everything you need to write about Nacre without having to ask. If something is missing, email me and I will add it.',
        ],

        'boilerplate' => [
            'title' => 'Short description',
            'short_label' => 'One sentence',
            'short' => 'Nacre is a journal app for iOS and Android where every page can attach to what you are living through and the people who matter, so you can reread a whole thread rather than a run of dates.',
            'long_label' => 'One paragraph',
            'long' => 'Nacre is a journal app for iPhone, iPad and Android. You write in it as you would in any journal — text, photos, voice notes, place, mood — but every page can be attached with one tap to a “quest” (a project, a relationship, a transition you are living through) and to the people who recur in your life. Opening a quest or a person then returns every page that belongs to it, in order: rereading by thread, which a chronological journal cannot do. The narrative vocabulary is deliberately conceptual — there are no points, levels or badges. The app works entirely offline and without an account, contains no trackers, and its AI writing layer is off by default. Nacre is built by an independent developer in France.',
        ],

        'facts' => [
            'title' => 'The facts',
            'rows' => [
                ['label' => 'Name', 'value' => 'Nacre'],
                ['label' => 'Category', 'value' => 'Journal, lifestyle'],
                ['label' => 'Platforms', 'value' => 'iPhone and iPad; Android in closed testing'],
                ['label' => 'Languages', 'value' => 'French and English'],
                ['label' => 'Price', 'value' => 'Free. Nacre Plus optional: :monthly a month or :annual a year'],
                ['label' => 'Publisher', 'value' => ':publisher, sole trader (France)'],
            ],
        ],

        'assets' => [
            'title' => 'Assets',
            'lead' => 'Free to use in an article or video about Nacre. Please do not crop or recolour them.',
            'icon' => 'App icon',
            'icon_note' => 'PNG, 1024 × 1024',
            'og' => 'Share image',
            'og_note' => 'PNG, 1200 × 630',
            'screens' => 'Screenshots',
            'screens_note' => 'Interface in English; French captures on request.',
            'download' => 'Download',
        ],

        'contact' => [
            'title' => 'Contact',
            'body' => 'An interview, early access, a specific capture: email directly, there is no agency in between.',
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | Download
    |---------------------------------------------------------------------------
    */

    'download' => [
        'short' => 'Download',

        'meta' => [
            'title' => 'Download Nacre — a private journal for iPhone and Android',
            'description' => 'Nacre is free on the App Store. The Android version is in closed testing. No account needed to start writing.',
        ],

        'hero' => [
            'eyebrow' => 'Download',
            'title' => 'Start tonight.',
            'lead' => 'Free, no account, no card. A mood and two sentences is enough for a first page.',
        ],

        'ios' => [
            'title' => 'iPhone and iPad',
            'body' => 'On the App Store, from :ios_min_os onwards.',
        ],

        'android' => [
            'title' => 'Android',
            'body' => 'In closed testing, and published as soon as Google’s required testing period ends. The features are the same as on iOS.',
        ],

        'next' => [
            'title' => 'What happens after you install it',
            'steps' => [
                [
                    'title' => 'You write.',
                    'body' => 'No setup, no questionnaire. The app opens on a blank page and a prompt of the day.',
                ],
                [
                    'title' => 'You name what you are living through.',
                    'body' => 'A main quest, when you know which one. It can wait a week — or a month.',
                ],
                [
                    'title' => 'You reread.',
                    'body' => 'After a few weeks, “On this day” and the constellation start answering back.',
                ],
            ],
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | Labels for the legal pages (served by LegalController)
    |---------------------------------------------------------------------------
    */

    'legal' => [
        'privacy' => [
            'short' => 'Privacy',
            'meta' => [
                'title' => 'Privacy policy — Nacre',
                'description' => 'What Nacre collects, what it does not, where your data lives, and how to get it out or delete it.',
            ],
        ],
        'terms' => [
            'short' => 'Terms',
            'meta' => [
                'title' => 'Terms of service — Nacre',
                'description' => 'The terms covering Nacre and the Nacre Plus subscription: your account, your content, cancellation, liability.',
            ],
        ],
        'support' => [
            'short' => 'Support',
            'meta' => [
                'title' => 'Help and support — Nacre',
                'description' => 'A question, a bug, a suggestion? How to reach us, and answers to the requests we get most often.',
            ],
        ],
        'notice' => [
            'short' => 'Legal notice',
            'meta' => [
                'title' => 'Legal notice — Nacre',
                'description' => 'Identification of Nacre’s publisher and host, plus contact details, as required by French law.',
            ],
        ],
    ],

];
