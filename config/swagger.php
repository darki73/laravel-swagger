<?php

return [

    /**
     * API Title
     */
    'title'                     =>  env('APP_NAME', 'Application API Documentation'),

    /**
     * API Description
     */
    'description'               =>  env('APP_DESCRIPTION', 'Documentation for the Application API'),

    /**
     * API Version
     */
    'version'                   =>  env('APP_VERSION', '1.0.0'),

    /**
     * API Host
     */
    'host'                      =>  env('APP_URL'),

    /**
     * API Path
     */
    'path'                      =>  env('SWAGGER_PATH', '/documentation'),

    /**
     * API Storage Path
     */
    'storage'                   =>  env('SWAGGER_STORAGE', storage_path('swagger')),

    /**
     * API Views Path
     */
    'views'                     =>  base_path('resources/views/vendor/swagger'),

    /**
     * Servers list
     * ['https://server.name.org'] OR [ [ "url" => "", "description" => "" ] ]
     */
    'servers'                   =>  [
//        'http://localhost',
//        [
//            'url'           =>  'http://localhost',
//            'description'   =>  'Demo Server'
//        ]
    ],

    /**
     * Always generate schema when accessing Swagger UI
     */
    'generated'                 =>  false,

    /**
     * Append additional data to ALL routes
     */
    'append'                    =>  [
        'responses'             =>  [
            '401'               =>  [
                'description'   =>  '(Unauthorized) Invalid or missing Access Token'
            ]
        ]
    ],

    /**
     * List of ignored items (routes and methods)
     * They will be hidden from the documentation
     */
    'ignored'                   =>  [
        'methods'               =>  [
            'head'
        ],
        'routes'                =>  [
//            'passport.authorizations.authorize',
//            'passport.authorizations.approve',
//            'passport.authorizations.deny',
//            'passport.token',
//            'passport.tokens.index',
//            'passport.tokens.destroy',
//            'passport.token.refresh',
//            'passport.clients.index',
//            'passport.clients.store',
//            'passport.clients.update',
//            'passport.clients.destroy',
//            'passport.scopes.index',
//            'passport.personal.tokens.index',
//            'passport.personal.tokens.store',
//            'passport.personal.tokens.destroy',


            '/_ignition/health-check',
            '/_ignition/execute-solution',
            '/_ignition/share-report',
            '/_ignition/scripts/{script}',
            '/_ignition/styles/{style}',
            env('SWAGGER_PATH', '/documentation'),
            env('SWAGGER_PATH', '/documentation') . '/content'
        ]
    ],

    /**
     * Tags
     */
    'tags'                      =>  [
//        [
//            'name'          =>  'Authentication',
//            'description'   =>  'Routes related to Authentication'
//        ],
    ],

    /**
     * Parsing strategy
     */
    'parse'                     =>  [
        'docBlock'              =>  true,
        'security'              =>  true,
    ],

    /**
     * Authentication flow values
     */
    'authentication_flow'       =>  [
        'OAuth2'                =>  'authorizationCode',
//        'bearerAuth'            =>  'http',
    ],

];
