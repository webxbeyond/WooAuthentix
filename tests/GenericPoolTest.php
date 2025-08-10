<?php
/**
 * Generic Pool Tests
 */
class GenericPoolTest extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();
		// Ensure migration run
		wooauthentix_run_migrations('test');
	}

	public function test_generate_generic_codes() {
		$codes = wc_apc_generate_batch_codes(null,5);
		$this->assertCount(5,$codes,'Should generate 5 generic codes');
		global $wpdb; $table=$wpdb->prefix.WC_APC_TABLE;
		$rows=$wpdb->get_results("SELECT product_id,status FROM $table WHERE code IN (".implode(',',array_map(fn($c)=>'\''.$c.'\'',$codes)).")");
		foreach($rows as $r){
			$this->assertNull($r->product_id,'Generic code product_id must be NULL');
			$this->assertEquals(0,$r->status,'Status should be unassigned');
		}
	}

	public function test_assignment_tags_generic_code() {
		// Create product
		$product_id = self::factory()->product->create();
		// Generate one generic code
		wc_apc_generate_batch_codes(null,1);
		$order_id = self::factory()->order->create();
		$order = wc_get_order($order_id);
		$order->add_product( wc_get_product($product_id), 1 );
		$order->save();
		// Simulate processing/completed
		WC_APC_Codes_Module::assign_on_order($order_id);
		global $wpdb; $table=$wpdb->prefix.WC_APC_TABLE;
		$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE product_id=%d AND status=1 ORDER BY id DESC LIMIT 1", $product_id));
		$this->assertNotEmpty($row,'Generic code should have been claimed and tagged');
		$this->assertEquals($product_id,(int)$row->product_id,'Should be tagged to product');
		$this->assertEquals(1,(int)$row->status,'Should be assigned status=1');
	}
}
