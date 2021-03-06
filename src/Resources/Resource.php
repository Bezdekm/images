<?php

namespace WebChemistry\Images\Resources;

use Nette\Utils\Random;

abstract class Resource implements IResource {

	const PREFIX_SEP = '_._';

	/** @var array */
	public $additional = [];

	/** @var string */
	protected $name;

	/** @var string */
	protected $prefix;

	/** @var string */
	protected $namespace;

	/** @var array */
	protected $aliases = [];

	/************************* Properties **************************/

	public function setSuffix(string $suffix): void {
		$this->name = pathinfo($this->name)['filename'] . '.' . $suffix;
	}

	protected function setName(string $name): void {
		$this->name = $name;
	}

	/**
	 * @param null|string $namespace
	 * @throws ResourceException
	 */
	protected function setNamespace(?string $namespace) {
		if (!$namespace) {
			$namespace = null;

			return;
		}

		if (!preg_match('#^[\w/-]+$#', $namespace)) {
			throw new ResourceException('Namespace \'' . $namespace . '\' is not valid.');
		}

		$this->namespace = trim($namespace, '/');
	}

	public function generatePrefix(int $length = 10): void {
		$this->prefix = Random::generate($length);
	}

	/////////////////////////////////////////////////////////////////

	public function toModify(): bool {
		return (bool) $this->aliases;
	}

	public function getAliases(): array {
		return $this->aliases;
	}

	public function setAlias(string $alias, array $args = []): void {
		$this->aliases[$alias] = $args;
	}

	public function setAliases(array $aliases): void {
		$this->aliases = $aliases;
	}

	/**
	 * @param string $id
	 * @throws ResourceException
	 */
	protected function parseId(string $id): void {
		$explode = explode('/', $id);
		$count = count($explode);

		$this->setName($explode[$count - 1]);
		if ($count !== 1) {
			$this->setNamespace(implode('/', array_slice($explode, 0, $count - 1)));
		}
	}

	/////////////////////////////////////////////////////////////////

	/**
	 * Combination of namespace and name
	 *
	 * @return string
	 */
	public function getId(): string {
		return ($this->namespace ? $this->namespace . '/' : '') . $this->getName();
	}

	public function getName(): string {
		return ($this->prefix ? $this->prefix . self::PREFIX_SEP : '') . $this->name;
	}

	public function getRawName(): string {
		return $this->name;
	}

	public function getNamespace(): ?string {
		return $this->namespace;
	}

	public function getPrefix(): ?string {
		return $this->prefix;
	}

	public function __toString(): string {
		return $this->getId();
	}

}
