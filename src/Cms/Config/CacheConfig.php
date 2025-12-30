<?php

namespace Neuron\Cms\Config;

/**
 * Cache configuration constants
 *
 * @package Neuron\Cms\Config
 */
final class CacheConfig
{
	/**
	 * Default cache TTL (1 hour)
	 */
	public const DEFAULT_TTL = 3600;

	/**
	 * Short cache TTL (5 minutes)
	 */
	public const SHORT_TTL = 300;

	/**
	 * Long cache TTL (24 hours)
	 */
	public const LONG_TTL = 86400;

	/**
	 * Session TTL (2 weeks)
	 */
	public const SESSION_TTL = 1209600;

	/**
	 * Remember me TTL (30 days)
	 */
	public const REMEMBER_ME_TTL = 2592000;

	private function __construct()
	{
		// Prevent instantiation
	}
}
