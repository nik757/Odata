<?php
	class ODataDate
		{
		private $date;

		public function __construct($date=null)
			{
			$this->date = $date;
			}

		public function getReference()
			{
			return "cast(".(new DateTime($this->date))->format('Y-m-d\TH:i:s\Z').",Edm.DateTimeOffset)";
			}

		public function __toString()
			{
			return (string)$this->date;
			}
		}