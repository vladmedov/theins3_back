<?php

return [
    /*
     | Custom index mappings for language-aware inflection search.
     */
    'indices' => [
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
                        ],
                    ],
                ],
            ],
        ],
    ],
];

