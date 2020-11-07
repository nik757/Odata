<?php

	class ODataQuote
		{
		private $val;

		public function __construct($val)
			{
			$this->val = $val;
			}

		public function __toString()
			{
			return $this->val;
			}
		}