<?php

return [
    /*
     | Custom index mappings for language-aware inflection search.
     */
    'indices' => [
        'settings' => [
            'default' => [
                // Required when ngram min_gram/max_gram span more than the cluster default (usually 1).
                'index' => [
                    'max_ngram_diff' => 12,
                ],
                'analysis' => [
                    'filter' => [
                        'ti_ngram_filter' => [
                            'type' => 'ngram',
                            'min_gram' => 3,
                            'max_gram' => 12,
                        ],
                    ],
                    'analyzer' => [
                        'ti_ngram_index' => [
                            'tokenizer' => 'standard',
                            'filter' => [
                                'lowercase',
                                'ti_ngram_filter',
                            ],
                        ],
                        'ti_ngram_search' => [
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
                            'ru_ngram' => [
                                'type' => 'text',
                                'analyzer' => 'ti_ngram_index',
                                'search_analyzer' => 'ti_ngram_search',
                            ],
                            'en_ngram' => [
                                'type' => 'text',
                                'analyzer' => 'ti_ngram_index',
                                'search_analyzer' => 'ti_ngram_search',
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
                            'ru_ngram' => [
                                'type' => 'text',
                                'analyzer' => 'ti_ngram_index',
                                'search_analyzer' => 'ti_ngram_search',
                            ],
                            'en_ngram' => [
                                'type' => 'text',
                                'analyzer' => 'ti_ngram_index',
                                'search_analyzer' => 'ti_ngram_search',
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
                            'ru_ngram' => [
                                'type' => 'text',
                                'analyzer' => 'ti_ngram_index',
                                'search_analyzer' => 'ti_ngram_search',
                            ],
                            'en_ngram' => [
                                'type' => 'text',
                                'analyzer' => 'ti_ngram_index',
                                'search_analyzer' => 'ti_ngram_search',
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
                            'ru_ngram' => [
                                'type' => 'text',
                                'analyzer' => 'ti_ngram_index',
                                'search_analyzer' => 'ti_ngram_search',
                            ],
                            'en_ngram' => [
                                'type' => 'text',
                                'analyzer' => 'ti_ngram_index',
                                'search_analyzer' => 'ti_ngram_search',
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
                            'ru_ngram' => [
                                'type' => 'text',
                                'analyzer' => 'ti_ngram_index',
                                'search_analyzer' => 'ti_ngram_search',
                            ],
                            'en_ngram' => [
                                'type' => 'text',
                                'analyzer' => 'ti_ngram_index',
                                'search_analyzer' => 'ti_ngram_search',
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
                            'ru_ngram' => [
                                'type' => 'text',
                                'analyzer' => 'ti_ngram_index',
                                'search_analyzer' => 'ti_ngram_search',
                            ],
                            'en_ngram' => [
                                'type' => 'text',
                                'analyzer' => 'ti_ngram_index',
                                'search_analyzer' => 'ti_ngram_search',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];

