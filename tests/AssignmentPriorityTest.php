<?php
/**
 * Test product-first vs generic fallback assignment ordering.
 */
class AssignmentPriorityTest extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();
		wooauthentix_run_migrations('test');
	}

	public function test_product_specific_preferred_before_generic() {
		global $wpdb; $table=$wpdb->prefix.WC_APC_TABLE;
		// Create product and generate one product-specific + one generic code
		$product_id = self::factory()->product->create();
		wc_apc_generate_batch_codes($product_id,1); // product-specific
		wc_apc_generate_batch_codes(null,1); // generic
		// Create order with qty=1 for product
		$order_id = self::factory()->order->create();
		$order = wc_get_order($order_id);
		$order->add_product( wc_get_product($product_id), 1 );
		$order->save();
		WC_APC_Codes_Module::assign_on_order($order_id);
		// After assignment, product-specific code should be status=1; generic remains unassigned
		$product_specific_assigned = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE product_id=%d AND status=1", $product_id));
		$generic_remaining = (int)$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE product_id IS NULL AND status=0");
		$this->assertEquals(1,$product_specific_assigned,'Product-specific code should be assigned first.');
		$this->assertEquals(1,$generic_remaining,'Generic code should remain unassigned.');
	}
}
