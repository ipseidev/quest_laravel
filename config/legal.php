<?php

/**
 * Publisher identification and the infrastructure facts the public legal pages
 * are required to disclose.
 *
 * Single source of truth: the legal notice, the privacy policy, the terms and
 * the shared layout footer all read from here, so an identification detail
 * cannot drift between pages and a change (new host, new VAT status) is a
 * one-line edit.
 *
 * The registry values below mirror the INSEE SIRENE record for SIREN
 * 843 299 751 and must stay in sync with it — a mismatch between a published
 * legal notice and the public register is itself a compliance problem.
 */
return [

    /*
    |---------------------------------------------------------------------------
    | Publisher (éditeur)
    |---------------------------------------------------------------------------
    |
    | Nacre is published by a French sole trader (entrepreneur individuel), not
    | a company: there is no share capital and no corporate name — the legal
    | name IS the natural person's name.
    |
    */

    'publisher' => [
        'name' => 'Nicolas Serra',
        'siren' => '843 299 751',
        'siret' => '843 299 751 00019',
        'ape' => '62.01Z',
        'vat' => 'FR70843299751',
        'publication_director' => 'Nicolas Serra',
        'country' => 'France',

        // LCEN art. 1-1, I, 1° (the mentions-légales list, moved out of the old
        // art. 6-III by loi SREN n° 2024-449 of 21 May 2024) requires BOTH a
        // domicile and a telephone number from a natural person editing a
        // service professionally.
        //
        // Neither is published today: the address by the publisher's explicit
        // decision, the phone because none is dedicated to the business yet.
        // The pages offer both on request instead — a documented gap, not an
        // oversight. Fill either value here and the corresponding line appears
        // on the legal notice automatically; a dedicated VoIP number is the
        // cheap way to close the phone half without exposing a personal line.
        'address' => null,
        'phone' => null,
    ],

    'contact_email' => 'contact@affiniteam.io',

    'app_url' => 'https://thequesting.app',

    /*
    |---------------------------------------------------------------------------
    | Hosting
    |---------------------------------------------------------------------------
    |
    | The API and the PostgreSQL database run on Laravel Cloud, on AWS
    | infrastructure in eu-west-3 (Paris) — i.e. inside the EU, which is what
    | the privacy policy asserts. Binary attachments (photos, voice notes) live
    | in a separate Cloudflare R2 bucket. Changing either MUST be reflected here
    | and re-read in the privacy policy's storage-location wording.
    |
    */

    'hosting' => [
        'provider' => 'Laravel Cloud (Laravel Holdings, Inc.)',
        'provider_url' => 'https://laravel.cloud',
        // LCEN art. 1-1, I, 4° wants the host's name, address AND telephone.
        // Set these once obtained from Laravel Cloud; the legal notice renders
        // each line only when its value is present.
        'provider_address' => null,
        'provider_phone' => null,
        'infrastructure' => 'Amazon Web Services EMEA SARL',
        'infrastructure_address' => '38 avenue John F. Kennedy, L-1855 Luxembourg',
        'region' => 'eu-west-3',
        'region_label' => 'Paris, France',
        'object_storage' => 'Cloudflare R2 (Cloudflare, Inc.)',
        // The R2 bucket runs with region=auto (see .env.production.example), so
        // its storage location is NOT pinned to the EU. The privacy policy says
        // so explicitly. Setting a Cloudflare jurisdiction restriction to the EU
        // is what would let that wording be tightened.
        'object_storage_eu_pinned' => false,
    ],

    /*
    |---------------------------------------------------------------------------
    | Last-updated dates
    |---------------------------------------------------------------------------
    |
    | Displayed at the top of each page. Bump the relevant entry whenever the
    | substance of a page changes — the date is what a user (or a regulator)
    | relies on to know which version they agreed to. Kept per page and per
    | language as ready-to-display strings so no locale formatting is needed.
    |
    */

    'last_updated' => [
        'privacy' => ['fr' => '27 juillet 2026', 'en' => '27 July 2026'],
        'terms' => ['fr' => '27 juillet 2026', 'en' => '27 July 2026'],
        'notice' => ['fr' => '27 juillet 2026', 'en' => '27 July 2026'],
    ],

];
