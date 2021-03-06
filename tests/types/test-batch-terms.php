<?php

namespace Rkv\Locomotive\Tests;

use WP_UnitTestCase;
use Rkv\Locomotive\Batches\Terms;

class TermBatchTest extends WP_UnitTestCase {
	/**
	 * Tear down.
	 */
	public function tearDown() {
		parent::tearDown();
		locomotive_clear_existing_batches();
	}

	/**
	 * Tear down.
	 */
	public function setUp() {
		parent::setUp();
		$this->terms = $this->factory->category->create_many( 5 );
		$this->tags = $this->factory->tag->create_many( 5 );
	}


	/**
	 * Test that term batch gets run.
	 */
	public function test_term_finished_run() {

		$term_batch = new Terms();

		$term_batch->register( array(
			'name'     => 'Hey there',
			'type'     => 'term',
			'callback' => __NAMESPACE__ . '\\my_term_callback_function_test',
			'args'     => array(
				'number' => 10,
				'taxonomy' => 'category',
				'hide_empty' => false,
			),
		) );

		$batch_status = get_option( 'loco_batch_' . $term_batch->slug );
		$this->assertFalse( $batch_status );

		$term_batch->run( 1 );

		$batch_status = get_option( 'loco_batch_' . $term_batch->slug );
		$this->assertEquals( 'finished', $batch_status['status'] );

		// Loop through each post and make sure our value was set.
		foreach ( $this->terms as $term ) {
			$meta = get_term_meta( $term, 'custom-key', true );
			$this->assertEquals( 'my-value', $meta );

			$status = get_term_meta( $term, $term_batch->slug . '_status', true );
			$this->assertEquals( 'success', $status );
		}

		// Run again so it skips some.
		$term_batch->run( 1 );
	}

	/**
	 * Test that you can clear individual result status.
	 */
	public function test_clear_term_result_status() {

		$term_batch = new Terms();

		$term_batch->register( array(
			'name'     => 'Hey there',
			'type'     => 'term',
			'callback' => __NAMESPACE__ . '\\my_term_callback_function_test',
			'args'     => array(
				'number' => 5,
				'taxonomy' => 'post_tag',
				'hide_empty' => false,
			),
		) );

		$term_batch->run( 1 );

		$term_batch->clear_result_status();

		// Loop through each post and make sure our value was set.
		foreach ( $this->tags as $term ) {
			$meta = get_term_meta( $term, 'custom-key', true );
			$this->assertEquals( 'my-value', $meta );

			$status = get_term_meta( $term, $term_batch->slug . '_status', true );
			$this->assertEquals( '', $status );
		}

		$batches = locomotive_get_all_batches();
		$this->assertEquals( 'reset', $batches['hey-there']['status'] );
	}

	/**
	 * Test that terms offset batch gets run.
	 */
	public function test_terms_offset_run() {

		$term_batch = new Terms();

		$term_batch->register( array(
			'name'     => 'Hey there',
			'type'     => 'term',
			'callback' => __NAMESPACE__ . '\\my_term_callback_function_test',
			'args'     => array(
				'number' => 3,
				'offset' => 5,
				'taxonomy' => 'category',
				'hide_empty' => false,
			),
		) );

		$batch_status = get_option( 'loco_batch_' . $term_batch->slug );
		$this->assertFalse( $batch_status );

		$term_batch->run( 2 );

		$batch_status = get_option( 'loco_batch_' . $term_batch->slug );
		$this->assertEquals( 'finished', $batch_status['status'] );
	}

	/**
	 * Test that batch gets run when data is destroyed during process.
	 */
	public function test_run_with_destructive_callback() {

		$term_batch = new Terms();

		$term_batch->register( array(
			'name'     => 'Hey there',
			'type'     => 'term',
			'callback' => __NAMESPACE__ . '\\my_callback_delete_term',
			'args'     => array(
				'number' => 3,
				'taxonomy' => 'post_tag',
				'hide_empty' => false,
			),
		) );

		// Simulate running twice with pass back of results number from client.
		$term_batch->run( 1 );
		$_POST['total_num_results'] = 5;
		$term_batch->run( 2 );

		// Check that all terms have been deleted.
		$all_terms = get_terms( array( 'taxonomy' => 'post_tag', 'hide_empty' => false, 'number' => 0 ) );
		$this->assertCount( 0, $all_terms );

		// Ensure that we are still getting a finished message.
		$batch_status = get_option( 'loco_batch_' . $term_batch->slug );
		$this->assertEquals( 'finished', $batch_status['status'] );
	}

}

/**
 * My callback function test for terms.
 *
 * @param WP_Term $result Result item.
 */
function my_term_callback_function_test( $result ) {
	update_term_meta( $result->term_id, 'custom-key', 'my-value' );
}

/**
 * Callback function with destructive action (deletion).
 *
 * @param  WP_Term $result Result item.
 */
function my_callback_delete_term( $result ) {
	wp_delete_term( $result->term_id, 'post_tag' );
}
