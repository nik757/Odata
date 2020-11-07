<?php

	class ODataCollection implements Iterator
		{

		private $collection = [], $data = [];

		public function __construct($collection = [])
			{
			if($collection instanceof ODataSet)
				{
				$collection = $collection->map(function ($obj){
					return $obj;
					});
				}
			if (is_array($collection))
				{
				$this->collection = array_values($collection);
				}
			}

		public function rewind()
			{
			reset($this->collection);
			}

		public function current()
			{
			return current($this->collection);
			}

		public function key()
			{
			return key($this->collection);
			}

		public function next()
			{
			return next($this->collection);
			}

		public function valid()
			{
			$key = key($this->collection);
			return $key !== null && $key !== false;
			}

		public function append($obj)
			{
			$this->collection[] = $obj;
			return $this;
			}

		# возвращает количество объектов
		public function count() : int
			{
			return count($this->collection);
			}

		public function getClass()
			{
			foreach ($this->collection as $obj)
				{
				return get_class($obj);
				}
			return false;
			}

		# возвращает новую коллекцию, отфильтрованную по filter
		public function filter($filter = [], $sort = []) : self
			{
			if (count($this->collection) && $classname = $this->getClass())
				{
				$filter[$classname::pk] = $this->extract($classname::pk);
				return $classname::findCollection($filter,$sort);
				}
			else
				{
				return new self;
				}
			}

		# возвращает массив значений свойст prop объектов коллекции
		public function extract($prop) : array
			{
			$res = [];
			if($classname = $this->getClass())
				{
				$pk = $classname::pk;
				foreach ($this->collection as $obj)
					{
					if($obj && $rel = $obj->$prop)
						{
						$res[$obj->$pk] = $rel;
						}
					}
				}
			return $res;
			}

		# возвращает расхождение коллекций
		public function diff(ODataCollection $collection) : ODataCollection
			{
			if($classname = $this->getClass())
				{
				$pk = $classname::pk;
				$ids = array_keys(array_diff($this->extract($pk),$collection->extract($pk)));
				return count($ids) ? $classname::findCollection([$pk=>$ids]) : new self;
				}
			return new self;
			}

		# возвращает схождение коллекций
		public function intersect(ODataCollection $collection) : ODataCollection
			{
			if($classname = $this->getClass())
				{
				$pk = $classname::pk;
				$ids = array_intersect($this->extract($pk),$collection->extract($pk));
				return count($ids) ? $classname::findCollection([$pk=>$ids]) : new self;
				}
			return new self;
			}

		# возвращает массив значений свойст объектов по связи $rel
		public function extractFields($prop, $rel) : array
			{
			$res = [];
			foreach ($this->collection as $obj)
				{
				foreach ($obj->extractFields($prop, $rel) as $val)
					{
					$res[$val] = $val;
					}
				}
			return array_keys($res);
			}

		public function get()
			{
			return $this->data;
			}

		public function __call($method, $args)
			{
			$this->data = [];
			foreach ($this->collection as $obj)
				{
				if (method_exists($obj, $method))
					{
					$this->data[] = $obj->$method(isset($args[0]) ? $args[0] : null, isset($args[1]) ? $args[1] : null);
					}
				else
					{
					throw new Exception('Method does not exists: ' . $method);
					}
				}
			return $this;
			}

		public function map(callable $callback) : array
			{
			if(!is_callable($callback))
				{
				throw new Exception('Callback function wrong');
				}
			$res = [];
			foreach ($this->collection as $obj)
				{
				$res[] = $callback($obj);
				}
			return $res;
			}

		# добавляет объекты текущей коллекции в указанную коллекцию
		public function appendTo(ODataCollection $collection) : ODataCollection
			{
			foreach ($this->collection as $obj)
				{
				$collection->append($obj);
				}
			return $this;
			}

		# добавляет объекты коллекции в текущуюю
		public function merge(ODataCollection $collection) : ODataCollection
			{
			foreach ($collection as $obj)
				{
				$this->append($obj);
				}
			return $this;
			}

		# вызывает callback, если в коллеции нет объектов, добавляет результат в коллекцию
		public function ifempty(callable $callback)
			{
			if(!count($this->collection))
				{
				$this->append($callback());
				}
			return $this;
			}

		public function reduce(callable $callback)
			{
			if(!is_callable($callback))
				{
				throw new Exception('Callback function wrong');
				}
			$val = null;
			foreach ($this->collection as $obj)
				{
				$callback($val,$obj);
				}
			return $val;
			}

		public function shuffle() : ODataCollection
			{
			shuffle($this->collection);
			return $this;
			}

		public function __toString()
			{
			$res = [];
			foreach ($this->collection as $obj)
				{
				$res[] = (string)$obj;
				}
			return implode(', ',$res);
			}

		public function unique($sort = []) : ODataCollection
			{
			$classname = $this->getClass();
			$pk = $classname::pk;
			return $this->filter([$pk=>array_unique($this->extract($pk))],$sort);
			}
		}

/*
	example_1
	$ODataCollection1 = ODataCounteragent::findCollection(['IDCounteragent'=>'ee02d1a8-57d0-4495-9670-501992c8650f']);
	$ODataCollection2 = ODataCounteragent::findCollection(['IDCounteragent'=>'8c1e920c-7cfa-4678-b4aa-000057c3a7b6']);
		return $ODataCollection1->merge($ODataCollection2)->map(function (ODataCounteragent $counteragent){
		self::Write($counteragent->CounteragentName);
		});
*/
