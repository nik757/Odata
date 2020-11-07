<?php

	class ODataRefence
		{
		private $guid;

		public function __construct($val)
			{
			if(is_object($val) && method_exists($val,'getProps'))
				{
				$props = $val->getProps();
				foreach(['sherpguid','CRMguid','guid'] as $prop)
					{
					if(array_key_exists($prop, $props) && (new GuidValidator())->Check($val->$prop))
						{
						$this->guid = $val->$prop;
						}
					}
				$this->guid = $this->guid ? $this->guid : GUID_EMPTY;
				}
			else
				{
				$this->guid = $val;
				}
			}

		public function getReference()
			{
			return ['Id'=>$this->guid];
			}

		public function __toString()
			{
			return $this->guid;
			}
		}