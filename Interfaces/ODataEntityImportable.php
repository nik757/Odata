<?php
	interface ODataEntityImportable
		{
		public function Queue(array $payload = []) : bool;
		public static function Import(Sheduler $sheduler, array $payload = []) : bool;
		}