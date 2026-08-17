<?php
/**
 * Tests for mapping WXR authors onto existing site users.
 *
 * @package WordPress_Importer
 */

class WXR_Import_UI_Author_Double extends WXR_Import_UI {
	/**
	 * Expose find_existing_user() for assertions.
	 *
	 * @param array $wxr_user WXR user item.
	 * @return WP_User|null
	 */
	public function match( $wxr_user ) {
		return $this->find_existing_user( $wxr_user );
	}
}

class AuthorMappingTest extends WP_UnitTestCase {
	/**
	 * @var WXR_Import_UI_Author_Double
	 */
	protected $ui;

	public function set_up() {
		parent::set_up();
		$this->ui = new WXR_Import_UI_Author_Double();
	}

	public function test_matches_existing_user_by_email() {
		$user_id = self::factory()->user->create(
			array(
				'user_login' => 'different-login',
				'user_email' => 'Harvestgreat4@gmail.com',
			)
		);

		$found = $this->ui->match(
			array(
				'data' => array(
					'user_login' => 'kola',
					'user_email' => 'harvestgreat4@gmail.com',
				),
			)
		);

		$this->assertInstanceOf( 'WP_User', $found );
		$this->assertSame( $user_id, $found->ID );
	}

	public function test_matches_existing_user_by_login_when_email_differs() {
		$user_id = self::factory()->user->create(
			array(
				'user_login' => 'iamkingsleyf',
				'user_email' => 'on-this-site@example.test',
			)
		);

		$found = $this->ui->match(
			array(
				'data' => array(
					'user_login' => 'iamkingsleyf',
					'user_email' => 'kingsleyfelix9@gmail.com',
				),
			)
		);

		$this->assertInstanceOf( 'WP_User', $found );
		$this->assertSame( $user_id, $found->ID );
	}

	public function test_email_match_wins_over_a_different_login_match() {
		$email_user = self::factory()->user->create(
			array(
				'user_login' => 'email-owner',
				'user_email' => 'shared@example.test',
			)
		);
		self::factory()->user->create(
			array(
				'user_login' => 'wxr-login',
				'user_email' => 'other@example.test',
			)
		);

		$found = $this->ui->match(
			array(
				'data' => array(
					'user_login' => 'wxr-login',
					'user_email' => 'shared@example.test',
				),
			)
		);

		$this->assertInstanceOf( 'WP_User', $found );
		$this->assertSame( $email_user, $found->ID );
	}

	public function test_returns_null_when_nobody_matches() {
		$found = $this->ui->match(
			array(
				'data' => array(
					'user_login' => 'brand-new-author',
					'user_email' => 'nobody-here@example.test',
				),
			)
		);

		$this->assertNull( $found );
	}
}
