<?php

namespace Vendi\Admin\LogWatcher\Commands;

use Desipa\Tail;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

class get_logs_command extends Command
{

    private $_io;

    public function set_io( SymfonyStyle $io )
    {
        $this->_io = $io;
    }

    protected final function get_or_create_io( InputInterface $input, OutputInterface $output ) : SymfonyStyle
    {
        if( ! $this->_io )
        {
            $this->_io = new SymfonyStyle( $input, $output );
        }
        return $this->_io;
    }

    protected final function root_check(SymfonyStyle $io)
    {
        $is_root = ( 0 === posix_getuid() );
        if( ! $is_root )
        {
            $io->error( 'This command needs to run as root. Please re-run it with sudo privileges.' );
            exit;
        }
    }

    protected function configure()
    {
        $this
            ->setName( 'get-logs' )
            ->setHidden( false )
            ->setDescription( 'Get logs' )
        ;
    }

    protected function initialize( InputInterface $input, OutputInterface $output )
    {

    }

    protected function execute( InputInterface $input, OutputInterface $output )
    {
        $io = $this->get_or_create_io( $input, $output);

        $this->root_check($io);

        $configs = $this->_get_active_nginx_files();
        $access_logs = $this->_get_all_logs_from_all_files($configs);
        $this->_do_tail($access_logs, $io);
    }

    protected function _do_tail(array $access_logs, SymfonyStyle $io)
    {
        $tail = new Tail(VENDI_LOG_WATCHER_PATH . '/tail.json');
        foreach($access_logs as $l){
            $tail->addFile($l);
        }

        //This blocks by an infinite loop
        $tail
            ->listenForLines(
                function($filename, $line) use ($io)
                {
                    $io->text($line);
                }
            )
        ;
    }

    protected function _get_active_nginx_files() : array
    {
        $finder = new Finder();
        $finder
            ->files()
            ->in( '/etc/nginx/sites-enabled/' )
        ;

        $ret = [];
        foreach($finder as $file){
            $ret[] = $file->getRealPath();
        }

        return $ret;
    }

    protected function _get_all_logs_from_all_files(array $configs) : array
    {
        $access_logs = [];
        foreach($configs as $c){
            $access_logs = array_merge($access_logs, $this->_get_all_logs_from_single_file($c) );
        }

        return $access_logs;
    }

    protected function _get_all_logs_from_single_file(string $abs_file_path) : array
    {
        $data = file_get_contents($abs_file_path);
        $lines = explode("\n", $data);
        $ret = [];
        foreach($lines as $line){
            //Cleanup whitespace
            $simple_line = preg_replace('/\s+/', ' ', trim($line));
            $parts = explode(' ', $simple_line);

            //We're looking for lines with at least two parts delim'd by spaces
            if(count($parts) < 2){
                continue;
            }

            //First part should start with this
            if(!in_array($parts[0], [ 'access_log', 'error_log' ])){
                continue;
            }

            //The file is the second part
            $file = $parts[1];

            //Sanity check that it exists
            if(!is_file($file)){
                continue;
            }

            $ret[] = $file;
        }

        return $ret;
    }


}
