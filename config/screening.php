<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Component versions
    |--------------------------------------------------------------------------
    | Stamped onto every screening result so a result's meaning is reproducible
    | even after parser/matcher/dictionary logic changes. Bump on behaviour change.
    */
    'versions' => [
        'parser' => '1',
        'matcher' => '1',
        'screening' => '1',
        'synonym' => '1',
    ],

    // Hard cap on text extracted from a CV (abuse + memory guard).
    'max_text_chars' => 100000,

    // Hard cap on the redacted summary persisted on the result row.
    'summary_max_chars' => 2000,

    /*
    |--------------------------------------------------------------------------
    | Sensitive terms (fairness)
    |--------------------------------------------------------------------------
    | Screening criteria may never key off these (label/keywords/synonyms).
    | Enforced at model + service level, not only in the Filament form.
    */
    'sensitive_terms' => [
        'agama', 'religion',
        'jenis kelamin', 'gender',
        'umur', 'usia', 'age',
        'tanggal lahir', 'date of birth', 'tempat tanggal lahir', 'tempat lahir',
        'status menikah', 'status pernikahan', 'marital status',
        'nik', 'ktp',
        'race', 'ras', 'ethnicity', 'suku',
        'foto', 'photo',
        'penampilan', 'appearance',
    ],

    /*
    |--------------------------------------------------------------------------
    | Synonym dictionary (directional)
    |--------------------------------------------------------------------------
    | direction:
    |   bidirectional        — source <-> target (either implies the other)
    |   source_implies_target— source -> target only (target does NOT imply source)
    | Non-links (frontend !-> react, javascript !-> java) are simply absent.
    */
    'synonyms' => [
        'version' => '1',
        'entries' => [
            ['source_terms' => ['akuntansi'], 'target_term' => 'accounting', 'direction' => 'bidirectional'],
            ['source_terms' => ['pajak'], 'target_term' => 'tax', 'direction' => 'bidirectional'],
            ['source_terms' => ['react', 'reactjs', 'react.js'], 'target_term' => 'frontend', 'direction' => 'source_implies_target'],
            ['source_terms' => ['next.js', 'nextjs'], 'target_term' => 'frontend', 'direction' => 'source_implies_target'],
            ['source_terms' => ['vue', 'vue.js', 'vuejs'], 'target_term' => 'frontend', 'direction' => 'source_implies_target'],
            ['source_terms' => ['laravel'], 'target_term' => 'php', 'direction' => 'source_implies_target'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Keyword-stuffing thresholds
    |--------------------------------------------------------------------------
    | density_*  — matched-keyword tokens as a % of total tokens.
    | single_repeat_* — repeats of any one keyword token.
    | warning  → flag + recommendation may not exceed `review`.
    | critical → recommendation forced to `needs_manual_review`.
    */
    'keyword_stuffing' => [
        'density_warning' => 8.0,
        'density_critical' => 15.0,
        'single_repeat_warning' => 8,
        'single_repeat_critical' => 15,
        // Density is meaningless on a tiny document; only judge it once the CV has at
        // least this many tokens. Single-keyword repeats are judged at any length.
        'min_tokens_for_density' => 30,
    ],

];
