<?php
	class ODataSet implements IteratorAggregate
		{
		const slice = 8;
		const entitypref = 'OData';
		const propPK = '_primary_key';
	
		private $method,$entity,$classname,$arguments,$ids = [], $isone = false, $data = [], $pk;
	
		public function count() : int
			{
			return count($this->ids);
			}

		/**
		 * @return ODataObject | null
		 */
		public function shift()
			{
			foreach($this as $obj)
				{
				return $obj;
				}
			}
	
		public function extract($prop) : array
			{
			$res = [];
			foreach($this as $obj)
				{
				$val = $obj->$prop;
				$res[] = $val instanceof self ? $val->extract($prop) : $val;
				}
			return $res;
			}
	
		public function map(callable $callback) : array
			{
			$res = [];
			foreach($this as $obj)
				{
				$res[] = $callback($obj);
				}
			return $res;
			}

		public function filter(array $filter = [], array $sort = []) : ODataSet
			{
			$classname = $this->classname;
			return $classname::find([$classname::pk=>$this->extract($classname::pk)] + $filter, $sort);
			}
		
		public function get() : array
			{
			return $this->data;
			}

		public function __call($method, $args) : ODataSet
			{
			$this->data = [];
			foreach ($this as $obj)
				{
				if (method_exists($obj, $method))
					{
					$this->data[] = $obj->$method(isset($args[0]) ? $args[0] : null, isset($args[1]) ? $args[1] : null);
					}
				else
					{
					throw new Exception('OData entity or local method does not exists: ' . $method);
					}
				}
			return $this;
			}
		
		public function __toString()
			{
			return (string)$this->entity;
			}
	
		private function request(array $filter = null, array $select = null) : array
			{
			$arguments = $this->arguments;
			$arguments[0] = $filter && is_array($filter) ? $filter : (isset($arguments[0]) ? $arguments[0] : []);
			$arguments[1] = $select && is_array($select) ? $select : (isset($arguments[1]) ? $arguments[1] : []);
			try
				{
				return OData::__request(OData::getURL().$this->method.(new ODataQuery($arguments,$this->entity)));
				}
			catch (ODataNotFoundException $exception)
				{
				return ['value'=>[]];
				}
			}
	
		public function __construct($method,$arguments)
			{
			$entity = $this->entity = $this->method = $method;
			$classname = $this->classname = self::entitypref.$entity;
			$this->arguments = $arguments;
			$this->pk = $classname::pk;
			if(array_key_exists($this->pk,$this->arguments[0]) && (!is_array($this->arguments[0][$this->pk]) || count($this->arguments[0][$this->pk]) == 1) && $pkID = $this->arguments[0][$this->pk])
				{
				unset($this->arguments[0][$this->pk]);
				$id = is_array($pkID) ? array_shift($pkID) : $pkID;
				$this->arguments[0][self::propPK] = $id;
				$this->isone = true;
				$this->ids[] = $id;
				}
			else
				{
				$response = self::request([], [$classname::pk]);
				foreach(array_key_exists($classname::pk, $response) ? [(object)$response] : $response['value'] as $row)
					{
					$row = (array)$row;
					$this->ids[] = $row[$classname::pk];
					}
				}
			}
		
		public function getIterator()
			{
			$classname = $this->classname;
			if($this->isone)
				{
				$response = self::request();
				foreach(array_key_exists($this->pk,$response) ? [(object)$response] : $response['value'] as $obj)
					{
					$row = (array)$obj;
					yield $row[$this->pk] => new $classname($row);
					}
				}
			else
				{
				$pages = ceil($this->count() / self::slice);
				$page = 1;
				while($page <= $pages)
					{
					$offset = ($page - 1) * self::slice;
					$ids = array_slice($this->ids,$offset,self::slice);
					$response = self::request([$this->pk=>$ids]);
					foreach(array_key_exists($this->pk,$response) ? [(object)$response] : $response['value'] as $obj)
						{
						$row = (array)$obj;
						yield $row[$this->pk] => new $classname($row);
						}
					$page++;
					}
				}
			}
	
		}
