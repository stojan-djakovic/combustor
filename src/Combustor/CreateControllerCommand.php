<?php

namespace Combustor;

use Combustor\Tools\Inflect;
use Combustor\Tools\GetColumns;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateControllerCommand extends Command
{

	/**
	 * Set the configurations of the specified command
	 */
	protected function configure()
	{
		$this->setName('create:controller')
			->setDescription('Create a new controller')
			->addArgument(
				'name',
				InputArgument::REQUIRED,
				'Name of the controller'
			)->addOption(
				'keep',
				null,
				InputOption::VALUE_NONE,
				'Keeps the name to be used'
			);
	}

	/**
	 * Execute the command
	 * 
	 * @param  InputInterface  $input
	 * @param  OutputInterface $output
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		/**
		 * Get the controller template
		 */
		
		$controller = file_get_contents(__DIR__ . '/Templates/Controller.txt');
		
		/**
		 * Get the columns from the specified name
		 */

		$columns = new GetColumns($input->getArgument('name'), $output);

		if ( ! $columns->result()) {
			$output->writeln('<error>There is no table named "' . $input->getArgument('name') . '" from the database!</error>');
			exit();	
		}

		$columns = $columns->result();

		$columnsCreate = NULL;
		$columnsEdit = NULL;
		$columnsValidate = NULL;
		$counter = 0;

		foreach ($columns as $row) {
			if ($counter != 0) {
				if ($row->Field != 'datetime_updated') {
					$columnsCreate .= "			";
				}

				if ($row->Field != 'datetime_created') {
					$columnsEdit .= "			";
				}

				if ($row->Field != 'password' && $row->Field != 'datetime_created' && $row->Field != 'datetime_updated') {
					$columnsValidate .= "			";
				}
			}

			if ($row->Extra == 'auto_increment') {
				continue;
			} elseif ($row->Key == 'MUL') {
				$entity = str_replace('_id', '', $row->Field);
				$columnsCreate .= "\n" . '			$' . $entity . ' = $this->doctrine->em->find(\'' . $entity . '\', $this->input->post(\'' . $row->Field . '\'));' . "\n";
				$columnsCreate .= '			$this->$singular->set_' . strtolower($row->Field) . '($' . $entity . ');';

				$columnsEdit .= "\n" . '			$' . $entity . ' = $this->doctrine->em->find(\'' . $entity . '\', $this->input->post(\'' . $row->Field . '\'));' . "\n";
				$columnsEdit .= '			$$singular->set_' . strtolower($row->Field) . '($' . $entity . ');';
			} elseif ($row->Field == 'password') {
				$columnsCreate .= "\n" . '			if ($this->input->post(\'password\') == $this->input->post(\'confirm_password\')) {' . "\n";
				$columnsCreate .= '				$this->$singular->set_password($this->input->post(\'password\'));' . "\n";
				$columnsCreate .= '			} else {' . "\n";
				$columnsCreate .= '				$this->session->set_flashdata(\'notification\', \'The passwords you entered did not match!\');' . "\n";
				$columnsCreate .= '				$this->session->set_flashdata(\'alert\', \'danger\');' . "\n";
				$columnsCreate .= '				redirect(\'$plural/create\');' . "\n";
				$columnsCreate .= '			}' . "\n\n";

				$columnsEdit .= "\n" . '			if ($this->input->post(\'old_password\') != NULL && $this->input->post(\'new_password\') != NULL && $this->input->post(\'confirm_password\') != NULL) {' . "\n";
				$columnsEdit .= '				if (md5($this->input->post(\'old_password\')) != $$singular->get_password() || $this->input->post(\'new_password\') != $this->input->post(\'confirm_password\')) {' . "\n";
				$columnsEdit .= '					$this->session->set_flashdata(\'notification\', \'The passwords you entered did not match!\');' . "\n";
				$columnsEdit .= '					$this->session->set_flashdata(\'alert\', \'danger\');' . "\n";
				$columnsEdit .= '					redirect(\'$plural/edit/\' . $id);' . "\n";
				$columnsEdit .= '				} else {' . "\n";
				$columnsEdit .= '					$$singular->set_password($this->input->post(\'new_password\'));' . "\n";
				$columnsEdit .= '				}' . "\n";
				$columnsEdit .= '			}' . "\n\n";
			} else {
				$column = ($row->Field == 'datetime_created' || $row->Field == 'datetime_updated') ? 'now' : $row->Field;

				if ($row->Field != 'datetime_updated') {
					$columnsCreate .= '$this->$singular->set_' . strtolower($row->Field) . '($this->input->post(\'' . $column . '\'));' . "\n";
				}

				if ($row->Field != 'datetime_created') {
					$columnsEdit .= '$$singular->set_' . strtolower($row->Field) . '($this->input->post(\'' . $column . '\'));' . "\n";
				}
			}

			if ($row->Field != 'password' && $row->Field != 'datetime_created' && $row->Field != 'datetime_updated') {
				$columnsValidate .= '\'' . $row->Field . '\' => \'' . str_replace('_', ' ', $row->Field) . '\',' . "\n";
			}

			$counter++;
		}

		/**
		 * Search and replace the following keywords from the template
		 */

		$search = array(
			'$columnsCreate',
			'$columnsEdit',
			'$columnsValidate',
			'$controller',
			'$plural',
			'$singular'
		);

		$replace = array(
			rtrim($columnsCreate),
			rtrim($columnsEdit),
			rtrim($columnsValidate),
			ucfirst(Inflect::pluralize($input->getArgument('name'))),
			Inflect::pluralize($input->getArgument('name')),
			Inflect::singularize($input->getArgument('name'))
		);

		$controller = str_replace($search, $replace, $controller);

		/**
		 * Create a new file and insert the generated template
		 */

		$name = ($input->getOption('keep')) ? Inflect::pluralize($input->getArgument('name')) : $input->getArgument('name');

		$filename = APPPATH . 'controllers/' . ucfirst($name) . '.php';

		if (file_exists($filename)) {
			$output->writeln('<error>The ' . Inflect::pluralize($input->getArgument('name')) . ' controller already exists!</error>');

			exit();
		}

		$file = fopen($filename, 'wb');
		file_put_contents($filename, $controller);

		$output->writeln('<info>The controller "' . Inflect::pluralize($input->getArgument('name')) . '" has been created successfully!</info>');
	}
	
}