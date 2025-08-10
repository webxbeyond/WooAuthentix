<?php
/**
 * Migration Tests
 */
class MigrationTest extends WP_UnitTestCase {
	public function test_product_id_nullable_migration() {
		global $wpdb; $table=$wpdb->prefix.WC_APC_TABLE;
		// Force run migrations assuming previous version
		update_option('wooauthentix_db_version','2.0.0');
		wooauthentix_run_migrations('upgrade');
		$col=$wpdb->get_row($wpdb->prepare("SHOW COLUMNS FROM $table LIKE %s",'product_id'));
		$this->assertNotEmpty($col,'product_id column should exist');
		$this->assertStringContainsString('YES',$col->Null,'product_id should be nullable after migration');
	}
}
