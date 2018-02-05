<?php

namespace Deployer;

require 'recipe/symfony3.php';

// Configuration

$prodEnvFile = '.env.prod';

set('shared_files', [
    '.env',
]);
add('shared_dirs', []);
add('writable_dirs', []);
set('clear_paths', []);
set('assets', ['public/build']);
set('env_vars', 'APP_ENV={{env}}');
set('keep_releases', 3);
set('default_stage', 'production');

set('ssh_type', 'native');
set('ssh_multiplexing', true);
set('writable_mode', 'chmod');
set('writable_chmod_mode', '0777');
set('writable_chmod_recursive', true);

/**
 * @param string $message
 */
function fatalError(string $message)
{
    writeln('<error>'.$message.'</error>');
    run("rm -f {{deploy_path}}/.dep/deploy.lock"); // = deploy:unlock
    die;
}

$serverList = getenv('DEPLOY_SERVERS') ?: './deploy_servers.yml';
if (!is_readable($serverList)) {
    die(
        'Server list not available, please fix the DEPLOY_SERVERS env variable. '
        .'Got "'.$serverList.'"'
    );
}
serverList($serverList);

task('check', function() use ($prodEnvFile) {
    if (!is_readable($prodEnvFile)) {
        fatalError($prodEnvFile.' not found');
    }
    if (!commandExist('yarn')) {
        fatalError('Please install yarn');
    }
});

task('deploy:copy_shared_config', function() use ($prodEnvFile) {
    upload($prodEnvFile, '{{deploy_path}}/shared/.env');
});

task('deploy:yarn', function() {
    within(get('release_path'), function() {
        run('yarn install');
    });
});

task('deploy:encore', function() {
    within(get('release_path'), function() {
        run('yarn run encore production');
    });
});

task('deploy:www_restart', function() {
    run('sudo service nginx restart');
    run('sudo service php7.1-fpm restart');
});

// A modified copy of built-in cleanup
desc('Cleaning up old releases with cache files inside');
task('sudo_cleanup', function () {
    $releases = get('releases_list');
    $keep = get('keep_releases');
    if ($keep === -1) {
        // Keep unlimited releases.
        return;
    }
    while ($keep - 1 > 0) {
        array_shift($releases);
        --$keep;
    }

    foreach ($releases as $release) {
        run("sudo rm -rf {{deploy_path}}/releases/$release");
    }

    run("cd {{deploy_path}} && if [ -e release ]; then sudo rm release; fi");
    run("cd {{deploy_path}} && if [ -h release ]; then sudo rm release; fi");
});

task('deploy:assets:install', function () {
    run('{{env_vars}} {{bin/php}} {{bin/console}} assets:install {{console_options}} {{release_path}}/public');
})->desc('Install bundle assets');

/**
 * Assembling install task — copy_shared_config
 */
task('deploy:install', [
    'check',
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:clear_paths',
    'deploy:create_cache_dir',
    'deploy:shared',
    'deploy:copy_shared_config',
    'deploy:assets',
    'deploy:vendors',
    'deploy:writable',
    'deploy:yarn',
    'deploy:encore',
    'deploy:writable',
    'deploy:symlink',
    'deploy:www_restart',
    'deploy:unlock',
    'cleanup',
])->desc('Install your project');

/**
 * Assembling deploy (update) task
 */

task('deploy', [
    'check',
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:clear_paths',
    'deploy:create_cache_dir',
    'deploy:shared',
    'deploy:assets',
    'deploy:writable',
    'deploy:vendors',
    'deploy:writable',
    'deploy:yarn',
    'deploy:encore',
    'deploy:symlink',
    'deploy:www_restart',
    'deploy:unlock',
    'cleanup',
])->desc('Deploy your project');

after('deploy:install', 'success');
after('deploy', 'success');

after('deploy:failed', 'deploy:unlock');
