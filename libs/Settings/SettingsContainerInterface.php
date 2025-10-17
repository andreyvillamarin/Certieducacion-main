<?php
namespace chillerlan\Settings;

use Traversable;

interface SettingsContainerInterface extends Traversable{
	public function __get(string $property);
	public function __set(string $property, $value):void;
	public function __isset(string $property):bool;
	public function __unset(string $property):void;
	public function toArray():array;
	public function fromJson(string $json):void;
    public function fromArray(array $properties):void;
	public function merge(array $properties):void;
	public function set(array $properties):void;
}