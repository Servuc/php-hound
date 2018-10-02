<?php
namespace phphound\integration;

use phphound\AnalysisResult;
use phphound\helper\ArrayHelper;
use Sabre\Xml\Reader;

/**
 * Integration of PHPHound with PHPCodeSniffer.
 * @see https://github.com/squizlabs/PHP_CodeSniffer
 */
class PHPCodeSniffer extends AbstractIntegration
{
    /**
     * @inheritdoc
     */
    public function getDescription() : string
    {
        return 'PHPCodeSniffer';
    }

    /**
     * @inheritdoc
     */
    public function getIgnoredArgument() : string
    {
        if (!empty($this->ignoredPaths)) {
            return '--ignore=' . implode(',', $this->ignoredPaths) . ' ';
        }
        return '';
    }

    /**
     * @inheritdoc
     */
    public function getCommand(array $targetPaths) : string
    {
        return $this->binariesPath . 'phpcs -p --standard=PSR2 --report=xml '
            . $this->getIgnoredArgument() . '--report-file="'
            . $this->temporaryFilePath . '" ' . implode(' ', $targetPaths);
    }

    /**
     * @inheritdoc
     */
    protected function addIssuesFromXml(Reader $xml) : void
    {
        $xmlArray = $xml->parse();

        foreach ((array) $xmlArray['value'] as $fileTag) {
            if ($fileTag['name'] != '{}file') {
                continue;
            }

            $fileName = $fileTag['attributes']['name'];

            foreach ((array) $fileTag['value'] as $issueTag) {
                $line = $issueTag['attributes']['line'];
                $tool = 'PHPCodeSniffer';
                $type = $issueTag['attributes']['source'];
                $message = $issueTag['value'];

                $this->result->addIssue($fileName, $line, $tool, $type, $message);
            }
        }
    }
}
