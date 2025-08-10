<?php
use PHPUnit\Framework\TestCase;

/**
 * @group utils
 */
class UtilsHelpersTest extends TestCase {
	public function test_parse_codes_meta_handles_array_and_string() {
		$rawArray = ['abc','DEF','abc'];
		$out = wc_apc_parse_codes_meta($rawArray);
		$this->assertSame(['ABC','DEF'], $out, 'Array normalization failed');

		$rawString = 'abc, Def , ghi';
		$out2 = wc_apc_parse_codes_meta($rawString);
		$this->assertSame(['ABC','DEF','GHI'], $out2, 'String parsing failed');

		$this->assertSame([], wc_apc_parse_codes_meta(null));
	}

	public function test_set_and_get_item_codes_round_trip() {
		if( ! class_exists('WC_Product_Simple') ){
			$this->markTestSkipped('WooCommerce not loaded in test environment.');
		}
		// Create a product + order + item.
		$order = wc_create_order();
		$product = new WC_Product_Simple();
		$product->set_name('Helper Test');
		$product->set_regular_price('9.99');
		$product->save();
		$item_id = $order->add_product($product, 1);
		$item = $order->get_item($item_id);
		wc_apc_set_item_codes($item, ['abc','def']);
		$codes = wc_apc_get_item_codes($item);
		$this->assertSame(['ABC','DEF'], $codes);
	}
}
