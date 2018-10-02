<?php
namespace phphound;

use League\CLImate\CLImate;
use phphound\output\AbstractOutput;
use phphound\output\filter\DiffOutputFilter;
use ReflectionMethod;
use SebastianBergmann\Diff\Parser;
use SebastianBergmann\Git\Git;
use UnexpectedValueException;

/**
 * Command line tool that run all script analyzers.
 */
class Command
{
    /**
     * CLI tool.
     * @var CLImate CLImate instance.
     */
    protected $cli;

    /**
     * Analyser.
     * @var Analyser analyser instance.
     */
    protected $analyser;

    /**
     * Command line arguments.
     * @var array list of arguments.
     */
    protected $arguments;

    /**
     * Analysis targets paths.
     * @var array list of files and directories paths.
     */
    protected $analysedPaths;

    /**
     * Composer binaries directory path.
     * @var string directory path.
     */
    protected $binariesPath;

    /**
     * Set dependencies and initialize CLI.
     * @param CLImate $climate CLImate instance.
     * @param string $binariesPath Composer binaries path.
     * @param array $arguments command line arguments.
     */
    public function __construct(CLImate $climate, string $binariesPath, array $arguments)
    {
        $this->cli = $climate;
        $this->cli->description($this->getDescription());
        $this->cli->arguments->add($this->getArguments());
        $this->cli->arguments->parse($arguments);

        $this->arguments = $arguments;
        $this->binariesPath = $binariesPath;
        $this->setAnalysedPathsFromString($this->getArgumentValue('path'));
    }

    /**
     * Run PHP-Hound command.
     * @return boolean true if it didn't find code issues or ran successfully.
     */
    public function run() : bool
    {
        if ($this->hasArgumentValue('help')) {
            $this->cli->usage();
            return true;
        }

        if ($this->hasArgumentValue('version')) {
            $this->cli->out($this->getDescription());
            return true;
        }

        if ($this->hasArgumentValue('git-diff')) {
            $gitDiff = $this->getArgumentValue('git-diff');
            $filter = $this->getGitDiffFilter($gitDiff);
            $analysedFiles = $filter->getFilesWithAddedCode();
            $this->setAnalysedPaths($analysedFiles);
            $this->getAnalyser()->setResultsFilter($filter);
            $this->getAnalyser()->setAnalysedPaths($analysedFiles);
        }

        return $this->getAnalyser()->run();
    }

    /**
     * Create a DiffOutputFilter based on a git-diff param.
     * @param string $gitDiff git diff arguments.
     * @return DiffOutputFilter filter instance.
     */
    protected function getGitDiffFilter(string $gitDiff) : DiffOutputFilter
    {
        $analysedPaths = $this->getAnalysedPaths();
        $gitPath = array_shift($analysedPaths);
        if (!is_dir($gitPath)) {
            $gitPath = dirname($gitPath);
        }
        $git = new Git($gitPath);
        $executeMethod = new ReflectionMethod($git, 'execute');
        $executeMethod->setAccessible(true);
        $gitRoot = trim(implode("\n", $executeMethod->invoke($git, 'git rev-parse --show-toplevel')));
        list($base, $changed) = explode('..', $gitDiff);
        $diff = $git->getDiff($base, $changed);
        $diffParser = new Parser;
        return new DiffOutputFilter($gitRoot, $diffParser->parse($diff));
    }

    /**
     * Initialize output.
     * @throws UnexpectedValueException on invalid format value.
     * @return AbstractOutput
     */
    protected function getOutput() : AbstractOutput
    {
        $format = $this->getOutputFormat();
        $formatClasses = $this->getOutputFormatClasses();

        if (!isset($formatClasses[$format])) {
            throw new UnexpectedValueException(
                'Invalid format: "' . $format . '"'
            );
        }

        $outputClassName = $formatClasses[$format];

        return new $outputClassName(
            $this->cli,
            $this->getWorkingDirectory()
        );
    }

