<?php
	use Ramsey\Uuid\Uuid;
	use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;

	class ODataObject
		{
		const pk = 'id';
		const pkINT = false;
		const title = null;
		const isEntity = true;

		const stdFields = [
			'CreateUser'	=>	'CreateUser',
			'CreateDate'	=>	'CreateDate',
			'ChangeUser'	=>	'ChangeUser',
			'ChangeDate'	=>	'ChangeDate',
			'IDRecStatus'	=>	'IDRecStatus',
			];

		private $data = [], $newdata = [], $entity, $classname;
		const links = []; # [prop=>entity,]
		
		public function __construct(array $data = [])
			{
			$this->classname = get_called_class();
			$this->entity = self::class2entity($this->classname);
			foreach($data as $prop=>$val)
				{
				if(substr($prop,0,1) == '@')
					{
					unset($data[$prop]);
					}
				else
					{
					$val = is_array($val) ? new TPart($val) : $val;
					$data[$prop] = $val;
					}
				}
			$this->data = $data;
			}

		public static function class2entity(string $classname) : string
			{
			return substr($classname,strlen(ODataSet::entitypref));
			}
		
		protected function getLinks() : array
			{
			$classname = $this->classname;
			return $classname::links;
			}
		
		public function __toString() : string
			{
			$classname = get_called_class();
			$prop = defined($classname.'::title') && $classname::title ? $classname::title : $classname::pk;
			return array_key_exists($prop,$this->data) ? (string)$this->data[$prop] : '';
			}
	
		public function getEntity() : string
			{
			return $this->entity;
			}
	
		public function getObjectLink() : string
			{
			$classname = $this->classname;
			return Router::Template([$this->entity,$this->data[$classname::pk]],"$1($2)");
			}

		/**
		 * @param string
		 * @param array || null
		 * @throws Exception
		 * @return ODataObject || ODataSet
		 */
		public function __call($prop, $arguments)
			{
			$classname = $this->classname;
			if(array_key_exists($prop,$classname::links))
				{
				# relation
				return $this->Rel($prop,$arguments);
				}
			else
				{
				# value
				if(array_key_exists($prop,$this->data))
					{
					return $this->data[$prop];
					}
				}
			throw new Exception('Can not read property: '.$classname.'.'.$prop);
			}
	
		private function Rel($prop, $arguments = []) : ODataSet
			{
			$classname = $this->classname;
			if(array_key_exists($prop,$classname::links) && $relEntity = $classname::links[$prop])
				{
				return new ODataSet($relEntity,$arguments);
				}
			}

		public function getProp($prop)
			{
			$classname = $this->classname;
			if(array_key_exists($prop,$this->data))
				{
				return $this->data[$prop];
				}
			throw new Exception('Can not read property: '.$classname.'.'.$prop);
			}

		/**
		 * @param string
		 * @param array || null
		 * @throws Exception
		 * @return ODataObject || ODataSet
		 */
		public function __get($prop)
			{
			$classname = $this->classname;
			if(array_key_exists($prop,$classname::links))
				{
				if(!array_key_exists($prop,$this->data))
					{
					throw new Exception('Can not read property: '.$classname.'.'.$prop);
					}
				return $this->Rel($prop,[[$classname::getRelEntityPK($prop)=>$this->data[$prop]]]);
				}
			else
				{
				# value
				if(array_key_exists($prop,$this->data))
					{
					return $this->data[$prop];
					}
				if(!array_key_exists($classname::pk,$this->data) || !$this->data[$classname::pk])
					{
					return null;
					}
				}
			if(in_array($prop,['id','title']))
				{
				return $this->getReference();
				}
			throw new Exception('Can not read property: '.$classname.'.'.$prop);
			}
		
		public function __set($prop,$value) : self
			{
			if(is_object($value) && in_array(get_class($value),['ODataSet','ODataCollection']))
				{
				$ids = $value->map(function($obj){
					return $obj->getReference();
					});
				$this->data[$prop] = $ids;
				$this->newdata[$prop] = $ids;
				}
			else
				{
				$value = is_object($value) ? $value->getReference() : $value;
				if(!array_key_exists($prop,$this->data) || $this->data[$prop] != $value)
					{
					$this->data[$prop] = $value;
					$this->newdata[$prop] = $value;
					}
				}
			return $this;
			}
	
		public function setProp($prop,$value) : self
			{
			return $this->__set($prop,$value);
			}
	
		public function setProps($props = []) : self
			{
			foreach($props as $prop=>$value)
				{
				$prop ? $this->setProp($prop,$value) : null;
				}
			return $this;
			}

		public function getProps() : array
			{
			return $this->data;
			}

		public static function getTechProps() : array
			{
			$classname = get_called_class();
			$stdFields = defined($classname.'::stdFields') ? $classname::stdFields : self::stdFields;
			return [
				array_key_exists('IDRecStatus',$stdFields) ? $stdFields['IDRecStatus'] : null,
				array_key_exists('CreateUser',$stdFields) ? $stdFields['CreateUser'] : null,
				array_key_exists('CreateDate',$stdFields) ? $stdFields['CreateDate'] : null,
				array_key_exists('ChangeUser',$stdFields) ? $stdFields['ChangeUser'] : null,
				array_key_exists('ChangeDate',$stdFields) ? $stdFields['ChangeDate'] : null,
				$classname::pk,
				];
			}
	
		public function Flush() : self
			{
			$classname = $this->classname;
			[$k_active,$k_cu,$k_cd,$k_mu,$k_md,$pk] = $classname::getTechProps();
			if($k_active)
				{
				$this->newdata[$k_active] = array_key_exists($k_active,$this->newdata) ? $this->newdata[$k_active] : 0;
				}
			if(array_key_exists($pk,$this->data) && $this->data[$pk])
				{
				if(array_key_exists($k_active,$this->data) && $this->newdata[$k_active] == $this->data[$k_active])
					{
					unset($this->newdata[$k_active]);
					}
				if(count($this->newdata))
					{
					$this->newdata =
						($k_mu ? [$k_mu => OData::getUser()] : []) +
						($k_md ? [$k_md => date('Y-m-d\TH:i:s\Z')] : []) +
						($k_active ? [$k_active =>	intval(array_key_exists($k_active,$this->data) ? $this->data[$k_active] : false)] : []) +
						$this->newdata;
					if(OData::Update($this, $this->newdata))
						{
						$this->newdata = [];
						}
					}
				}
			else
				{
				$this->newdata =
					($k_cu ? [$k_cu => OData::getUser()] : []) +
					($k_cd ? [$k_cd => date('Y-m-d\TH:i:s\Z')] : []) +
					($k_active ? [$k_active =>	intval(array_key_exists($k_active,$this->data) ? $this->data[$k_active] : false)] : []) +
					$this->newdata + [$classname::pk => $classname::genPrimaryKey()];
				if($object = OData::Create($this->entity, $this->newdata))
					{
					$this->data = $object->getProps();
					$this->newdata = [];
					}
				}
			return $this;
			}
	
		public function Delete() : bool
			{
			return OData::Delete($this);
			}
		
		public static function find(array $filter = [], array $sort = [], array $select = [], int $top = null) : ODataSet
			{
			$entity = self::class2entity(get_called_class());
			return OData::$entity($filter,$select,null,$top,$sort);
			}

		public static function getCount(array $filter = []) : int
			{
			$entity = self::class2entity(get_called_class());
			$response = OData::__request(OData::getURL().(new ODataQuery([$filter,[],$entity,0,[],true])));
			if(is_array($response) && array_key_exists('@odata.count',$response))
				{
				return $response['@odata.count'];
				}
			}

		public static function findCollection(array $filter = [], array $sort = [], array $select = [], int $top = null) : ODataCollection
			{
			$entity = self::class2entity(get_called_class());
			return new ODataCollection(OData::$entity($filter,$select,null,$top,$sort));
			}

		/**
		 * @param array $filter | null
		 * @return ODataObject | null
		 */
		public static function findOneBy($filter = [])
			{
			$entity = self::class2entity(get_called_class());
			return OData::$entity($filter,[],null,1)->shift();
			}

		/**
		 * @param string | int | null
		 * @return ODataObject
		 */
		public static function getById(string $id)
			{
			$classname = get_called_class();
			$entity = self::class2entity($classname);
			return OData::$entity([
				$classname::pk	=>	$classname::isPKint() ? (int)$id : (string)$id
				],[],null,1)->shift();
			}
		
		public static function sandbox(callable $callback, $default = null)
			{
			try
				{
				return $callback();
				}
			catch(Throwable $exception)
				{
				Console::isKey('e') ? Console::Write($exception->getMessage()) : null;
				return $default;
				}
			}

		public static function getRelEntityPK($prop) : string
			{
			$classname = get_called_class();
			if(array_key_exists($prop,$classname::links) && $relEntity = $classname::links[$prop])
				{
				$relClass = ODataSet::entitypref.$relEntity;
				if(!class_exists($relClass))
					{
					throw new Exception('Class does not exists: '.$relClass);
					}
				return $relClass::pk;
				}
			throw new Exception('Is not relation property: '.$prop);
			}

		public static function genGUID() : string
			{
			return Uuid::uuid1(hexdec('0x'.substr(bin2hex((string)Site::getCurrent()),0,12)))->toString();
			}

		private static function nextPK() : int
			{
			$classname = get_called_class();
			$entity = self::class2entity($classname);
			$response = OData::__request(OData::getURL().(new ODataQuery([[],[$classname::pk],$entity,1,[$classname::pk =>'desc']])));
			if(is_array($response) && array_key_exists('value',$response) && is_array($response['value']) && isset($response['value'][0]) && array_key_exists($classname::pk,$response['value'][0]))
				{
				return intval($response['value'][0][$classname::pk]) + 1;
				}
			}

		public function getReference()
			{
			$classname = $this->classname;
			return array_key_exists($classname::pk,$this->data) ? $this->data[$classname::pk] : null;
			}

		public function Async(array $payload = []) : self
			{
			$classname = $this->classname;
			self::isSynchronousMode() ? $classname::getById($this->getReference())->Queue($payload) : Queue::Push($this,$payload);
			return $this;
			}

		public function Queue(array $payload = []) : bool
			{
			return true;
			}

		# class::pkINT
		public static function isPKint() : bool
			{
			$classname = get_called_class();
			return defined($classname.'::pkINT') && $classname::pkINT;
			}

		private static function genPrimaryKey() : string
			{
			$classname = get_called_class();
			$isINT = $classname::isPKint();
			do
				{
				$pk = $isINT ? self::nextPK() : self::genGUID();
				}
			while($classname::getCount([$classname::pk=>$pk]));
			return (string)$pk;
			}

		public static function getEntityHumanName() : string
			{
			$classname = get_called_class();
			return defined($classname.'::entityName') ? $classname::entityName : $classname;
			}

		public function getDetailPageUrl()
			{
			$classname = $this->classname;
			return Router::Template([$classname,$this->{$classname::pk}],'/backend/odata/entity/object/?entity=$1&$id_object=$2');
			}

		public function isNew() : bool
			{
			$classname = get_called_class();
			return !array_key_exists($classname::pk,$this->data) || !$this->data[$classname::pk];
			}

		public static function isSynchronousMode() : bool
			{
			return Console::isKey('x','cron');
			}
		}