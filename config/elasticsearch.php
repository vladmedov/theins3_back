<?php

return [
    /*
     | Custom index mappings for language-aware inflection search.
     */
    'indices' => [
        'settings' => [
            'default' => [
                'analysis' => [
                    'analyzer' => [
                        'ti_exact_text' => [
                            'tokenizer' => 'standard',
                            'filter' => [
                                'lowercase',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'mappings' => [
            'default' => [
                'properties' => [
                    'id' => [
                        'type' => 'keyword',
                    ],
                    'title' => [
                        'type' => 'text',
                        'fields' => [
                            'ru' => [
                                'type' => 'text',
                                'analyzer' => 'russian',
                                'search_analyzer' => 'russian',
                            ],
                            'en' => [
                                'type' => 'text',
                                'analyzer' => 'english',
                                'search_analyzer' => 'english',
                            ],
                            'exact' => [
                                'type' => 'text',
                                'analyzer' => 'ti_exact_text',
                                'search_analyzer' => 'ti_exact_text',
                            ],
                        ],
                    ],
                    'lead' => [
                        'type' => 'text',
                        'fields' => [
                            'ru' => [
                                'type' => 'text',
                                'analyzer' => 'russian',
                                'search_analyzer' => 'russian',
                            ],
                            'en' => [
                                'type' => 'text',
                                'analyzer' => 'english',
                                'search_analyzer' => 'english',
                            ],
                            'exact' => [
                                'type' => 'text',
                                'analyzer' => 'ti_exact_text',
                                'search_analyzer' => 'ti_exact_text',
                            ],
                        ],
                    ],
                    'content' => [
                        'type' => 'text',
                        'fields' => [
                            'ru' => [
                                'type' => 'text',
                                'analyzer' => 'russian',
                                'search_analyzer' => 'russian',
                            ],
                            'en' => [
                                'type' => 'text',
                                'analyzer' => 'english',
                                'search_analyzer' => 'english',
                            ],
                            'exact' => [
                                'type' => 'text',
                                'analyzer' => 'ti_exact_text',
                                'search_analyzer' => 'ti_exact_text',
                            ],
                        ],
                    ],
                    'authors' => [
                        'type' => 'text',
                        'fields' => [
                            'ru' => [
                                'type' => 'text',
                                'analyzer' => 'russian',
                                'search_analyzer' => 'russian',
                            ],
                            'en' => [
                                'type' => 'text',
                                'analyzer' => 'english',
                                'search_analyzer' => 'english',
                            ],
                            'exact' => [
                                'type' => 'text',
                                'analyzer' => 'ti_exact_text',
                                'search_analyzer' => 'ti_exact_text',
                            ],
                        ],
                    ],
                    'columnist' => [
                        'type' => 'text',
                        'fields' => [
                            'ru' => [
                                'type' => 'text',
                                'analyzer' => 'russian',
                                'search_analyzer' => 'russian',
                            ],
                            'en' => [
                                'type' => 'text',
                                'analyzer' => 'english',
                                'search_analyzer' => 'english',
                            ],
                            'exact' => [
                                'type' => 'text',
                                'analyzer' => 'ti_exact_text',
                                'search_analyzer' => 'ti_exact_text',
                            ],
                        ],
                    ],
                    'tags' => [
                        'type' => 'text',
                        'fields' => [
                            'ru' => [
                                'type' => 'text',
                                'analyzer' => 'russian',
                                'search_analyzer' => 'russian',
                            ],
                            'en' => [
                                'type' => 'text',
                                'analyzer' => 'english',
                                'search_analyzer' => 'english',
                            ],
                            'exact' => [
                                'type' => 'text',
                                'analyzer' => 'ti_exact_text',
                                'search_analyzer' => 'ti_exact_text',
                            ],
                        ],
                    ],
                    'title_lemma' => [
                        'type' => 'text',
                        'analyzer' => 'ti_exact_text',
                        'search_analyzer' => 'ti_exact_text',
                    ],
                    'lead_lemma' => [
                        'type' => 'text',
                        'analyzer' => 'ti_exact_text',
                        'search_analyzer' => 'ti_exact_text',
                    ],
                    'content_lemma' => [
                        'type' => 'text',
                        'analyzer' => 'ti_exact_text',
                        'search_analyzer' => 'ti_exact_text',
                    ],
                    'authors_lemma' => [
                        'type' => 'text',
                        'analyzer' => 'ti_exact_text',
                        'search_analyzer' => 'ti_exact_text',
                    ],
                    'columnist_lemma' => [
                        'type' => 'text',
                        'analyzer' => 'ti_exact_text',
                        'search_analyzer' => 'ti_exact_text',
                    ],
                    'tags_lemma' => [
                        'type' => 'text',
                        'analyzer' => 'ti_exact_text',
                        'search_analyzer' => 'ti_exact_text',
                    ],
                ],
            ],
        ],
    ],
];
