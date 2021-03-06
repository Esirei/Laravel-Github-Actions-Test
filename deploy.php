<?php
namespace Deployer;

require 'recipe/laravel.php';
require 'recipe/rsync.php';

// Project name
set('application', 'actions_test_scp');

// Project repository
set('repository', 'git@github.com:Esirei/Laravel-Github-Actions-Test.git');

// Speeds up deployments
set('ssh_multiplexing', true);

// If your project isn't in the root, you'll need to change this.
set('rsync_src', function () {
    return __DIR__;
});

set('writable_mode', 'chmod');
set('writable_chmod_mode', '0775');
// We don't want it to be recursive else deployment will fail once it encounters www-data owned/created files.
set('writable_chmod_recursive', false);
set('http_user', 'www-data');
// we don't want to disable sudo password request on ssh_user so
// chown or chgrp will manually be granted to www-data on the server after the 1st deployment.

// Configuring the rsync exclusions.
// You'll want to exclude anything that you don't want on the production server.
add('rsync', [
    'exclude' => [
        '.env',
        '.git',
        '.github',
        'deploy.php',
//        '/storage/',
        '/vendor/',
        '/node_modules/',
    ],
]);

// Set up a deployer task to copy secrets to the server.
// Since our secrets are stored in Github, we can access them as env vars.
task('deploy:secrets', function () {
    file_put_contents(__DIR__ . '/.env', getenv('DOT_ENV'));
    upload('.env', get('deploy_path') . '/shared');
});

// Set ssh user
set('user', function () {
    return getenv('SSH_USER');
});

// Hosts
host(getenv('SSH_HOST'))
    ->stage('production')
    ->user(getenv('SSH_USER'))
    ->port(getenv('SSH_PORT'))
    ->set('deploy_path', '~/{{application}}');

// [Optional] if deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');

desc('Deploy the application');
task('deploy', [
    'deploy:info',
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
//    'rsync', // Deploy code & built assets
//    'deploy:secrets', // Deploy secrets
    'scp:deploy',
    'scp:deploy-secrets',
    'deploy:shared',
    'deploy:vendors',
    'deploy:writable',
    'artisan:storage:link', // |
    'artisan:view:cache',   // |
    'artisan:config:cache', // | Laravel specific steps
    'artisan:optimize',     // |
    'artisan:migrate',      // |
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
]);

function scp($source, $destination, $options = []) {
    $host = \Deployer\Task\Context::get()->getHost();
    $port = $host->getPort();
    $user = $host->getUser();
    $hostname = $host->getRealHostname();
    runLocally("scp -rC -P $port $source $user@$hostname:$destination", $options);
}

desc('Upload the application using scp');
task('scp:deploy', function () {
//    $src = __DIR__ . '/deploy';
//    runLocally("mkdir -p $src");

    $source = '/*';

//    $excludes = [
//        '*.env',
//        '*.git',
//        '*.github',
//        'deploy.php',
//        'vendor',
//        'node_modules'
//    ];
//
//    $excluded = '';
//    foreach ($excludes as $key => $exclude) {
//        $excluded.= ($key === 0) ? $exclude : "|$exclude";
//    }
//
//    if (!empty($excluded)) {
////        runLocally('shopt -s extglob');
//        $source = "/!($excluded)";
//    }

    scp(__DIR__ . $source, '{{release_path}}', ['timeout' => 3600]);
});

desc('Upload the application secrets');
task('scp:deploy-secrets', function () {
    file_put_contents(__DIR__ . '/.env', getenv('DOT_ENV'));
    scp(__DIR__ . '/.env', '{{deploy_path}}/shared');
});

