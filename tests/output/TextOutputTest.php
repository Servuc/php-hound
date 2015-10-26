<?php
namespace tests\output;

use phphound\Command;
use phphound\output\TextOutput;

class TextOutputTest extends \PHPUnit_Framework_TestCase
{
    /** @test */
    public function it_outputs_results_correctly()
    {
        $cli = $this->getMockBuilder('League\CLImate\CLImate')
            ->setMethods(['yellowFlank'])
            ->getMock()
        ;
        $result = $this->getMock('phphound\AnalysisResult');
        $output = new TextOutput($cli, sys_get_temp_dir());

        $result->expects($this->once())->method('toArray')->willReturn([
            'File.php' => [
                93 => [['tool' => 'PHP-Hound', 'type' => 'error', 'message' => '   Error trimmed  ']],
            ],
        ]);

        $cli->expects($this->any())->method('yellowFlank')->with('File.php', '=', 2);
        $cli->expects($this->any())->method('cyanInline')->with('93: ');
        $cli->expects($this->any())->method('inline')->with('Error trimmed');

        $output->result($result);
    }

    /** @test */
    public function it_outputs_on_starting_analysis()
    {
        $cli = $this->getMockBuilder('League\CLImate\CLImate')
            ->setMethods(['green'])
            ->getMock()
        ;
        $output = new TextOutput($cli, sys_get_temp_dir());

        $cli->expects($this->once())->method('green')->with('Starting analysis');

        $output->trigger(Command::EVENT_STARTING_ANALYSIS);
    }

    /** @test */
    public function it_outputs_on_starting_tool()
    {
        $cli = $this->getMockBuilder('League\CLImate\CLImate')
            ->setMethods(['inline'])
            ->getMock()
        ;
        $output = new TextOutput($cli, sys_get_temp_dir());
        $message = ['description' => 'Toolname', 'ignoredPaths' => ['vendor', 'tests']];

        $cli->expects($this->at(0))->method('inline')->with('Running Toolname... ');
        $cli->expects($this->at(1))->method('inline')->with('Ignored paths:');
        $cli->expects($this->at(2))->method('inline')->with('     ' . $message['ignoredPaths'][0]);
        $cli->expects($this->at(3))->method('inline')->with('     ' . $message['ignoredPaths'][1]);

        $output->trigger(Command::EVENT_STARTING_TOOL, $message);
    }

    /** @test */
    public function it_outputs_on_finishing_tool()
    {
        $cli = $this->getMockBuilder('League\CLImate\CLImate')
            ->setMethods(['out'])
            ->getMock()
        ;
        $output = new TextOutput($cli, sys_get_temp_dir());

        $cli->expects($this->once())->method('out')->with('Done!');

        $output->trigger(Command::EVENT_FINISHED_TOOL);
    }

    /** @test */
    public function it_outputs_on_finishing_analysis()
    {
        $cli = $this->getMockBuilder('League\CLImate\CLImate')
            ->setMethods(['green'])
            ->getMock()
        ;
        $output = new TextOutput($cli, sys_get_temp_dir());

        $cli->expects($this->once())->method('green')->with('Analysis complete!');

        $output->trigger(Command::EVENT_FINISHED_ANALYSIS);
    }
}
