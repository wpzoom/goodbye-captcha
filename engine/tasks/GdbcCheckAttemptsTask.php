<?php

defined( 'ABSPATH' ) || exit;

class GdbcCheckAttemptsTask extends MchGdbcWpTask
{
	public function __construct($runningInterval, $isRecurring)
	{
		parent::__construct($runningInterval, $isRecurring);
	}

	public function run()
	{}
}