<?php

define( 'VENDI_LOG_WATCHER_FILE', __FILE__ );
define( 'VENDI_LOG_WATCHER_PATH', __DIR__ );
define( 'VENDI_LOG_WATCHER_APP_VERSION', '1.0.0' );

require VENDI_LOG_WATCHER_PATH . '/includes/autoload.php';

$application = new Symfony\Component\Console\Application( 'Vendi Log Watcher', '0.1-dev' );
$application->add( new Vendi\Admin\LogWatcher\Commands\get_logs_command() );


// $application->setDefaultCommand( $create_site_wizard->getName() );
$application->run();
