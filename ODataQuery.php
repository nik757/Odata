<?php
	class ODataQuery
		{
		private $filter,$select,$relmethod,$top,$orderby,$count,$entity,$classname;

		# 0 $filter
		# 1 $select
		# 2 mehod
		# 3 $top
		# 4 $orderby
		# 5 count [true/false]
		public function __construct(array $arguments = null, $entity = null)
			{
			$this->entity = $entity;
			$arguments = is_array($arguments) && isset($arguments[0]) && isset($arguments[1]) ? $arguments : [];
			$this->relmethod = isset($arguments[2]) && $arguments[2] ? '/'.$arguments[2] : '';

			$this->where(isset($arguments[0]) && is_array($arguments[0]) ? $arguments[0] : [])
				->select(isset($arguments[1]) && is_array($arguments[1]) ? $arguments[1] : [])
				->top(isset($arguments[3]) && is_numeric($arguments[3]) ? (int)$arguments[3] : null)
				->orderby(isset($arguments[4]) && is_array($arguments[4]) ? $arguments[4] : [])
				->count(isset($arguments[5]) && $arguments[5]);

			$classname = ODataSet::entitypref.$this->entity;
			if($this->entity && class_exists($classname))
				{
				$this->classname = $classname;
				$this->select = array_unique(!count($this->select) || in_array($classname::pk, $this->select) ? $this->select : array_merge($this->select, [$classname::pk]));
				}
			}

		public function where(array $where)
			{
			$this->filter = $where;
			return $this;
			}

		public function select(array $select)
			{
			$this->select = $select;
			return $this;
			}

		public function top($top)
			{
			$this->top = $top;
			return $this;
			}

		public function orderby(array $orderby)
			{
			$this->orderby = $orderby;
			return $this;
			}

		public function count(bool $iscount)
			{
			$this->count = $iscount;
			return $this;
			}

		public function __toString() : string
			{
			$query = $this->prepareQuery();
			while(substr_count($query,'&&'))
				{
				$query = str_replace('&&','&',$query);
				}
			return $query;
			}
		
		private $cond = [], $logic = 'and', $query = [];
		
		private function prepareQuery()
			{
			$classname = ODataSet::entitypref.$this->entity;
			if(array_key_exists(ODataSet::propPK,$this->filter) && !is_array($this->filter[ODataSet::propPK]))
				{
				if($classname::isEntity)
					{
					$this->query[] = "(".$this->filter[ODataSet::propPK].")";
					}
				else
					{
					$this->filter[$classname::pk] = $this->filter[ODataSet::propPK];
					}
				unset($this->filter[ODataSet::propPK]);
				}
			if($relmethod = $this->relmethod)
				{
				$this->query[] = $relmethod;
				}
			foreach($this->filter as $prop => $val)
				{
				$val = is_object($val) && method_exists($val,'getReference') ? $val->getReference() : $val;
				$val = is_object($val) && in_array(get_class($val),['ODataSet','ODataCollection']) ? $this->extractPKs($val) : $val;
				$this->changeLogic($prop,$val);
				$method = strtolower(gettype($val)).'Type';
				if(!method_exists($this, $method))
					{
					throw new Exception('Filter type not supported: '.$method);
					}
				$option = $this->getLogicOption($prop);
				if($cond = $this->$method($prop, $val, $option))
					{
					$this->cond[] = $cond;
					}
				}
			return implode('',[
				implode('',$this->query),
				'?',
				$this->top && !$this->count ? '&$top='.$this->top : '',
				count($this->cond) ? '&$filter='.implode(' '.$this->logic.' ', $this->cond) : '',
				count($this->select) ? '&$select='.implode(',',$this->select) : '',
				count($this->orderby) ? '&$orderby='.implode(',',$this->prepareSort()) : '',
				$this->count ? '&$count=true&$top=0' : '',
				]);
			}

		private function extractPKs(iterable $collection) : array
			{
			return $collection->map(function ($obj){
				return $obj->getReference();
				});
			}

		private function prepareSort() : array
			{
			$sort = [];
			foreach ($this->orderby as $prop=>$direction)
				{
				$sort[] = $prop.' '.$direction;
				}
			return $sort;
			}

		const options = [
			'='		=>	'eq',
			'!'		=>	'ne',
			'>'		=>	'gt',
			'<'		=>	'lt',
			];
		private function getLogicOption(&$prop) : string
			{
			$option = self::options['='];
			$firstSymbol = substr($prop,0,1);
			if(array_key_exists($firstSymbol,self::options))
				{
				$option = self::options[$firstSymbol];
				$prop = substr($prop,1);
				}
			return $option;
			}

		private function changeLogic($prop,$val)
			{
			if($prop == 'logic')
				{
				if(!in_array($val,['and','or']))
					{
					throw new Exception('Wrong logic option in filter: '.var_export($val,true));
					}
				$this->logic = $val;
				}
			return $this;
			}

		private function template(array $params,string $template) : string
			{
			return Router::Template($params,$template);
			}

		private function booleanType(string $prop, bool $val, string $option) : string
			{
			return $this->template([$prop,$val === true ? 'true' : 'false',$option],'$1 $3 $2');
			}
		
		private function arrayType(string $prop, array $val, string $option) : string
			{
			$localLogic = 'and';
			$subCond = [];
			if(array_key_exists('from',$val) || array_key_exists('to',$val))
				{
				# range
				foreach(['from'=>'ge','to'=>'le'] as $k=>$option)
					{
					if (array_key_exists($k,$val))
						{
						$subCond[] = implode(' ', [$prop, $option, is_object($val[$k]) ? $val[$k]->getReference() : $val[$k]]);
						}
					}
				}
			else
				{
				$localLogic = $option == 'eq' ? 'or' : 'and';
				foreach($val as $v)
					{
					$subCond[] = $this->stringType($prop,$v,$option);
					}
				}
			return count($subCond) ? '('.implode(' '.$localLogic.' ',$subCond).')' : '';
			}
		
		private function integerType(string $prop, string $val, string $option) : string
			{
			return $this->template([$prop,$val,$option],"$1 $3 $2");
			}
		
		private function doubleType(string $prop, string $val, string $option) : string
			{
			return $this->template([$prop,$val,$option],"$1 $3 $2");
			}
		
		private function stringType(string $prop, $val, string $option) : string
			{
			return $this->template([$prop,$val,$option],is_string($val) && !(new GuidValidator)->Check($val) ? "$1 $3 '$2'" : "$1 $3 $2");
			}
		
		private function nullType(string $prop,$val, string $option) : string
			{
			return $this->template([$prop,$option],'$1 $2 null');
			}

		private function objectType(string $prop,$val, string $option) : string
			{
			return $this->template([$prop,(string)$val,$option],"$1 $3 '$2'");
			}

		public function getReference() : ODataCollection
			{
			$classname = $this->classname;
			$objects = [];
			$this->request()->map(function (array $row) use (&$objects,$classname){
				$objects[] = new $classname($row);
				});
			return new ODataCollection($objects);
			}

		public function request() : TPart
			{
			$response = OData::__request(OData::getURL().$this->entity.(string)$this);
			return new TPart(array_key_exists('value',$response) ? $response['value'] : $response);
			}
		}

	/** @examples
	 * 1. get Counteragent's ids with role 'supplier'
	 * mixed way - query + ORM:
	 *	$ids = (new ODataQuery([['IDRole'=>ODataSubjectRole::find(['RoleCode'=>'supplier'])], ['IDCounteragent']],'CounteragentRole'))->request();
	 *
	 * pure way:
	 * 	$ids = (new ODataQuery(null,'CounteragentRole'))->where([
			'IDRole'	=>	(new ODataQuery(null,'SubjectRole'))->where(['RoleCode'=>'supplier'])
			])->select(['IDCounteragent'])->request();
	 */