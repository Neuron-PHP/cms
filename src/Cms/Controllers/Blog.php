<?php
namespace Neuron\Cms\Controllers;

use Blahg\Article;
use Blahg\Exception\ArticleMissingBody;
use Blahg\Exception\ArticleNotFound;
use Blahg\Repository;
use JetBrains\PhpStorm\NoReturn;
use Neuron\Data\Filter\Get;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Routing\Router;

class Blog extends Content
{
	private Repository $repository;

	/**
	 * @param Router $Router
	 */
	public function __construct( Router $Router )
	{
		parent::__construct( $Router );

		$Get = new Get();

		$this->setRepository( new Repository(
			"../blog",
			$Get->filterScalar( 'drafts' ) ? true : false
			)
		);
	}

	public function getRepository(): Repository
	{
		return $this->repository;
	}

	public function setRepository( Repository $Repository ): self
	{
		$this->repository = $Repository;
		return $this;
	}

	public function index( array $Parameters, ?Request $Request ): string
	{
		return $this->renderHtml(
			HttpResponseStatus::OK,
			[
				'Articles'		=> $this->repository->getArticles(),
				'Categories'	=> $this->repository->getCategories(),
				'Tags'      	=> $this->repository->getTags(),
				'Title'    		=> $this->getTitle() . ' | ' . $this->getName(),
				'Description' 	=> $this->getDescription(),
			],
			'index'
		);
	}

	/**
	 * @param array $Parameters
	 * @param Request|null $Request
	 * @return string
	 * @throws \Neuron\Core\Exceptions\NotFound
	 */
	public function show( array $Parameters, ?Request $Request ): string
	{
		try
		{
			$Article = $this->repository->getArticleBySlug( $Parameters[ 'title'] );
		}
		catch( ArticleNotFound  $Exception )
		{
			$Article = new Article();
			$Article->setTitle( 'Character Not Found' );
			$Article->setBody( 'The requested character does not exist.' );
			$Article->setTags( [] );
			$Article->setDatePublished( '1969-06-09' );
		}
		catch( ArticleMissingBody $Exception )
		{
			$Article = new Article();
			$Article->setTitle( 'Article Body Not Found' );
			$Article->setBody( 'The requested article is missing its body text.' );
			$Article->setTags( [] );
			$Article->setDatePublished( '1969-06-09' );
		}

		return $this->renderHtml(
			HttpResponseStatus::OK,
			[
				'Categories'=> $this->repository->getCategories(),
				'Tags'      => $this->repository->getTags(),
				'Article' 	=> $Article,
				'Title'     => $Article->getTitle() . ' | ' . $this->getName()
			],
			'show'
		);
	}

	/**
	 * @param array $Parameters
	 * @param Request|null $Request
	 * @return string
	 * @throws \Neuron\Core\Exceptions\NotFound
	 */
	public function tag( array $Parameters, ?Request $Request ): string
	{
		$Tag = $Parameters[ 'tag' ];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			[
				'Categories'=> $this->repository->getCategories(),
				'Tags'      => $this->repository->getTags(),
				'Articles'	=> $this->repository->getArticlesByTag( $Tag ),
				'Title'    	=> "Characters tagged with $Tag | " . $this->getName(),
				'Tag'      	=> $Tag
			],
			'index'
		);
	}

	/**
	 * @param array $Parameters
	 * @param Request|null $Request
	 * @return string
	 * @throws \Neuron\Core\Exceptions\NotFound
	 */
	public function category( array $Parameters, ?Request $Request ): string
	{
		$Category = $Parameters[ 'category' ];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			[
				'Categories'=> $this->repository->getCategories(),
				'Tags'      => $this->repository->getTags(),
				'Articles'	=> $this->repository->getArticlesByCategory( $Category ),
				'Title'    	=> "Characters in campaign $Category | " . $this->getName(),
				'Category' 	=> $Category
			],
			'index'
		);
	}

	/**
	 * @param array $Parameters
	 * @param Request|null $Request
	 * @return string
	 */
	#[NoReturn] public function feed( array $Parameters, ?Request $Request ): string
	{
		// Suppress deprecation warnings for this request
		error_reporting(E_ALL & ~E_DEPRECATED);

		return $this->repository
			->getFeed(
				$this->getName(),
				$this->getDescription(),
				$this->getUrl(),
				$this->getRssUrl(),
				$this->repository->getArticles()
			);
	}
}
