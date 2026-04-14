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

    'video-model' => 'App\Models\Ckeditor\Video',

    /*
     |--------------------------------------------------------------------------
     | Image Model
     |--------------------------------------------------------------------------
     |
     | Specifies the path of your image model
     |
     */

    'image-model' => 'App\Models\Ckeditor\Image',


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
     | Max Intervention Image Resizing Quality
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
            'strip-inline-styles-on-paste' => true,
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
                'removeFormat',
                '|',
                'terminPicker',
                'hintPicker',
                'outlineHeading',
                '|',
                'heading',
                '|',
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
                'specialCharacters',
                'replaceQuotesWithGuillemets',
                'replaceHyphenWithDash',
                '|',
                'showBlocks',
                'sourceEditing',
            ],

            'html-support' => [
                'allow' => [
                    [
                        'name'       => 'span',
                        'classes'    => ['termin'],
                        'attributes' => ['data-id' => true, 'data-description' => true],
                    ],
                    [
                        'name'    => 'h3',
                        'classes' => ['outline-heading'],
                    ],
                ],
                'disallow' => [],
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
                        'title' => 'Заголовок 3',
                        'class' => 'ck-heading_heading1',
                    ],
                    [
                        'model' => 'heading4',
                        'view'  => 'h4',
                        'title' => 'Заголовок 4',
                        'class' => 'ck-heading_heading2',
                    ],
                    [
                        'model' => 'heading5',
                        'view'  => 'h5',
                        'title' => 'Заголовок 5',
                        'class' => 'ck-heading_heading3',
                    ],
                ],
            ],
        ],

        'toolbar-theins-small' => [
            'height' => 200,
            'content-lang' => 'en',
            'force-paste-as-plain-text' => false,
            'strip-inline-styles-on-paste' => true,
            'alert-before-unsaved-changes' => true,
            'should-not-group-when-full' => false,

            'browser' => [
                'image' => false,
                'video' => false,
                'audio' => false,
                'file'  => false
            ],

            'snippets' => [],

            'items' => [
                'undo',
                'redo',
                '|',
                'removeFormat',
                '|',
                'bold',
                'italic',
                'underline',
                'strikethrough',
                'link',
                '|',
                'specialCharacters',
                'replaceQuotesWithGuillemets',
                'replaceHyphenWithDash',
                '|',
                'showBlocks',
                'sourceEditing',
            ],

            'options' => [],
        ],

        /** Minimal: bold, italic, link, image (e.g. term description in Nova). */
        'toolbar-theins-mini' => [
            'height' => 160,
            'content-lang' => 'en',
            'force-paste-as-plain-text' => false,
            'strip-inline-styles-on-paste' => true,
            'alert-before-unsaved-changes' => true,
            'should-not-group-when-full' => false,

            'browser' => [
                'image' => false,
                'video' => false,
                'audio' => false,
                'file'  => false,
            ],

            'snippets' => [],

            'items' => [
                'bold',
                'italic',
                'link',
                'insertImage',
            ],

            'options' => [
                'image' => [
                    'insert' => [
                        'integrations' => ['url'],
                    ],
                    /** No file upload: images only via URL (paste/drag of files ignored). */
                    'upload' => [
                        'types' => [],
                    ],
                    'resizeUnit' => '%',
                    'resizeOptions' => [
                        [
                            'name' => 'resizeImage:original',
                            'value' => null,
                            'label' => 'Original',
                            'icon' => 'original',
                        ],
                        [
                            'name' => 'resizeImage:25',
                            'value' => '25',
                            'label' => 'Small (25%)',
                            'icon' => 'small',
                        ],
                        [
                            'name' => 'resizeImage:50',
                            'value' => '50',
                            'label' => 'Medium (50%)',
                            'icon' => 'medium',
                        ],
                        [
                            'name' => 'resizeImage:75',
                            'value' => '75',
                            'label' => 'Large (75%)',
                            'icon' => 'large',
                        ],
                    ],
                    'toolbar' => [
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
                    ],
                    'styles' => [
                        'full',
                        'alignLeft',
                        'alignCenter',
                        'alignRight',
                    ],
                ],
            ],
        ]
    ],
];
