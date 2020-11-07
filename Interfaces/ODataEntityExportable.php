<?php
	interface ODataEntityExportable
		{
		public static function ExportOne($obj) : bool;
		public static function Export(Sheduler $sheduler, array $payload = []) : bool;
		}