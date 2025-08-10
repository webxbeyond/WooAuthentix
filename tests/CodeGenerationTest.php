<?php
use PHPUnit\Framework\TestCase;

class CodeGenerationTest extends TestCase {
    public function test_code_generation_unique() {
        if ( ! function_exists('wc_apc_generate_batch_codes') ) {
            $this->markTestSkipped('Plugin not loaded / environment incomplete.');
        }
        $codes = wc_apc_generate_batch_codes(12345, 50);
        $this->assertCount(50, $codes, 'Should generate requested number of codes');
        $this->assertCount(50, array_unique($codes), 'Codes should be unique');
        foreach($codes as $c){
            $this->assertMatchesRegularExpression('/^[A-F0-9]+$/', $c, 'Code must be uppercase hex');
        }
    }
}
