<?php

namespace Rougin\Combustor\Commands;

use Rougin\Blueprint\AbstractCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Remove Wildfire Command
 *
 * Removes Wildfire from CodeIgniter
 * 
 * @package Combustor
 * @author  Rougin Royce Gutib <rougingutib@gmail.com>
 */
class RemoveWildfireCommand extends AbstractCommand
{
    /**
     * Checks whether the command is enabled or not in the current environment.
     *
     * Override this to check for x or y and return false if the command can not
     * run properly under the current conditions.
     *
     * @return bool
     */
    public function isEnabled()
    {
        if (file_exists(APPPATH . 'libraries/Wildfire.php')) {
            return TRUE;
        }

        return FALSE;
    }

    /**
     * Sets the configurations of the specified command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('remove:wildfire')
            ->setDescription('Remove Wildfire');
    }

    /**
     * Executes the command.
     * 
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return object|OutputInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /**
         * Adding Wildfire.php to the "libraries" directory
         */

        if ( ! file_exists(APPPATH . 'libraries/Wildfire.php')) {
            exit($output->writeln('<error>Wildfire is not installed!</error>'));
        }

        $autoload = file_get_contents(APPPATH . 'config/autoload.php');

        preg_match_all('/\$autoload\[\'libraries\'\] = array\((.*?)\)/', $autoload, $match);

        $libraries = explode(', ', end($match[1]));

        if (in_array('\'wildfire\'', $libraries)) {
            $position = array_search('\'wildfire\'', $libraries);

            unset($libraries[$position]);

            $libraries = array_filter($libraries);

            $autoload = preg_replace(
                '/\$autoload\[\'libraries\'\] = array\([^)]*\);/',
                '$autoload[\'libraries\'] = array(' . implode(', ', $libraries) . ');',
                $autoload
            );

            $file = fopen(APPPATH . 'config/autoload.php', 'wb');

            file_put_contents(APPPATH . 'config/autoload.php', $autoload);
            fclose($file);
        }

        if (unlink(APPPATH . 'libraries/Wildfire.php')) {
            $output->writeln('<info>Wildfire is now successfully removed!</info>');
        } else {
            $output->writeln('<error>There\'s something wrong while removing. Please try again later.</error>');
        }
    }
}