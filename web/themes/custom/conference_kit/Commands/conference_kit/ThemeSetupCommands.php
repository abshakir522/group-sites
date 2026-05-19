<?php

declare(strict_types=1);

namespace Drush\Commands\conference_kit;

use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;


/**
 * Class ThemeSetupCommands.
 */
class ThemeSetupCommands extends DrushCommands {

  /**
   * conference_kit theme setup command.
   */
  #[CLI\Command(name: 'conference_kit:setup', aliases: ['conference_kit'])]
  #[CLI\Usage(name: 'drush conference_kit:setup', description: 'Sets up the conference_kit theme requirements.')]
  #[CLI\Bootstrap(level: \Drush\Boot\DrupalBootLevels::FULL)]

  public function createThemeSetup() {
    try {

      $drupalRoot = Drush::bootstrapManager()->getRoot();
      $rootPath = dirname($drupalRoot);

      // Define paths.
      $themePath = $drupalRoot . '/themes/custom/conference_kit'; // Path to the theme directory.
      $ddevConfigPath = "$rootPath/.ddev/config.yaml";

      $this->logger()->notice("Setting up conference_kit theme...");
      $this->logger()->notice("Drupal root path: $drupalRoot");
      $this->logger()->notice("Theme path: $themePath");
      $this->logger()->notice("Top root path: $rootPath");

      // Copy files and directories.
      $filesystem = new Filesystem();
      $this->copyFile("$themePath/assets/scaffold/package.json", "$rootPath/package.json", $filesystem);

      // Copy recipes directory
      $recipesDir = "$rootPath/recipes";
      $this->logger()->notice("Copying recipes scaffold...");
      $recipesSourceDir = "$themePath/assets/scaffold/recipes";
      
      if ($filesystem->exists($recipesSourceDir)) {
        $filesystem->mirror($recipesSourceDir, $recipesDir);
        $this->logger()->notice("Recipes directory copied successfully.");
      } else {
        $this->logger()->warning("Recipes source directory not found at: $recipesSourceDir");
      }

      // Create images directory in the project root if it doesn't exist.
      $imagesDir = "$rootPath/images";
      if (!$filesystem->exists($imagesDir)) {
        $this->logger()->notice("Creating images directory in the project root...");
        $filesystem->mkdir($imagesDir);
      } else {
        $this->logger()->notice("Images directory already exists. Skipping creation.");
      }

      // Add DDEV customizations if .ddev directory exists
      if ($filesystem->exists("$rootPath/.ddev")) {
        $this->logger()->notice("Adding DDEV customizations...");
        $this->addDdevCustomizations($ddevConfigPath, $filesystem);
      }

      // Update development.services.yml
      $this->logger()->notice("Update development.services.yml...");
      $this->updateDevelopmentServicesYml($drupalRoot, $filesystem);

      // Run npm install in the theme directory.
      $this->logger()->notice("Running npm install in the theme directory...");
      $this->runCommand("npm install", $themePath);

      // Run npm install in the root directory.
      $this->logger()->notice("Running npm install in the root directory...");
      $this->runCommand("npm install", $rootPath);

      // Enable the theme and set as a default theme.
      $this->logger()->notice("Enable the theme and set as a default theme...");
      $this->runCommand('drush then conference_kit -y; drush config-set system.theme default conference_kit -y', $rootPath);
      
      // Check if conference_kit is default theme
      $default_theme = \Drupal::config('system.theme')->get('default');
      $optionalConfigPath = $rootPath . '/optionalConfig';

      if (is_dir($optionalConfigPath) && $default_theme) {
        $this->logger()->notice("Importing optional config from: $optionalConfigPath");
        $this->runCommand("drush config:import --partial --source=$optionalConfigPath -y", $rootPath);
      } else {
        $this->logger()->warning("Skipping optional config import.");
      }

      // Clear cache.
      $this->logger()->notice("Clearing cache...");
      $this->runCommand("drush cr", $rootPath);

      $this->logger()->success("✅ conference_kit theme setup complete!");

    } catch (\Exception $exception) {
      $this->logger()->error($exception->getMessage());
    }
  }

  /**
   * Adds DDEV customizations to config.yml.
   */
  protected function addDdevCustomizations(string $configPath, Filesystem $filesystem): void {
    $customConfig = <<<YAML

###############################################################################
# Customizations
###############################################################################
nodejs_version: "18"
webimage_extra_packages:
  - pkg-config
  - libpixman-1-dev
  - libcairo2-dev
  - libpango1.0-dev
  - make
web_extra_daemons:
  - name: node.js
    command: "tail -F package.json > /dev/null"
    directory: /var/www/html
hooks:
  post-start:
    - exec: echo '================================================================================='
    - exec: echo '                                  NOTICE'
    - exec: echo '================================================================================='
    - exec: echo 'conference_kit theme is ready!'
    - exec: echo 'Run "npm run watch" in the theme directory to watch for changes.'
    - exec: echo '================================================================================='

###############################################################################
# End of customizations
###############################################################################
YAML;

    // Append the custom configuration if the file exists
    if ($filesystem->exists($configPath)) {
      $currentContent = file_get_contents($configPath);

      // Only append if the custom config isn't already there
      if (strpos($currentContent, '# Customizations') === false) {
        file_put_contents($configPath, $currentContent . $customConfig);
        $this->logger()->notice("Added DDEV customizations to config.yml");
      } else {
        $this->logger()->notice("DDEV customizations already exist in config.yml. Skipping.");
      }
    } else {
      $this->logger()->warning("DDEV config.yml not found at $configPath");
    }
  }

  protected function updateDevelopmentServicesYml(string $web, Filesystem $filesystem): void {
    $devServicesPath = "$web/sites/development.services.yml";

    if (!$filesystem->exists($devServicesPath)) {
      $this->logger()->warning("development.services.yml not found at $devServicesPath");
      return;
    }

    $newParameters = <<<YAML
parameters:
  http.response.debug_cacheability_headers: true
  cors.config:
    enabled: true
    allowedHeaders: ['*']
    allowedMethods: ['*']
    allowedOrigins: ['*']
    exposedHeaders: false
    maxAge: false
    supportsCredentials: true
YAML;

    // Read existing content
    $content = file_get_contents($devServicesPath);

    // Replace parameters section using regex
    $pattern = '/parameters:(.*?)(\n\w|\Z)/s';
    $newContent = preg_replace($pattern, $newParameters . "\n\n$2", $content);

    if ($newContent !== null) {
      file_put_contents($devServicesPath, $newContent);
      $this->logger()->notice("Updated parameters in development.services.yml");
    } else {
      $this->logger()->error("Failed to update development.services.yml");
    }
  }

  /**
   * Copies a file from source to destination.
   */
  protected function copyFile(string $source, string $destination, Filesystem $filesystem): void {
    if ($filesystem->exists($source)) {
      $filesystem->copy($source, $destination);
      $this->logger()->notice("Copied $source to $destination.");
    } else {
      $this->logger()->warning("Source file not found: $source");
    }
  }

  /**
   * Runs a shell command in the specified directory.
   */
  protected function runCommand(string $command, string $workingDirectory): void {
    $process = Process::fromShellCommandline($command, $workingDirectory);
    $process->setTimeout(null);
    $process->run(function ($type, $buffer) {
      $this->logger()->notice($buffer);
    });

    if (!$process->isSuccessful()) {
      throw new \RuntimeException("Command failed: {$process->getErrorOutput()}");
    }
  }

}