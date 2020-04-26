<?php
namespace Valet;

class Debug
{
    const SOURCE_LOG = 'log';
    const SOURCE_CLI = 'cli';

    public $cli;
    public $count = 0;
    public $files;
    public $logPath;
    public $source = self::SOURCE_CLI;

    /**
     * Create a debug log.
     *
     * @param  CommandLine $cli
     * @param  Filesystem  $files
     */
    public function __construct(
        CommandLine $cli,
        Filesystem $files
    ) {
        $this->cli = $cli;
        $this->files = $files;
    }

    /**
     * Runs the debug commands of the services.
     *
     * @param $log
     * @param $table
     */
    public function run($log, $table)
    {
        if ($log) {
            $this->createLogFile();
            $this->source = static::SOURCE_LOG;
        }

        if ($table && $this->source !== static::SOURCE_CLI) {
            warning('[--table] can only be used with CLI output.');
            return;
        }

        $output = $table ? $this->source . '-table' : $this->source;
        info(sprintf('Starting debug. (output: %s)', $output));

        if ($table) {
            $this->processTable();
        } else {
            $this->processItems();
        }

        $response = sprintf('Debug finished (%s total messages).', $this->count);
        if ($log) {
            $response .= sprintf("\nDebug log file location: %s ", $this->logPath);
        }

        info($response);
    }

    /**
     * Processes items in table form for CLI.
     */
    public function processTable()
    {
        $headers = [
            'Id',
            'Service',
            'Type',
            'Message'
        ];
        $rows = [];

        /** @var \Valet\Interfaces\ServiceInterface $service */
        foreach (get_services() as $service) {
            $messages = $service::debug();
            foreach ($messages as $message) {
                $rows[] = [
                    $this->getCount(),
                    $service,
                    $message->getTypeLabel(),
                    $message->getMessage(),
                ];
            }
        }

        if (count($rows) === 0) {
            info($this->getPrefixInfo() . 'No issues found!');
            return;
        }

        table($headers, $rows);
    }

    /**
     * Processes items.
     */
    public function processItems()
    {
        /** @var \Valet\Interfaces\ServiceInterface $service */
        foreach (get_services() as $service) {
            $this->logInfo(sprintf('Debugging %s', $service));

            $messages = $service::debug();
            foreach ($messages as $message) {
                $this->processMessage($message);
            }

            $this->logInfo(sprintf("Finished debugging %s (%s messages)\n", $service, count($messages)));
        }
    }

    /**
     * Processes a message and sends it to chosen source.
     *
     * @param $message
     */
    public function processMessage($message)
    {
        switch ($this->source) {
            case static::SOURCE_LOG:
                $this->logToFile($message);
                break;

            case static::SOURCE_CLI:
            default:
                $this->logToCli($message);
                break;
        }
    }

    /**
     * Logs a message to the log file.
     *
     * @param $message
     */
    public function logToFile($message)
    {
        $this->addPrefixSuffix($message, true);
        $this->files->append($this->logPath, $message->getMessage());
    }

    /**
     * Outputs given message to the terminal.
     *
     * @param $message
     */
    public function logToCli($message)
    {
        $this->addPrefixSuffix($message);
        switch ($message->getType()) {
            case LOG_WARNING:
                warning($message->getMessage());
                break;

            case LOG_INFO:
            default:
                info($message->getMessage());
                break;
        }
    }

    /**
     * Adds prefix/suffix for given message.
     *
     * @param $message
     * @param $suffix
     */
    public function addPrefixSuffix(&$message, $suffix = false)
    {
        switch ($message->getType()) {
            case LOG_WARNING:
                $message->prefixMessage($this->getPrefixWarning());
                break;

            case LOG_INFO:
            default:
                $message->prefixMessage($this->getPrefixInfo());
                break;
        }

        // Suffix for log file output.
        if ($suffix) {
            $message->suffixMessage("\n");
        }
    }

    /**
     * Prefix warnings for easier reference.
     *
     * @return string
     */
    public function getPrefixWarning()
    {
        return $this->getCount() . ". [warning] ";
    }

    /**
     * Prefix info for easier readability.
     *
     * @return string
     */
    public function getPrefixInfo()
    {
        return "==> ";
    }

    /**
     * Logs info message.
     *
     * @param $message
     */
    public function logInfo($message)
    {
        $debug = new DebugMessage($message, LOG_INFO);
        $this->processMessage($debug);
    }

    /**
     * Returns current count.
     *
     * @return int
     */
    public function getCount()
    {
        return ++$this->count;
    }

    /**
     * Creates a new log file to write to.
     */
    public function createLogFile()
    {
        $fileName = 'debug_' . date('Ymd_his') . '.log';
        $logPath = VALET_HOME_PATH . '/Log/debug/';
        $this->files->ensureDirExists($logPath, user());
        $this->logPath = $this->files->touch($logPath . $fileName, user());
    }
}
