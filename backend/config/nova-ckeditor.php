<?php

return [
    /*
     |--------------------------------------------------------------------------
     | Video Model
     |--------------------------------------------------------------------------
     |
     | Specifies the path of your video model
     |
     */

    'video-model' => 'App\Models\Video',

    /*
     |--------------------------------------------------------------------------
     | Image Model
     |--------------------------------------------------------------------------
     |
     | Specifies the path of your image model
     |
     */

    'image-model' => 'App\Models\Image',


    /*
     |--------------------------------------------------------------------------
     | Max Memory
     |--------------------------------------------------------------------------
     |
     | Max Memory (php.ini override) for Intervention Image Resizing
     | @docs https://www.php.net/manual/en/ini.core.php#ini.memory-limit
     |
     */

    'memory' => '256M',

    /*
     |--------------------------------------------------------------------------
     | Image Quality
     |--------------------------------------------------------------------------
     |
     | Max Intervention Image Output Quality
     | before Image Optimizer is run.
     | @docs http://image.intervention.io/api/save
     |
     */

    'max-quality' => 75,

    /*
     |--------------------------------------------------------------------------
     | Image Dimensions
     |--------------------------------------------------------------------------
     |
     | Intervention Image Max Dimensions
     | @docs http://image.intervention.io/api/resize
     |
     */

    'max-width'  => 1920,
    'max-height' => 1080,

    /*
     |--------------------------------------------------------------------------
     | Naming Method of Images
     |--------------------------------------------------------------------------
     |
     | Available methods: hash-file, real-file-name, unique-real-file-name
     |
     */

    'image-naming-method' => 'hash-file',

    /*
     |--------------------------------------------------------------------------
     | Image Processing Library
     |--------------------------------------------------------------------------
     |
     |
     | Available methods: GD, IMAGICK
     |
    */

    'image-processing-library' => \Mostafaznv\NovaCkEditor\Enums\ImageLibrary::GD,

    /*
     |--------------------------------------------------------------------------
     | Naming Method of Audio
     |--------------------------------------------------------------------------
     |
     | Available methods: hash-file, real-file-name, unique-real-file-name
     |
     */

    'audio-naming-method' => 'hash-file',

    /*
     |--------------------------------------------------------------------------
     | Naming Method of File
     |--------------------------------------------------------------------------
     |
     | Available methods: hash-file, real-file-name, unique-real-file-name
     |
     */

    'file-naming-method' => 'hash-file',

    /*
     |--------------------------------------------------------------------------
     | Toolbar
     |--------------------------------------------------------------------------
     |
     | Customize Settings
     |
     */

    'toolbars' => [
        'default' => 'toolbar-theins',

        'toolbar-theins' => [
            'height' => 400,
            'content-lang' => 'en',
            'force-paste-as-plain-text' => false,
            'alert-before-unsaved-changes' => true,
            'should-not-group-when-full' => false,

            'browser' => [
                'image' => false,
                'video' => false,
                'audio' => false,
                'file'  => false
            ],

            'snippets' => [
                ['name' => 'Image', 'html' => 'ckeditor.image'],
                ['name' => 'Media', 'html' => 'ckeditor.media'],
                ['name' => 'Table', 'html' => 'ckeditor.table']
            ],

            'items' => [
                'undo',
                'redo',
                '|',
                'heading',
                'bold',
                'italic',
                'underline',
                'strikethrough',
                'subscript',
                'superscript',
                '|',
                'link',
                'blockQuote',
                'bulletedList',
                'numberedList',
                'alignment',
                '|',
                'code',
                '|',
                'removeFormat',
                'showBlocks',
                'sourceEditing',
            ],

            'options' => [
                'headings' => [
                    [
                        'model' => 'paragraph',
                        'title' => 'Обычный текст',
                        'class' => 'ck-heading_paragraph',
                    ],
                    [
                        'model' => 'heading3',
                        'view'  => 'h3',
                        'title' => 'Главный заголовок',
                        'class' => 'ck-heading_heading3',
                    ],
                    [
                        'model' => 'heading4',
                        'view'  => 'h4',
                        'title' => 'Заголовок',
                        'class' => 'ck-heading_heading4',
                    ],
                    [
                        'model' => 'heading5',
                        'view'  => 'h5',
                        'title' => 'Подзаголовок',
                        'class' => 'ck-heading_heading5',
                    ],
                ],
            ],
        ],
     
        'toolbar-1' => [
            'height' => 400,

            'content-lang' => 'en',

            'force-paste-as-plain-text' => false,

            'alert-before-unsaved-changes' => true,

            'ui-language' => [
                'name'   => 'en',

                /**
                 * Example 1: asset('js/ckeditor-fa.js')
                 * Example 2: 'https://cdn.ckeditor.com/ckeditor5/34.0.0/decoupled-document/translations/fa.js'
                 */
                'script' => null
            ],

            'text-part-language' => [
                ['title' => 'Farsi', 'languageCode' => 'fa'],
                ['title' => 'English', 'languageCode' => 'en']
            ],

            /*
             * General HTML Support
             *
             * @see https://ckeditor.com/docs/ckeditor5/latest/features/html/general-html-support.html#configuration
             */

            'html-support' => [
                'allow' => [
                    [
                        'name'    => 'div',
                        'classes' => true,
                    ],
                    [
                        'name' => '/^(div|section|article)$/'
                    ]
                ],

                'disallow' => []
            ],

            'should-not-group-when-full' => false,

            'browser' => [
                'image' => true,
                'video' => true,
                'audio' => true,
                'file'  => true
            ],

            'image' => [
                /*
                 * Insert images directly into the editor by pasting or dragging.
                 */

                'insert' => [
                    'types' => ['gif', 'png', 'jpg', 'jpeg', 'webp'],
                    'size'  => 1500 // kb, nullable
                ]
            ],

            'snippets' => [
                ['name' => 'Image', 'html' => 'ckeditor.image'],
                ['name' => 'Media', 'html' => 'ckeditor.media'],
                ['name' => 'Table', 'html' => 'ckeditor.table']
            ],

            'items' => [
                'textPartLanguage',
                'heading',
                '|',
                'fontSize',
                'fontFamily',
                'fontColor',
                'fontBackgroundColor',
                '|',
                'insertTable',
                'mediaBrowser',
                'mediaEmbed',
                'link',
                'resizeImage',
                'insertImage',
                '|',
                'bold',
                'italic',
                'alignment',
                'elementIdAttributes',
                'horizontalLine',
                'subscript',
                'superscript',
                'underline',
                'strikethrough',
                'code',
                'removeFormat',
                '|',
                'outdent',
                'indent',
                '|',
                'codeBlock',
                'blockQuote',
                'emoji',
                'bulletedList',
                'numberedList',
                '|',
                'snippetBrowser',
                'htmlEmbed',
                '|',
                'undo',
                'redo',
                '|',
                'showBlocks',
                'sourceEditing'
            ],

            'options' => [
                'headings' => [
                    [
                        'model' => 'paragraph',
                        'title' => 'Paragraph',
                        'class' => 'ck-heading_paragraph',
                    ],
                    [
                        'model' => 'heading1',
                        'view'  => 'h1',
                        'title' => 'Heading 1',
                        'class' => 'ck-heading_heading1',
                    ],
                    [
                        'model' => 'heading2',
                        'view'  => 'h2',
                        'title' => 'Heading 2',
                        'class' => 'ck-heading_heading2',
                    ],
                    [
                        'model' => 'heading3',
                        'view'  => 'h3',
                        'title' => 'Heading 3',
                        'class' => 'ck-heading_heading3',
                    ],
                    [
                        'model' => 'heading4',
                        'view'  => 'h4',
                        'title' => 'Heading 4',
                        'class' => 'ck-heading_heading4',
                    ],
                    [
                        'model' => 'heading5',
                        'view'  => 'h5',
                        'title' => 'Heading 5',
                        'class' => 'ck-heading_heading5',
                    ],
                    [
                        'model' => 'heading6',
                        'view'  => 'h6',
                        'title' => 'Heading 6',
                        'class' => 'ck-heading_heading6',
                    ]
                ],

                'fontFamily' => [
                    'supportAllValues' => false,
                    'options'          => [
                        'default',
                        'Arial, Helvetica, sans-serif',
                        'Courier New, Courier, monospace',
                        'Georgia, serif',
                        'Lucida Sans Unicode, Lucida Grande, sans-serif',
                        'Tahoma, Geneva, sans-serif',
                        'Times New Roman, Times, serif',
                        'Trebuchet MS, Helvetica, sans-serif',
                        'Verdana, Geneva, sans-serif'
                    ]
                ],

                'fontSize' => [
                    'options' => [
                        'tiny',
                        'small',
                        'default',
                        'big',
                        'huge'
                    ]
                ],

                'fontColor' => [
                    'columns' => 5,
                    'colors'  => [
                        [
                            'color' => 'hsl(0, 0%, 0%)',
                            'label' => 'Black'
                        ],
                        [
                            'color' => 'hsl(0, 0%, 30%)',
                            'label' => 'Dim grey'
                        ],
                        [
                            'color' => 'hsl(0, 0%, 60%)',
                            'label' => 'Grey'
                        ],
                        [
                            'color' => 'hsl(0, 0%, 90%)',
                            'label' => 'Light grey'
                        ],
                        [
                            'color'     => 'hsl(0, 0%, 100%)',
                            'label'     => 'White',
                            'hasBorder' => true
                        ],
                        [
                            'color' => 'hsl(0, 75%, 60%)',
                            'label' => 'Red'
                        ],
                        [
                            'color' => 'hsl(30, 75%, 60%)',
                            'label' => 'Orange'
                        ],
                        [
                            'color' => 'hsl(60, 75%, 60%)',
                            'label' => 'Yellow'
                        ],
                        [
                            'color' => 'hsl(90, 75%, 60%)',
                            'label' => 'Light green'
                        ],
                        [
                            'color' => 'hsl(120, 75%, 60%)',
                            'label' => 'Green'
                        ],
                        [
                            'color' => 'hsl(150, 75%, 60%)',
                            'label' => 'Aquamarine'
                        ],
                        [
                            'color' => 'hsl(180, 75%, 60%)',
                            'label' => 'Turquoise'
                        ],
                        [
                            'color' => 'hsl(210, 75%, 60%)',
                            'label' => 'Light blue'
                        ],
                        [
                            'color' => 'hsl(240, 75%, 60%)',
                            'label' => 'Blue'
                        ],
                        [
                            'color' => 'hsl(270, 75%, 60%)',
                            'label' => 'Purple'
                        ]
                    ]
                ],

                'fontBackgroundColor' => [
                    'columns' => 5,
                    'colors'  => [
                        [
                            'color' => 'hsl(0, 0%, 0%)',
                            'label' => 'Black'
                        ],
                        [
                            'color' => 'hsl(0, 0%, 30%)',
                            'label' => 'Dim grey'
                        ],
                        [
                            'color' => 'hsl(0, 0%, 60%)',
                            'label' => 'Grey'
                        ],
                        [
                            'color' => 'hsl(0, 0%, 90%)',
                            'label' => 'Light grey'
                        ],
                        [
                            'color'     => 'hsl(0, 0%, 100%)',
                            'label'     => 'White',
                            'hasBorder' => true
                        ],
                        [
                            'color' => 'hsl(0, 75%, 60%)',
                            'label' => 'Red'
                        ],
                        [
                            'color' => 'hsl(30, 75%, 60%)',
                            'label' => 'Orange'
                        ],
                        [
                            'color' => 'hsl(60, 75%, 60%)',
                            'label' => 'Yellow'
                        ],
                        [
                            'color' => 'hsl(90, 75%, 60%)',
                            'label' => 'Light green'
                        ],
                        [
                            'color' => 'hsl(120, 75%, 60%)',
                            'label' => 'Green'
                        ],
                        [
                            'color' => 'hsl(150, 75%, 60%)',
                            'label' => 'Aquamarine'
                        ],
                        [
                            'color' => 'hsl(180, 75%, 60%)',
                            'label' => 'Turquoise'
                        ],
                        [
                            'color' => 'hsl(210, 75%, 60%)',
                            'label' => 'Light blue'
                        ],
                        [
                            'color' => 'hsl(240, 75%, 60%)',
                            'label' => 'Blue'
                        ],
                        [
                            'color' => 'hsl(270, 75%, 60%)',
                            'label' => 'Purple'
                        ]
                    ]
                ],

                'image' => [
                    'insert' => [
                        'integrations' => [
                            'upload',
                            'assetManager',
                            'url'
                        ],
                    ],

                    'upload' => [
                        'types' => ['gif', 'png', 'jpg', 'jpeg', 'webp']
                    ],

                    'resizeUnit' => '%',

                    'resizeOptions' => [
                        [
                            'name'  => 'resizeImage:original',
                            'value' => null,
                            'label' => 'Original',
                            'icon'  => 'original'
                        ],
                        [
                            'name'  => 'resizeImage:25',
                            'value' => '25',
                            'label' => 'Small (25%)',
                            'icon'  => 'small'
                        ],
                        [
                            'name'  => 'resizeImage:50',
                            'value' => '50',
                            'label' => 'Medium',
                            'icon'  => 'medium'
                        ],
                        [
                            'name'  => 'resizeImage:75',
                            'value' => '75',
                            'label' => 'Large (75%)',
                            'icon'  => 'large'
                        ]
                    ],

                    'toolbar' => [
                        'mediaBrowser',
                        '|',
                        'imageStyle:full',
                        'imageStyle:alignLeft',
                        'imageStyle:alignCenter',
                        'imageStyle:alignRight',
                        '|',
                        'imageTextAlternative',
                        'toggleImageCaption',
                        '|',
                        'imageStyle:block',
                        'imageStyle:side',
                        '|',
                        'linkImage',
                        '|',
                        'resizeImage:25',
                        'resizeImage:50',
                        'resizeImage:75',
                        'resizeImage:original',
                        '|',
                        'insertImage',
                    ],

                    'styles' => [
                        'full',
                        'alignLeft',
                        'alignCenter',
                        'alignRight',
                    ]
                ],

                'mediaEmbed' => []
            ],
        ]
    ],
];
