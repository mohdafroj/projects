<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    'reporter_audio_disk' => env('REPORTER_AUDIO_DISK', 's2s_input_audio'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        'vani_audio' => [
            'driver' => 's3',
            'key' => env('VANI_MINIO_ACCESS_KEY', env('AWS_ACCESS_KEY_ID')),
            'secret' => env('VANI_MINIO_SECRET_KEY', env('AWS_SECRET_ACCESS_KEY')),
            'region' => env('VANI_MINIO_REGION', 'us-east-1'),
            'bucket' => env('VANI_MINIO_AUDIO_BUCKET', 'vani-audio-raw-rs'),
            'endpoint' => env('VANI_MINIO_ENDPOINT', env('AWS_ENDPOINT')),
            'use_path_style_endpoint' => true,
            'throw' => false,
            'report' => false,
        ],

        // Local fallback for S2S input audio so chunks always land on disk
        // even when MinIO/S3 is unreachable. Used by SarvamSpeechPipeline /
        // SpeechToSpeechPageController::storeAudio when REPORTER_AUDIO_DISK
        // defaults to s2s_input_audio.
        's2s_input_audio' => [
            'driver' => 'local',
            'root' => storage_path('app/s2s-input'),
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
