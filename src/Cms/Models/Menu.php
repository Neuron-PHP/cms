<?php

namespace Neuron\Cms\Models;

use DateTimeImmutable;
use Exception;
use Neuron\Cms\Enums\MenuLocation;
use Neuron\Orm\Model;
use Neuron\Orm\Attributes\{Table, HasMany};
use Neuron\Orm\DependentStrategy;

/**
 * Named navigation menu rendered in the public header or footer.
 *
 * @package Neuron\Cms\Models
 */
#[Table('menus')]
class Menu extends Model
{
	private ?int $_id = null;
	private string $_name = '';
	private string $_slug = '';
	private string $_location = MenuLocation::HEADER->value;
	private ?string $_description = null;
	private ?DateTimeImmutable $_createdAt = null;
	private ?DateTimeImmutable $_updatedAt = null;

	/** @var MenuItem[] */
	#[HasMany(MenuItem::class, foreignKey: 'menu_id', dependent: DependentStrategy::DeleteAll)]
	private array $_items = [];

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

	public function getName(): string
	{
		return $this->_name;
	}

	public function setName( string $name ): self
	{
		$this->_name = $name;
		return $this;
	}

	public function getSlug(): string
	{
		return $this->_slug;
	}

	public function setSlug( string $slug ): self
	{
		$this->_slug = $slug;
		return $this;
	}

	public function getLocation(): string
	{
		return $this->_location;
	}

	public function getLocationMode(): MenuLocation
	{
		return MenuLocation::fromValue( $this->_location );
	}

	public function setLocation( string $location ): self
	{
		$this->_location = MenuLocation::fromValue( $location )->value;
		return $this;
	}

	public function getDescription(): ?string
	{
		return $this->_description;
	}

	public function setDescription( ?string $description ): self
	{
		$this->_description = $description;
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

	/**
	 * @return MenuItem[]
	 */
	public function getItems(): array
	{
		return $this->_items;
	}

	/**
	 * @param MenuItem[] $items
	 */
	public function setItems( array $items ): self
	{
		$this->_items = $items;
		return $this;
	}

	/**
	 * @param array<string, mixed> $data
	 * @throws Exception
	 */
	public static function fromArray( array $data ): static
	{
		$menu = new self();

		if( isset( $data['id'] ) )
		{
			$menu->setId( (int) $data['id'] );
		}

		$menu->setName( $data['name'] ?? '' );
		$menu->setSlug( $data['slug'] ?? '' );
		$menu->setLocation( $data['location'] ?? MenuLocation::HEADER->value );
		$menu->setDescription( $data['description'] ?? null );

		if( isset( $data['created_at'] ) && $data['created_at'] )
		{
			$menu->setCreatedAt(
				is_string( $data['created_at'] )
					? new DateTimeImmutable( $data['created_at'] )
					: $data['created_at']
			);
		}

		if( isset( $data['updated_at'] ) && $data['updated_at'] )
		{
			$menu->setUpdatedAt(
				is_string( $data['updated_at'] )
					? new DateTimeImmutable( $data['updated_at'] )
					: $data['updated_at']
			);
		}

		return $menu;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'id' => $this->_id,
			'name' => $this->_name,
			'slug' => $this->_slug,
			'location' => $this->_location,
			'description' => $this->_description,
			'created_at' => $this->_createdAt?->format( 'Y-m-d H:i:s' ),
			'updated_at' => $this->_updatedAt?->format( 'Y-m-d H:i:s' ),
		];
	}
}
