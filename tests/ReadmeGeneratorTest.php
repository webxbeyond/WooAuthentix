<?php
use PHPUnit\Framework\TestCase;

class ReadmeGeneratorTest extends TestCase {
    public function testGeneratorProducesStableTag() {
        $script = __DIR__.'/../tools/generate-wp-readme.php';
        $this->assertFileExists($script,'Generator script missing');
        $output = shell_exec('php '.escapeshellarg($script));
        $this->assertNotEmpty($output,'Generator output empty');
        $this->assertStringContainsString('Stable tag:', $output,'Stable tag line missing');
        // ensure dynamic version matches plugin header constant
        $main = file_get_contents(dirname(__DIR__).'/authentic-checker.php');
        $this->assertNotFalse($main,'Main plugin file missing');
        if (preg_match('/^Version:\s*(.+)$/mi',$main,$m)) {
            $version = trim($m[1]);
            $this->assertStringContainsString($version, $output,'Version mismatch in generated readme');
        }
    }

    public function testChangelogInclusion() {
        $script = __DIR__.'/../tools/generate-wp-readme.php';
        $output = shell_exec('php '.escapeshellarg($script));
        $this->assertStringContainsString('== Changelog ==', $output);
        // Expect at least one version section from changelog
        $this->assertMatchesRegularExpression('/\[\d+\.\d+\.\d+\]/',$output,'Version section not found in changelog snippet');
    }
}
