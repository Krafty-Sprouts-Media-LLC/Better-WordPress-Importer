<?php
/**
 * Tests for the core WXR import behaviour, using a real WXR fixture.
 *
 * @package WordPress_Importer
 */

class ImportTest extends WP_UnitTestCase {
	/**
	 * Path to the small fixture WXR file used by these tests.
	 *
	 * @var string
	 */
	protected $fixture_file;

	public function set_up() {
		parent::set_up();

		$this->fixture_file = dirname( __FILE__ ) . '/data/small-export.xml';
	}

	/**
	 * Build an importer instance with a working (non-null) logger, since
	 * WXR_Importer calls $this->logger directly without a null check.
	 *
	 * @param array $options Options passed through to WXR_Importer.
	 * @return WXR_Importer
	 */
	protected function get_importer( $options = array() ) {
		$importer = new WXR_Importer( $options );
		$importer->set_logger( new WP_Importer_Logger() );

		return $importer;
	}

	public function test_import_creates_posts_and_page() {
		$importer = $this->get_importer();
		$result = $importer->import( $this->fixture_file );

		$this->assertNotWPError( $result );

		$post = get_page_by_path( 'hello-world', OBJECT, 'post' );
		$this->assertNotNull( $post );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertStringContainsString( 'first fixture post', $post->post_content );

		$page = get_page_by_path( 'sample-page', OBJECT, 'page' );
		$this->assertNotNull( $page );
	}

	public function test_import_creates_postmeta() {
		$importer = $this->get_importer();
		$importer->import( $this->fixture_file );

		$post = get_page_by_path( 'hello-world', OBJECT, 'post' );
		$this->assertSame( 'fixture-value', get_post_meta( $post->ID, '_fixture_meta_key', true ) );
	}

	public function test_import_creates_category_term() {
		$importer = $this->get_importer();
		$importer->import( $this->fixture_file );

		$post = get_page_by_path( 'hello-world', OBJECT, 'post' );
		$terms = wp_get_post_terms( $post->ID, 'category', array( 'fields' => 'slugs' ) );
		$this->assertContains( 'fixtures', $terms );
	}

	public function test_import_creates_comment() {
		$importer = $this->get_importer();
		$importer->import( $this->fixture_file );

		$post = get_page_by_path( 'hello-world', OBJECT, 'post' );
		$comments = get_comments( array( 'post_id' => $post->ID ) );
		$this->assertCount( 1, $comments );
		$this->assertSame( 'This is a fixture comment.', $comments[0]->comment_content );
	}

	/**
	 * Re-running an import against the same file must not create
	 * duplicate posts or comments — see WXR_Importer::post_exists().
	 */
	public function test_reimporting_does_not_duplicate_content() {
		$importer_one = $this->get_importer();
		$importer_one->import( $this->fixture_file );

		$importer_two = $this->get_importer();
		$importer_two->import( $this->fixture_file );

		$posts = get_posts( array(
			'post_type'   => array( 'post', 'page' ),
			'post_status' => 'publish',
			'numberposts' => -1,
			'title'       => 'Hello World',
		) );
		$this->assertCount( 1, $posts );

		$post = get_page_by_path( 'hello-world', OBJECT, 'post' );
		$comments = get_comments( array( 'post_id' => $post->ID ) );
		$this->assertCount( 1, $comments );
	}

	/**
	 * WordPress.com-style exports omit channel-level <wp:category>/<wp:tag>
	 * nodes and only list terms on each <item>. Those must still be found
	 * or created, then assigned — otherwise every post lands in Uncategorized.
	 */
	public function test_item_level_terms_are_created_and_assigned() {
		$importer = $this->get_importer();
		$result = $importer->import( dirname( __FILE__ ) . '/data/item-only-terms.xml' );

		$this->assertNotWPError( $result );

		$post = get_page_by_path( 'yamaha-golf-carts', OBJECT, 'post' );
		$this->assertNotNull( $post );

		$categories = wp_get_post_terms( $post->ID, 'category', array( 'fields' => 'slugs' ) );
		$this->assertContains( 'vehicles', $categories );
		$this->assertNotContains( 'uncategorized', $categories );

		$tags = wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'slugs' ) );
		$this->assertContains( 'golf-carts', $tags );
	}

	/**
	 * Re-importing must attach missing terms to posts that already exist
	 * (the first pass of a WordPress.com-style file left them Uncategorized).
	 */
	public function test_reimport_assigns_terms_to_existing_uncategorized_posts() {
		$existing_id = wp_insert_post(
			array(
				'post_title'  => 'Yamaha Golf Carts',
				'post_name'   => 'yamaha-golf-carts',
				'post_status' => 'publish',
				'post_type'   => 'post',
				'guid'        => 'https://example.test/?p=187605',
			)
		);
		$this->assertGreaterThan( 0, $existing_id );
		wp_set_post_terms( $existing_id, array( 'uncategorized' ), 'category' );

		$importer = $this->get_importer();
		$importer->import( dirname( __FILE__ ) . '/data/item-only-terms.xml' );

		$categories = wp_get_post_terms( $existing_id, 'category', array( 'fields' => 'slugs' ) );
		$this->assertContains( 'vehicles', $categories );
		$this->assertNotContains( 'uncategorized', $categories );

		$tags = wp_get_post_terms( $existing_id, 'post_tag', array( 'fields' => 'slugs' ) );
		$this->assertContains( 'golf-carts', $tags );
	}
}
