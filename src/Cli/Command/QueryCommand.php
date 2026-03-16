<?php

namespace FQL\Cli\Command;

use FQL\Cli\Config\ConfigManager;
use FQL\Cli\Config\ServerConfig;
use FQL\Cli\Config\SessionManager;
use FQL\Cli\Interactive\HistoryManager;
use FQL\Cli\Interactive\Repl;
use FQL\Cli\Interactive\ResultPager;
use FQL\Cli\Output\JsonRenderer;
use FQL\Cli\Output\TableRenderer;
use FQL\Cli\Query\ApiQueryExecutor;
use FQL\Cli\Query\LocalQueryExecutor;
use FQL\Cli\Query\QueryExecutorInterface;
use FQL\Cli\Query\QuerySplitter;
use FQL\Client\Dto\AuthToken;
use FQL\Client\Exception\AuthenticationException;
use FQL\Client\FiQueLaClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class QueryCommand extends Command
{
    private ConfigManager $configManager;
    private SessionManager $sessionManager;

    protected function configure(): void
    {
        $this->setName('fiquela-cli')
            ->setDescription('Execute SQL-like queries on structured data files or via FiQueLa API')
            ->addArgument('query', InputArgument::OPTIONAL, 'FQL query (if omitted, enter interactive mode)')
            ->addOption('file', 'f', InputOption::VALUE_OPTIONAL, 'Path to the data file')
            ->addOption('file-type', 't', InputOption::VALUE_OPTIONAL, 'File type (csv, xml, json, yaml, neon)')
            ->addOption('file-delimiter', 'd', InputOption::VALUE_OPTIONAL, 'CSV file delimiter', ',')
            ->addOption('file-encoding', 'e', InputOption::VALUE_OPTIONAL, 'File encoding', 'utf-8')
            ->addOption('memory-limit', 'm', InputOption::VALUE_OPTIONAL, 'Set memory limit (e.g. 128M)', '128M')
            ->addOption('connect', 'c', InputOption::VALUE_NONE, 'Connect to FiQueLa API')
            ->addOption('server', 's', InputOption::VALUE_OPTIONAL, 'Server name from auth.json')
            ->addOption('user', 'u', InputOption::VALUE_OPTIONAL, 'API username')
            ->addOption('password', 'p', InputOption::VALUE_OPTIONAL, 'API password');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var ConsoleOutput $output */
        $this->configManager = new ConfigManager();
        $this->sessionManager = new SessionManager($this->configManager->getConfigDir());

        // Set memory limit (only affects local mode, but set it always)
        $memoryLimit = $input->getOption('memory-limit');
        if ($memoryLimit !== null) {
            ini_set('memory_limit', $memoryLimit);
        }

        $isApiMode = (bool) $input->getOption('connect');
        $query = $input->getArgument('query') ?? '';

        try {
            $executor = $isApiMode
                ? $this->createApiExecutor($input, $output)
                : $this->createLocalExecutor($input);
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
            return Command::FAILURE;
        }

        if ($executor === null) {
            return Command::FAILURE;
        }

        // Non-interactive mode: query is provided as argument
        if ($query !== '') {
            return $this->runNonInteractive($output, $executor, $query);
        }

        // Interactive mode
        return $this->runInteractive($output, $executor, $isApiMode);
    }

    private function runNonInteractive(OutputInterface $output, QueryExecutorInterface $executor, string $query): int
    {
        $jsonRenderer = new JsonRenderer();

        // Support multiple queries separated by semicolons (respecting quoted strings)
        $queries = QuerySplitter::split($query);

        foreach ($queries as $singleQuery) {
            try {
                $result = $executor->executeAll($singleQuery);
                $jsonRenderer->render($output, $result);
            } catch (\Exception $e) {
                $output->writeln(json_encode(
                    ['error' => $e->getMessage()],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ) ?: '{"error":"Unknown error"}');
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @param ConsoleOutput $output
     */
    private function runInteractive(OutputInterface $output, QueryExecutorInterface $executor, bool $isApiMode): int
    {
        $historyFile = $isApiMode
            ? $this->configManager->getConfigDir() . '/history-api'
            : $this->configManager->getConfigDir() . '/history';

        $historyManager = new HistoryManager($historyFile);
        $resultPager = new ResultPager(new TableRenderer());

        $repl = new Repl($output, $executor, $historyManager, $resultPager);
        return $repl->run();
    }

    private function createLocalExecutor(InputInterface $input): LocalQueryExecutor
    {
        $file = $input->getOption('file');
        $fileType = $input->getOption('file-type');
        $delimiter = $input->getOption('file-delimiter') ?? ',';
        $encoding = $input->getOption('file-encoding') ?? 'utf-8';

        // Validate file exists if specified
        if ($file !== null && $file !== '' && !file_exists($file)) {
            throw new \RuntimeException(sprintf('File not found: %s', $file));
        }

        return new LocalQueryExecutor(
            $file !== '' ? $file : null,
            $fileType,
            $delimiter,
            $encoding
        );
    }

    /**
     * @param ConsoleOutput $output
     */
    private function createApiExecutor(InputInterface $input, OutputInterface $output): ?ApiQueryExecutor
    {
        $serverName = $input->getOption('server');
        $user = $input->getOption('user');
        $password = $input->getOption('password');

        // Try to load from auth.json
        $serverConfig = null;

        if ($this->configManager->hasAuthFile()) {
            if (!$this->configManager->validateAuthFilePermissions()) {
                // Auth file exists but bad permissions
                $output->writeln(sprintf(
                    '<comment>Warning: %s has incorrect permissions. Required: %s</comment>',
                    $this->configManager->getAuthFile(),
                    ConfigManager::getRequiredPermissionsString()
                ));
                $output->writeln('<comment>Falling back to command-line credentials.</comment>');
                $output->writeln('');

                // Must provide credentials via command line
                if ($user === null || $password === null || $serverName === null) {
                    $output->writeln(
                        '<error>When auth.json permissions are incorrect, '
                        . 'you must provide --user (-u), --password (-p), and --server (-s) options.</error>'
                    );
                    return null;
                }

                $serverConfig = new ServerConfig($serverName, $serverName, $user, $password);
            } else {
                // Auth file is valid
                $servers = $this->configManager->loadServers();

                if (empty($servers)) {
                    // Auth file exists but empty — offer to add server
                    $serverConfig = $this->interactiveAddServer($input, $output);
                    if ($serverConfig === null) {
                        return null;
                    }
                } elseif ($serverName !== null) {
                    // Specific server requested
                    $serverConfig = $this->configManager->findServer($serverName);
                    if ($serverConfig === null) {
                        $output->writeln(sprintf(
                            '<error>Server "%s" not found in auth.json.</error>',
                            $serverName
                        ));
                        return null;
                    }
                } elseif (count($servers) === 1) {
                    // Single server — use it
                    $serverConfig = $servers[0];
                } else {
                    // Multiple servers — interactive selection
                    $serverConfig = $this->interactiveSelectServer($input, $output, $servers);
                    if ($serverConfig === null) {
                        return null;
                    }
                }
            }
        } else {
            // No auth.json — check CLI credentials or offer to add server
            if ($user !== null && $password !== null && $serverName !== null) {
                $serverConfig = new ServerConfig($serverName, $serverName, $user, $password);
            } else {
                // Offer interactive server addition
                $serverConfig = $this->interactiveAddServer($input, $output);
                if ($serverConfig === null) {
                    return null;
                }
            }
        }

        // Create client and authenticate
        $client = new FiQueLaClient($serverConfig->url);

        // Check for existing valid session token
        if ($this->sessionManager->validatePermissions()) {
            $existingToken = $this->sessionManager->getToken($serverConfig->url);
            if ($existingToken !== null) {
                $client->setToken($existingToken->token);
                return new ApiQueryExecutor($client, $serverConfig->name);
            }
        }

        // Login to get a new token
        try {
            $authToken = $client->login($serverConfig->user, $serverConfig->secret);
            $client->setToken($authToken->token);

            // Save token to session
            $this->sessionManager->saveToken($serverConfig->url, $authToken);

            return new ApiQueryExecutor($client, $serverConfig->name);
        } catch (AuthenticationException $e) {
            $output->writeln(sprintf('<error>Authentication failed: %s</error>', $e->getMessage()));
            return null;
        }
    }

    /**
     * @param ServerConfig[] $servers
     */
    private function interactiveSelectServer(
        InputInterface $input,
        OutputInterface $output,
        array $servers
    ): ?ServerConfig {
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        $choices = [];
        foreach ($servers as $server) {
            $choices[$server->name] = sprintf('%s (%s)', $server->name, $server->url);
        }

        $question = new ChoiceQuestion('Select a server:', $choices);
        $question->setErrorMessage('Server "%s" is not valid.');

        $selectedName = $helper->ask($input, $output, $question);

        // Find the original server config by name
        foreach ($servers as $server) {
            if ($server->name === $selectedName) {
                return $server;
            }
        }

        return null;
    }

    private function interactiveAddServer(InputInterface $input, OutputInterface $output): ?ServerConfig
    {
        $output->writeln('<info>No servers configured. Let\'s add one.</info>');
        $output->writeln('');

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        $nameQuestion = new Question('Server name (alias): ');
        $name = $helper->ask($input, $output, $nameQuestion);
        if ($name === null || trim($name) === '') {
            $output->writeln('<error>Server name is required.</error>');
            return null;
        }

        $urlQuestion = new Question('Server URL (e.g. https://api.example.com): ');
        $url = $helper->ask($input, $output, $urlQuestion);
        if ($url === null || trim($url) === '') {
            $output->writeln('<error>Server URL is required.</error>');
            return null;
        }

        $userQuestion = new Question('Username: ');
        $user = $helper->ask($input, $output, $userQuestion);
        if ($user === null || trim($user) === '') {
            $output->writeln('<error>Username is required.</error>');
            return null;
        }

        $passwordQuestion = new Question('Password: ');
        $passwordQuestion->setHidden(true);
        $passwordQuestion->setHiddenFallback(false);
        $password = $helper->ask($input, $output, $passwordQuestion);
        if ($password === null || trim($password) === '') {
            $output->writeln('<error>Password is required.</error>');
            return null;
        }

        $serverConfig = new ServerConfig(
            trim($name),
            trim($url),
            trim($user),
            $password
        );

        // Save to auth.json
        $this->configManager->addServer($serverConfig);
        $output->writeln(sprintf('<info>Server "%s" saved to %s</info>', $name, $this->configManager->getAuthFile()));
        $output->writeln('');

        return $serverConfig;
    }
}
