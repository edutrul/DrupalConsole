<?php

/**
 * @file
 * Contains \Drupal\Console\Command\Module\InstallCommand.
 */

namespace Drupal\Console\Command\Module;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Drupal\Console\Command\ContainerAwareCommand;
use Drupal\Console\Command\ProjectDownloadTrait;
use Drupal\Console\Style\DrupalStyle;
use Drupal\Console\Command\PHPProcessTrait;

/**
 * Class InstallCommand
 * @package Drupal\Console\Command\Module
 */
class InstallCommand extends ContainerAwareCommand
{
    use PHPProcessTrait;
    use ProjectDownloadTrait;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('module:install')
            ->setDescription($this->trans('commands.module.install.description'))
            ->addArgument(
                'module',
                InputArgument::IS_ARRAY,
                $this->trans('commands.module.install.arguments.module')
            )
            ->addOption(
                'latest',
                '',
                InputOption::VALUE_NONE,
                $this->trans('commands.module.install.options.latest')
            )
            ->addOption(
                'composer',
                '',
                InputOption::VALUE_NONE,
                $this->trans('commands.module.uninstall.options.composer')
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $io = new DrupalStyle($input, $output);

        $module = $input->getArgument('module');
        if (!$module) {
            $module = $this->modulesQuestion($io);
            $input->setArgument('module', $module);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new DrupalStyle($input, $output);

        $modules = $input->getArgument('module');
        $latest = $input->getOption('latest');
        $composer = $input->getOption('composer');

        $this->getDrupalHelper()->loadLegacyFile(
            'core/includes/bootstrap.inc'
        );

        if ($composer) {
            foreach ($modules as $module) {
                $cmd = "cd " . $this->getApplication()->getSite()->getSiteRoot() . "; ";
                $cmd .= 'composer show "drupal/' . $module . '"';

                if ($out = $this->execProcess($cmd)) {
                    $io->info(
                        sprintf(
                            'Module %s was downloaded with Composer.',
                            $module
                        )
                    );
                }
                else {
                    $io->error(
                        sprintf(
                            'Module %s seems not to be installed with Composer. Halting.',
                            $module
                        )
                    );
                    return 0;
                }
            }

            // all given modules were downloaded with Composer
            $unInstalledModules = $modules;

        }
        else{


          $resultList = $this->downloadModules($io, $modules, $latest);

          $invalidModules = $resultList['invalid'];
          $unInstalledModules = $resultList['uninstalled'];

          if ($invalidModules) {
              foreach ($invalidModules as $invalidModule) {
                  unset($modules[array_search($invalidModule, $modules)]);
                  $io->error(
                      sprintf(
                          'Invalid module name: %s',
                          $invalidModule
                      )
                  );
              }
          }

          if (!$unInstalledModules) {
              $io->warning($this->trans('commands.module.install.messages.nothing'));

              return 0;
          }
        }

        try {
            $io->comment(
                sprintf(
                    $this->trans('commands.module.install.messages.installing'),
                    implode(', ', $unInstalledModules)
                )
            );

            $moduleInstaller = $this->getModuleInstaller();
            drupal_static_reset('system_rebuild_module_data');

            $moduleInstaller->install($unInstalledModules, true);
            $io->success(
                sprintf(
                    $this->trans('commands.module.install.messages.success'),
                    implode(', ', $unInstalledModules)
                )
            );
        } catch (\Exception $e) {
            $io->error($e->getMessage());

            return 1;
        }

        $this->getChain()->addCommand('cache:rebuild', ['cache' => 'all']);
    }
}
