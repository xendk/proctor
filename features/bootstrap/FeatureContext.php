<?php

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Defines application features from the specific context.
 */
class FeatureContext implements Context, SnippetAcceptingContext
{
    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     */
    public function __construct()
    {
    }

    /**
     * Cleans test folders in the temporary directory.
     *
     * @BeforeSuite
     * @AfterSuite
     */
    public static function cleanTestFolders()
    {
        if (is_dir($dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'proctor')) {
            self::clearDirectory($dir);
        }
    }

    /**
     * Prepares test folders in the temporary directory.
     *
     * @BeforeScenario
     */
    public function prepareTestFolders()
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'proctor' . DIRECTORY_SEPARATOR .
             md5(microtime() * rand(0, 10000));

        mkdir($dir . '/home', 0777, true);
        mkdir($dir . '/workdir', 0777, true);

        $phpFinder = new PhpExecutableFinder();
        if (false === $php = $phpFinder->find()) {
            throw new \RuntimeException('Unable to find the PHP executable.');
        }
        $this->homeDir = $dir . '/home';
        $this->workingDir = $dir . '/workdir';
        $this->phpBin = $php;
        $this->process = new Process(null);
        // Set the env variable for home.
        $this->process->setEnv(array('HOME' => $dir . '/home'));
    }

    private function getOutput()
    {
        $output = $this->process->getErrorOutput() . $this->process->getOutput();

        // Normalize the line endings in the output
        if ("\n" !== PHP_EOL) {
            $output = str_replace(PHP_EOL, "\n", $output);
        }

        return trim(preg_replace("/ +$/m", '', $output));
    }

    private static function clearDirectory($path)
    {
        $files = scandir($path);
        array_shift($files);
        array_shift($files);

        foreach ($files as $file) {
            $file = $path . DIRECTORY_SEPARATOR . $file;
            if (is_dir($file)) {
                self::clearDirectory($file);
            } else {
                unlink($file);
            }
        }

        rmdir($path);
    }

    private function getExitCode()
    {
        return $this->process->getExitCode();
    }

    /**
     * Run proctor with the provided arguments.
     *
     * @When /^I run "proctor(?: ((?:\"|[^"])*))?"$/
     */
    public function iRun($arguments)
    {
        $this->process->setWorkingDirectory($this->workingDir);
        $this->process->setCommandLine(
            sprintf(
                '%s %s %s',
                $this->phpBin,
                escapeshellarg(realpath('proctor')),
                $arguments
            )
        );

        $env = $this->process->getEnv();
        // Don't reset the LANG variable on HHVM, because it breaks HHVM itself
        if (!defined('HHVM_VERSION')) {
            $env['LANG'] = 'en'; // Ensures that the default language is en, whatever the OS locale is.
        }
        $env['TESTLOG'] = $this->workingDir . '/test.log';
        $this->process->setEnv($env);

        $this->process->start();
        $this->process->wait();

        // Check that PHP didn't throw any notices or other cruft in the
        // output.
        if (preg_match('/(PHP Notice|PHP Stack trace:)/', $this->getOutput())) {
            throw new \RuntimeException('PHP cruft in output\n' . $this->getOutput());
        }
    }

    /**
     * Check that the output contains the provided string.
     *
     * @Then the output should contain:
     */
    public function theOutputShouldContain2(PyStringNode $string)
    {
        $this->theOutputShouldContain((string) $string);
    }

    /**
     * Check that the output contains the provided string.
     *
     * @Then the output should contain :string
     */
    public function theOutputShouldContain($string)
    {
        PHPUnit_Framework_Assert::assertContains((string) $string, $this->getOutput());
    }

    /**
     * Checks whether specified file exists and contains specified string.
     *
     * @Then /^"([^"]*)" should contain( the string|):$/
     */
    public function shouldContain($path, $subString, PyStringNode $string)
    {
        $subString = !empty($subString);
        if ($path[0] == '~') {
            $path = $this->homeDir . ltrim($path, '~');
        } else {
            $path = $this->workingDir . '/' . $path;
        }
        PHPUnit_Framework_Assert::assertFileExists($path);

        $fileContent = trim(file_get_contents($path));
        // Normalize the line endings in the output
        if ("\n" !== PHP_EOL) {
            $fileContent = str_replace(PHP_EOL, "\n", $fileContent);
        }

        // Trim trailing whitespace. It usually messes things up.
        $fileContent = trim(preg_replace("/ +$/m", '', $fileContent));

        if ($subString) {
            PHPUnit_Framework_Assert::assertContains((string) $string, $fileContent);
        } else {
            PHPUnit_Framework_Assert::assertEquals((string) $string, $fileContent);
        }
    }

     /**
     * @Given :path contains:
     */
    public function contains($path, PyStringNode $string)
    {
        if ($path[0] == '~') {
            $path = $this->homeDir . ltrim($path, '~');
        } else {
            $path = $this->workingDir . '/' . $path;
        }

        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, (string) $string);
    }

    /**
     * Checks whether previously ran command passes|fails with provided output.
     *
     * @Then /^it should (fail|pass) with "([^"]*)"$/
     *
     * @param   string       $success "fail" or "pass"
     * @param   PyStringNode $text    PyString text instance
     */
    public function itShouldPassWith($success, $text)
    {
        $this->itShouldFail($success);
        $this->theOutputShouldContain((string) $text);
    }

    /**
     * Checks whether previously ran command passes|fails with provided output.
     *
     * @Then /^it should (fail|pass) with:$/
     *
     * @param   string       $success "fail" or "pass"
     * @param   PyStringNode $text    PyString text instance
     */
    public function itShouldPassWith2($success, PyStringNode $text)
    {
        $this->itShouldPassWith($success, (string) $text);
    }

    /**
     * Checks whether previously ran command failed|passed.
     *
     * @Then /^it should (fail|pass)$/
     *
     * @param   string $success "fail" or "pass"
     */
    public function itShouldFail($success)
    {
        if ('fail' === $success) {
            if (0 === $this->getExitCode()) {
                echo 'Actual output:' . PHP_EOL . PHP_EOL . $this->getOutput();
            }

            PHPUnit_Framework_Assert::assertNotEquals(0, $this->getExitCode());
        } else {
            if (0 !== $this->getExitCode()) {
                echo 'Actual output:' . PHP_EOL . PHP_EOL . $this->getOutput();
            }

            PHPUnit_Framework_Assert::assertEquals(0, $this->getExitCode());
        }
    }
}
