<?php
namespace chillerlan\Settings;

use IteratorAggregate;
use Traversable;

abstract class SettingsContainerAbstract implements SettingsContainerInterface, IteratorAggregate{

	/**
	 * @param iterable|null $properties
	 */
	public function __construct(iterable $properties = null){
		if(!empty($properties)){
			$this->fromIterable($properties);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function __get(string $property){

		if(property_exists($this, $property)){
			return $this->{$property};
		}

		return null;
	}

	/**
	 * @inheritDoc
	 */
	public function __set(string $property, $value):void{
		if(property_exists($this, $property)){
			$this->{$property} = $value;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function __isset(string $property):bool{
		return property_exists($this, $property) && isset($this->{$property});
	}

	/**
	 * @inheritDoc
	 */
	public function __unset(string $property):void{
		if(property_exists($this, $property)){
			unset($this->{$property});
		}
	}

	/**
	 * @inheritDoc
	 */
	public function toArray():array{
		$data = [];

		foreach(get_object_vars($this) as $property => $value){
			$data[$property] = $value;
		}

		return $data;
	}

	/**
	 * @inheritDoc
	 */
	public function fromJson(string $json):void{
		$this->fromArray(json_decode($json, true));
	}

	/**
	 * @inheritDoc
	 */
	public function fromArray(array $properties):void{
		$this->fromIterable($properties);
	}

	/**
	 * @param iterable $properties
	 */
	protected function fromIterable(iterable $properties):void{
		foreach($properties as $key => $value){
			$this->__set($key, $value);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function merge(array $properties):void{
		$this->fromArray($properties);
	}

	/**
	 * @inheritDoc
	 */
	public function set(array $properties):void{
		$this->fromArray($properties);
	}

	/**
	 * @inheritDoc
	 */
	public function getIterator():Traversable{
		return new SettingsIterator($this->toArray());
	}

}