    /**
     * Command line arguments list for CLImate.
     * @return array CLI list of arguments.
     */
    protected function getArguments() : array
    {
        return [
            'help' => [
                'prefix' => 'h',
                'longPrefix' => 'help',
                'description' => 'Prints a usage statement',
                'noValue' => true,
            ],
            'version' => [
                'prefix' => 'v',
                'longPrefix' => 'version',
                'description' => 'Prints installed version',
                'noValue' => true,
            ],
            'ignore' => [
                'prefix' => 'i',
                'longPrefix' => 'ignore',
                'description' => 'Ignore a comma-separated list of directories',
                'castTo' => 'string',
                'defaultValue' => 'vendor,tests,features,spec',
            ],
            'format' => [
                'prefix' => 'f',
                'longPrefix' => 'format',
                'description' => 'Output format',
                'castTo' => 'string',
                'defaultValue' => 'text',
            ],
            'git-diff' => [
                'prefix' => 'g',
                'longPrefix' => 'git-diff',
                'description' => 'Limit to files and lines changed between two '
                                . 'commits or branches, e.g., "master..other".',
                'castTo' => 'string',
            ],
            'path' => [
                'description' => 'File or directory path to analyze',
                'defaultValue' => '.',
            ],
        ];
    }

    /**
     * Get a list of paths to be ignored by the analysis.
     * @return string[] a list of file and/or directory paths.
     */
    public function getIgnoredPaths() : array
    {
        $ignoredArgument = $this->getArgumentValue('ignore');
        $ignoredPaths = explode(',', $ignoredArgument);
        return array_filter($ignoredPaths);
    }

    /**
     * Parse a string of comma separated files and/or directories to be analysed.
     * @param string $pathsString the path argument value.
     * @return void
     */
    protected function setAnalysedPathsFromString(string $pathsString) : void
    {
        $rawAnalysedPaths = explode(',', $pathsString);
        $analysedPaths = array_filter($rawAnalysedPaths);
        $this->setAnalysedPaths(array_filter($rawAnalysedPaths));
    }

    /**
     * Set target files and/or directories to be analysed. Fix relative paths.
     * @param string[] $paths target paths.
     * @return void
     */
    protected function setAnalysedPaths(array $paths) : void
    {
        foreach ($paths as &$path) {
            if (0 === strpos($path, DIRECTORY_SEPARATOR)) {
                continue;
            }
            $path = $this->getWorkingDirectory() . DIRECTORY_SEPARATOR . $path;
        }
        $this->analysedPaths = $paths;
    }

    /**
     * Analysis target paths.
     * @return string[] a list of analysed paths (usually just one).
     */
    public function getAnalysedPaths() : array
    {
        return $this->analysedPaths;
    }

    /**
     * Running script path.
     * @return string current script directory.
     */
    public function getWorkingDirectory() : string
    {
        return getcwd();
    }

    /**
     * Output format.
     * @return string format type.
     */
    public function getOutputFormat() : string
    {
        return $this->getArgumentValue('format');
    }

    /**
     * CLI output description.
     * @return string description.
     */
    public function getDescription() : string
    {
        return 'PHP Hound ' . Analyser::VERSION;
    }

    /**
     * Analyser instance.
     * @return Analyser instance.
     */
    public function getAnalyser() : Analyser
    {
        if (null === $this->analyser) {
            $this->analyser = new Analyser(
                $this->getOutput(),
                $this->binariesPath,
                $this->getAnalysedPaths(),
                $this->getIgnoredPaths()
            );
        }
        return $this->analyser;
    }

    /**
     * List of output format classes.
     * @return array array where the key is a format and its value the class.
     */
    protected function getOutputFormatClasses() : array
    {
        return [
            'text' => 'phphound\output\TextOutput',
            'json' => 'phphound\output\JsonOutput',
            'xml' => 'phphound\output\XmlOutput',
            'csv' => 'phphound\output\CsvOutput',
            'html' => 'phphound\output\HtmlOutput',
        ];
    }

    /**
     * Get argument value from user informed arguments.
     * @param string $name argument name.
     * @return Mixed argument value.
     */
    protected function getArgumentValue(string $name)
    {
        return $this->cli->arguments->get($name);
    }

    /**
     * Check if the user supplied an argument.
     * @param string $name argument name.
     * @return boolean if the argument has informed or not.
     */
    protected function hasArgumentValue(string $name) : bool
    {
        return $this->cli->arguments->defined($name, $this->arguments);
    }
}
