<?php

namespace Tests\Unit\Cms\Services\Contact;

use Neuron\Cms\Services\Contact\ContactFormValidator;
use PHPUnit\Framework\TestCase;

class ContactFormValidatorTest extends TestCase
{
	private ContactFormValidator $validator;

	protected function setUp(): void
	{
		parent::setUp();
		$this->validator = new ContactFormValidator();
	}

	private function fields(): array
	{
		return [
			[ 'name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'rules' => [ 'length' => [ 'min' => 2, 'max' => 10 ] ] ],
			[ 'name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true ],
			[ 'name' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'required' => false ],
			[ 'name' => 'county', 'label' => 'County', 'type' => 'select', 'required' => true, 'options' => [ 'Sarasota', 'Manatee' ] ],
			[ 'name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'required' => false ]
		];
	}

	public function testValidSubmissionReturnsNoErrors(): void
	{
		$errors = $this->validator->validate( $this->fields(), [
			'name'   => 'Alice',
			'email'  => 'alice@example.com',
			'phone'  => '(941) 555-1234',
			'county' => 'Sarasota'
		] );

		$this->assertSame( [], $errors );
	}

	public function testRequiredFieldsAreFlaggedWhenEmpty(): void
	{
		$errors = $this->validator->validate( $this->fields(), [
			'name'   => '',
			'email'  => '',
			'county' => ''
		] );

		$this->assertArrayHasKey( 'name', $errors );
		$this->assertArrayHasKey( 'email', $errors );
		$this->assertArrayHasKey( 'county', $errors );
	}

	public function testOptionalEmptyFieldsAreSkipped(): void
	{
		$errors = $this->validator->validate( $this->fields(), [
			'name'   => 'Bob',
			'email'  => 'bob@example.com',
			'county' => 'Manatee',
			'phone'  => '',
			'notes'  => ''
		] );

		$this->assertSame( [], $errors );
	}

	public function testInvalidEmailIsRejected(): void
	{
		$errors = $this->validator->validate( $this->fields(), [
			'name'   => 'Bob',
			'email'  => 'not-an-email',
			'county' => 'Manatee'
		] );

		$this->assertArrayHasKey( 'email', $errors );
	}

	public function testLengthRuleIsEnforced(): void
	{
		$errors = $this->validator->validate( $this->fields(), [
			'name'   => 'A',
			'email'  => 'a@example.com',
			'county' => 'Manatee'
		] );

		$this->assertArrayHasKey( 'name', $errors );
	}

	public function testSelectValueMustBeInOptionSet(): void
	{
		$errors = $this->validator->validate( $this->fields(), [
			'name'   => 'Carol',
			'email'  => 'carol@example.com',
			'county' => 'Hillsborough'
		] );

		$this->assertArrayHasKey( 'county', $errors );
	}

	public function testInvalidPhoneIsRejected(): void
	{
		$errors = $this->validator->validate( $this->fields(), [
			'name'   => 'Dan',
			'email'  => 'dan@example.com',
			'county' => 'Sarasota',
			'phone'  => '123'
		] );

		$this->assertArrayHasKey( 'phone', $errors );
	}

	public function testPatternRuleIsEnforced(): void
	{
		$fields = [
			[ 'name' => 'code', 'label' => 'Code', 'type' => 'text', 'required' => true, 'rules' => [ 'pattern' => '/^[A-Z]{3}$/' ] ]
		];

		$this->assertArrayHasKey( 'code', $this->validator->validate( $fields, [ 'code' => 'ab' ] ) );
		$this->assertSame( [], $this->validator->validate( $fields, [ 'code' => 'ABC' ] ) );
	}
}
