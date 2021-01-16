<?php
namespace Deployer;

require 'recipe/laravel.php';
require 'recipe/rsync.php';

// Project name
set('application', 'laravel_github_actions_test');

// Project repository
set('repository', 'git@github.com:Esirei/Laravel-Github-Actions-Test.git');

// Speeds up deployments
set('ssh_multiplexing', true);

// If your project isn't in the root, you'll need to change this.
set('rsync_src', function () {
    return __DIR__;
});

set('writable_mode', 'chown');
set('writable_recursive', true);
set('http_user', 'www-data');

// Configuring the rsync exclusions.
// You'll want to exclude anything that you don't want on the production server.
add('rsync', [
    'exclude' => [
        '.env',
        '.git',
        '.github',
        'deploy.php',
        '/storage/',
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
    'rsync', // Deploy code & built assets
    'deploy:secrets', // Deploy secrets
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

