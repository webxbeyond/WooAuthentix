<?php
use PHPUnit\Framework\TestCase;

class LifecycleTest extends TestCase {
    public function test_assignment_and_verification_flow() {
        if ( ! function_exists('wc_apc_generate_batch_codes') ) {
            $this->markTestSkipped('Plugin functions unavailable.');
        }
        // Generate generic codes (product_id null)
        $codes = wc_apc_generate_batch_codes(null, 5);
        $this->assertNotEmpty($codes);
        $code = $codes[0];
        // Attempt verification before assignment (should fail unless preprinted mode enabled)
        $res1 = wc_apc_perform_verification($code, 'test');
        $this->assertFalse($res1['success'], 'Unassigned code should not verify in normal mode.');
    }
}
