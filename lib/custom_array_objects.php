<?php

class LinkedArrayObject extends ArrayObject {
	protected $parent;

	public function __construct($parent, $data = null) {
		if (!is_a($parent, 'LinkedArrayObject')) {
			throw new InvalidArgumentException('Must be LinkedArrayObject');
		}
		$this->parent = $parent;
		$this->fill($data);
	}

	protected function fill($data) {
		if (is_array($data) || is_object($data)) {
			foreach ($data as $key => $value) {
				$this->offsetSet($key, $value);
			}
		}
	}

	protected function adapt($value) {
		if (is_array($value)) {
			$class = get_class($this);
			$value = new $class($this, $value);
		}
		return $value;
	}

	public function offsetSet($key, $value) {
		parent::offsetSet($key, $this->adapt($value));
	}

	public function append($value) {
		parent::append($this->adapt($value));
	}

	public function getArrayCopy() {
		$ret = array();
		foreach ($data as $key => $value) {
			if (is_a($value, 'ArrayObject')) {
				$value = $value->getArrayCopy();
			}
			$ret[$key] = $value;
		}
		return $ret;
	}

	public function exchangeArray($_ = null) {
		throw new RuntimeException('Method not supported in subclass');
	}
}

abstract class NotifiedLinkedArrayObject extends LinkedArrayObject {

	## abstract methods

	abstract public function writeNotify();
	abstract public function readNotify();

	## ArrayObject overrides

	# write functions

	public function append($value) {
		$this->readNotify();
		parent::append($value);
		$this->writeNotify();
	}

	public function asort() {
		$this->readNotify();
		parent::asort();
		$this->writeNotify();
	}

	public function ksort() {
		$this->readNotify();
		parent::ksort();
		$this->writeNotify();
	}

	public function natcasesort() {
		$this->readNotify();
		parent::natcasesort();
		$this->writeNotify();
	}

	public function natsort() {
		$this->readNotify();
		parent::natsort();
		$this->writeNotify();
	}

	public function offsetSet($key, $value) {
		$this->readNotify();
		parent::offsetSet($key, $value);
		$this->writeNotify();
	}

	public function offsetUnset($key) {
		$this->readNotify();
		parent::offsetUnset($key);
		$this->writeNotify();
	}

	public function uasort() {
		$this->readNotify();
		parent::uasort();
		$this->writeNotify();
	}

	public function uksort() {
		$this->readNotify();
		parent::uksort();
		$this->writeNotify();
	}

	# read functions

	public function count() {
		$this->readNotify();
		return parent::count();
	}

	public function getArrayCopy() {
		$this->readNotify();
		return parent::getArrayCopy();
	}

	public function offsetExists($index) {
		$this->readNotify();
		return parent::offsetExists($index);
	}

	public function offsetGet($index) {
		$this->readNotify();
		return parent::offsetGet($index);
	}

}

class BubbleNotifyLinkedArrayObject extends NotifiedLinkedArrayObject {
	public function writeNotify() {
		$this->parent->writeNotify();
	}

	public function readNotify() {
		$this->parent->readNotify();
	}

}

