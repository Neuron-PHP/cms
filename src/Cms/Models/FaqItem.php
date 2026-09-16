<?php

namespace Neuron\Cms\Models;

use DateTimeImmutable;
use Exception;
use Neuron\Orm\Model;
use Neuron\Orm\Attributes\{Table, BelongsTo};

/**
 * A question and answer in a named FAQ group.
 *
 * @package Neuron\Cms\Models
 */
#[Table('faq_items')]
class FaqItem extends Model
{
	private ?int $_id = null;
	private int $_faqId = 0;
	private string $_question = '';
	private string $_answer = '';
	private int $_sortOrder = 0;
	private ?DateTimeImmutable $_createdAt = null;
	private ?DateTimeImmutable $_updatedAt = null;

	#[BelongsTo(Faq::class, foreignKey: 'faq_id')]
	private ?Faq $_faq = null;

	public function __construct()
	{
		$this->_createdAt = new DateTimeImmutable();
	}

	public function getId(): ?int
	{
		return $this->_id;
	}

	public function setId( int $id ): self
	{
		$this->_id = $id;
		return $this;
	}

	public function getFaqId(): int
	{
		return $this->_faqId;
	}

	public function setFaqId( int $faqId ): self
	{
		$this->_faqId = $faqId;
		return $this;
	}

	public function getQuestion(): string
	{
		return $this->_question;
	}

	public function setQuestion( string $question ): self
	{
		$this->_question = $question;
		return $this;
	}

	public function getAnswer(): string
	{
		return $this->_answer;
	}

	public function setAnswer( string $answer ): self
	{
		$this->_answer = $answer;
		return $this;
	}

	public function getSortOrder(): int
	{
		return $this->_sortOrder;
	}

	public function setSortOrder( int $sortOrder ): self
	{
		$this->_sortOrder = $sortOrder;
		return $this;
	}

	public function getCreatedAt(): ?DateTimeImmutable
	{
		return $this->_createdAt;
	}

	public function setCreatedAt( DateTimeImmutable $createdAt ): self
	{
		$this->_createdAt = $createdAt;
		return $this;
	}

	public function getUpdatedAt(): ?DateTimeImmutable
	{
		return $this->_updatedAt;
	}

	public function setUpdatedAt( ?DateTimeImmutable $updatedAt ): self
	{
		$this->_updatedAt = $updatedAt;
		return $this;
	}

	public function getFaq(): ?Faq
	{
		return $this->_faq;
	}

	public function setFaq( ?Faq $faq ): self
	{
		$this->_faq = $faq;
		return $this;
	}

	/**
	 * @param array<string, mixed> $data
	 * @throws Exception
	 */
	public static function fromArray( array $data ): static
	{
		$item = new self();

		if( isset( $data['id'] ) )
		{
			$item->setId( (int) $data['id'] );
		}

		$item->setFaqId( (int) ( $data['faq_id'] ?? 0 ) );
		$item->setQuestion( $data['question'] ?? '' );
		$item->setAnswer( $data['answer'] ?? '' );
		$item->setSortOrder( (int) ( $data['sort_order'] ?? 0 ) );

		if( isset( $data['created_at'] ) && $data['created_at'] )
		{
			$item->setCreatedAt(
				is_string( $data['created_at'] )
					? new DateTimeImmutable( $data['created_at'] )
					: $data['created_at']
			);
		}

		if( isset( $data['updated_at'] ) && $data['updated_at'] )
		{
			$item->setUpdatedAt(
				is_string( $data['updated_at'] )
					? new DateTimeImmutable( $data['updated_at'] )
					: $data['updated_at']
			);
		}

		return $item;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'id' => $this->_id,
			'faq_id' => $this->_faqId,
			'question' => $this->_question,
			'answer' => $this->_answer,
			'sort_order' => $this->_sortOrder,
			'created_at' => $this->_createdAt?->format( 'Y-m-d H:i:s' ),
			'updated_at' => $this->_updatedAt?->format( 'Y-m-d H:i:s' ),
		];
	}
}
